<?php

namespace App\Services;

use App\Services\Contracts\NutritionAssistant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

use function Illuminate\Support\defer;

/**
 * Provides one food fact or piece of food trivia per day, written by the AI
 * and cached so it is asked at most once daily.
 *
 * Asking the AI can take many seconds (and fail), so it never happens while a
 * page is loading: the page gets a curated fact straight away, and the AI is
 * asked after the response has been sent. Its fact shows from the next page
 * load on.
 *
 * Three things keep it from repeating: the subject rotates through TOPICS by
 * calendar day, the AI is shown the facts used recently and told to avoid
 * them, and anything it repeats anyway is rejected.
 *
 * AI-written facts can be wrong, so each one goes through a second,
 * fact-checking request and is dropped unless it is confirmed. Whenever the
 * AI can't supply a usable fact, a curated one is served instead — those
 * rotate by day too, so the fallback never shows the same fact two days
 * running.
 */
class DailyFoodFact
{
    private const RECENT_KEY = 'food_fact:recent';

    /** How many past facts the AI is told to steer clear of. */
    private const RECENT_LIMIT = 30;

    private const MAX_LENGTH = 220;

    /**
     * One subject per day, in order. 31 entries, so the weekday a topic lands
     * on shifts each cycle.
     *
     * @var list<string>
     */
    private const TOPICS = [
        'how a common fruit is classified botanically',
        'where a familiar spice comes from',
        'a classic Filipino dish or ingredient',
        'fermented foods',
        'rice',
        'a food that is a well-known source of a particular vitamin',
        'the history of an everyday food',
        'what cooking does to food (kitchen science)',
        'fish and seafood',
        'vegetables and the plant families they belong to',
        'nuts and seeds',
        'coffee or tea',
        'chocolate and cacao',
        'culinary herbs',
        'beans, lentils and other legumes',
        'eggs or dairy',
        'traditional ways of preserving food',
        'street food in Southeast Asia',
        'bread and baking',
        'tropical fruits',
        'root crops such as cassava, ube, taro and sweet potato',
        'the origin of a food word or dish name',
        'taste, smell and how we sense flavour',
        'mushrooms and other fungi we eat',
        'cooking oils and fats',
        'salt, and how people have used it through history',
        'a traditional dish from another part of the world',
        'chili peppers and spiciness',
        'noodles and pasta',
        'coconut, calamansi or another staple of Philippine cooking',
        'how calories and macronutrients work',
    ];

    /**
     * Checked facts served when the AI can't be used.
     *
     * @var list<string>
     */
    private const FALLBACKS = [
        'Botanically, bananas are berries — but strawberries are not.',
        'Peanuts are legumes, not true nuts. They grow underground, in the same plant family as beans and peas.',
        'Vanilla comes from the seed pod of an orchid.',
        'White, green, oolong and black tea all come from the same plant, Camellia sinensis. The difference is how the leaves are processed.',
        'A chili\'s heat comes from capsaicin, which is concentrated in the pale inner ribs rather than in the seeds.',
        'Broccoli, cabbage, cauliflower, kale and Brussels sprouts are all varieties of a single species, Brassica oleracea.',
        'Ube is a purple yam. It is a different plant from both taro and purple sweet potato.',
        'The word "adobo" comes from the Spanish adobar, "to marinate" — but Filipinos were braising food in vinegar long before the Spanish arrived.',
        'Cashews grow hanging from the bottom of a fruit called the cashew apple.',
        'Saffron is the dried stigmas of a crocus flower. Each bloom has only three, picked by hand, which is why it is the costliest spice by weight.',
        'Fat provides about 9 calories per gram — more than double the roughly 4 per gram in protein and carbohydrates.',
        'Brown rice and white rice are the same grain. White rice has simply had the bran and germ polished away.',
        'Pineapple contains bromelain, an enzyme that breaks down protein. That is why it tenderises meat and can make your mouth tingle.',
        'Cooking tomatoes makes their lycopene easier for the body to absorb.',
        'Almonds are the seeds of a fruit closely related to peaches and plums.',
        'Garlic only develops its sharp smell once it is cut or crushed, when an enzyme reaction produces allicin.',
        'Calamansi is a natural citrus hybrid, a cross between a kumquat and a mandarin orange.',
        'Black, white and green peppercorns all come from the same vine. They are picked or processed at different stages.',
        'Mangoes belong to the same plant family as cashews and pistachios.',
        'Nutmeg and mace come from the same fruit: nutmeg is the seed, and mace is the red lacy covering around it.',
        'Mushrooms can make vitamin D when they are exposed to sunlight or UV light.',
        'A hen\'s breed decides whether her eggs are white or brown. Shell colour makes no difference to nutrition.',
        'Sweet potatoes are not closely related to potatoes. They belong to the morning glory family.',
        'Cinnamon is the dried inner bark of a tree.',
        'Rice is the staple food for more than half of the world\'s population.',
        'Popcorn pops because the water inside each kernel turns to steam and bursts the hard hull.',
        'Quinoa is a seed, not a cereal grain. The plant is related to spinach and beets.',
        'Red bell peppers contain more vitamin C, weight for weight, than oranges.',
        'Patis (fish sauce) is traditionally the clear liquid drawn off fermenting bagoong.',
        'Cutting an onion releases a sulfur compound that drifts up and irritates your eyes. That is what makes you tear up.',
        'A fresh egg sinks in water and an old one floats, because the air pocket inside grows as the egg ages.',
        'A coconut is not a true nut. Botanically it is a drupe, the same kind of fruit as a peach or an olive.',
        'Avocados do not ripen on the tree. They only begin to soften after they are picked.',
        'Most "wasabi" served outside Japan is actually horseradish coloured green.',
        'Sinigang gets its sourness from fruit: usually tamarind, but also kamias, guava or green mango depending on the region.',
        'Cucumbers are about 95% water.',
        'Tomatoes are botanically a fruit, though cooks treat them as a vegetable.',
    ];

    public function __construct(private readonly NutritionAssistant $ai) {}

    public static function key(): string
    {
        return 'food_fact:'.today()->toDateString();
    }

    public function today(): string
    {
        $cached = Cache::get(self::key());
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        // Nothing for today yet. Show a curated fact now and hold it for half
        // an hour, which also stops other page loads from asking the AI at the
        // same time; then ask the AI once this response is on its way.
        $fallback = $this->fallback();
        Cache::put(self::key(), $fallback, now()->addMinutes(30));

        defer(fn () => $this->writeTodaysFact());

        return $fallback;
    }

    /**
     * Ask the AI for today's fact and, if it gives a usable one, keep it for
     * the rest of the day. If not, the curated fact stays until it lapses and
     * the next page load triggers another try.
     */
    public function writeTodaysFact(): void
    {
        $fact = $this->fromAi();

        if ($fact !== null) {
            Cache::put(self::key(), $fact, today()->endOfDay());
            $this->remember($fact);
        }
    }

    /**
     * Today's subject. Consecutive days always get different topics.
     */
    public function topic(): string
    {
        return self::TOPICS[$this->dayNumber() % count(self::TOPICS)];
    }

    private function fromAi(): ?string
    {
        try {
            $fact = $this->clean($this->ai->shortText($this->prompt()));
        } catch (Throwable) {
            return null;
        }

        // Empty, or something we have shown recently — don't use it.
        if ($fact === '' || in_array($this->fingerprint($fact), array_map($this->fingerprint(...), $this->recent()), true)) {
            return null;
        }

        return $this->isConfirmed($fact) ? $fact : null;
    }

    /**
     * Have the AI fact-check its own output. Anything short of a clear "yes"
     * (including a failed request) counts as unconfirmed.
     */
    private function isConfirmed(string $fact): bool
    {
        $prompt = "You are a careful fact-checker for a food education app.\n"
            ."Statement: \"{$fact}\"\n"
            ."Is every claim in this statement accurate and well established, including any numbers, dates and comparisons?\n"
            .'Answer with exactly one word: YES or NO. Answer NO if any part is wrong, exaggerated, disputed, or you are not sure.';

        try {
            $verdict = $this->ai->shortText($prompt, temperature: 0.0);
        } catch (Throwable) {
            return false;
        }

        return Str::startsWith(Str::upper(ltrim($verdict, " \t\n\r\"'*")), 'YES');
    }

    private function prompt(): string
    {
        $prompt = "Share ONE interesting food fact or piece of food trivia about this subject: {$this->topic()}.\n"
            ."It must be true, well established and something a curious person could verify — no myths, no rumours, no made-up statistics.\n"
            ."Teach something surprising rather than giving diet or health advice.\n"
            ."Do not compare the amount of a nutrient between foods, and do not give serving-size equivalents: those are easy to get wrong.\n"
            .'Write one or two plain sentences, under 35 words. Reply with ONLY the fact: no title, no numbering, no quotation marks, no preamble such as "Did you know".';

        $recent = $this->recent();

        if ($recent !== []) {
            $prompt .= "\n\nThese facts were used recently. Do not repeat or rephrase any of them, and pick a different food:\n- ".implode("\n- ", $recent);
        }

        return $prompt;
    }

    /**
     * @return list<string>
     */
    private function recent(): array
    {
        $recent = Cache::get(self::RECENT_KEY, []);

        return is_array($recent) ? array_values(array_filter($recent, 'is_string')) : [];
    }

    private function remember(string $fact): void
    {
        $recent = array_slice([$fact, ...$this->recent()], 0, self::RECENT_LIMIT);

        Cache::forever(self::RECENT_KEY, $recent);
    }

    private function clean(string $raw): string
    {
        // The /u flag keeps these working on whole characters; without it a
        // multi-byte character could be cut in half and the text left invalid.
        $fact = preg_replace('/^[\s"\'“”‘’]+|[\s"\'“”‘’]+$/u', '', $raw) ?? trim($raw);
        $fact = preg_replace('/\s+/u', ' ', $fact) ?? $fact;
        $fact = preg_replace('/^did you know( that)?[:,?!.\s]*/iu', '', $fact) ?? $fact;

        return Str::limit(Str::ucfirst($fact), self::MAX_LENGTH - 1, '…');
    }

    /**
     * A loose form for spotting repeats: lower-case letters and digits only.
     */
    private function fingerprint(string $fact): string
    {
        return preg_replace('/[^a-z0-9]+/', '', Str::lower($fact)) ?? $fact;
    }

    private function fallback(): string
    {
        return self::FALLBACKS[$this->dayNumber() % count(self::FALLBACKS)];
    }

    /**
     * Whole days since a fixed date, so it goes up by exactly one each day.
     */
    private function dayNumber(): int
    {
        return (int) abs(Carbon::create(2000, 1, 1)->startOfDay()->diffInDays(today()));
    }
}
