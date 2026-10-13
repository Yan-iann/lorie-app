<?php

namespace Tests\Feature;

use App\Services\DailyFoodFact;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DailyFoodFactTest extends TestCase
{
    private string $aiFact;

    private string $aiVerdict;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        config()->set('services.ai.provider', 'groq');
        config()->set('services.ai.fallback', null);
        config()->set('services.groq.key', 'test-key');
    }

    protected function tearDown(): void
    {
        Cache::flush();

        parent::tearDown();
    }

    /**
     * Fake the AI: it writes $fact when asked for one, and answers $verdict
     * when asked to fact-check.
     */
    private function fakeAi(string $fact, string $verdict = 'YES'): void
    {
        $firstCall = ! isset($this->aiFact);

        $this->aiFact = $fact;
        $this->aiVerdict = $verdict;

        // Registered once: later calls just change what the same fake answers.
        if ($firstCall) {
            Http::fake(['api.groq.com/*' => fn (Request $request) => Http::response([
                'choices' => [['message' => ['content' => $this->isFactCheck($request) ? $this->aiVerdict : $this->aiFact]]],
            ])]);
        }
    }

    private function isFactCheck(Request $request): bool
    {
        return str_contains($request['messages'][1]['content'], 'fact-checker');
    }

    private function fact(): string
    {
        return app(DailyFoodFact::class)->today();
    }

    public function test_the_fact_is_written_by_the_ai_cleaned_and_cached_for_the_day(): void
    {
        $this->fakeAi('“Did you know? vanilla comes from the seed pod of an orchid.”');

        // Quotes and the "Did you know" lead-in are stripped.
        $this->assertSame('Vanilla comes from the seed pod of an orchid.', $this->fact());

        // Cached for the day — a changed API response is not used on the next call.
        $this->fakeAi('Something different');
        $this->assertSame('Vanilla comes from the seed pod of an orchid.', $this->fact());
    }

    public function test_a_new_fact_is_requested_each_day_on_a_different_topic(): void
    {
        $this->travelTo('2026-10-13 09:00');
        $firstTopic = app(DailyFoodFact::class)->topic();
        $this->fakeAi('Bananas are berries.');
        $this->assertSame('Bananas are berries.', $this->fact());

        $this->travelTo('2026-10-14 09:00');
        $secondTopic = app(DailyFoodFact::class)->topic();
        $this->fakeAi('Cinnamon is the dried inner bark of a tree.');
        $this->assertSame('Cinnamon is the dried inner bark of a tree.', $this->fact());

        $this->assertNotSame($firstTopic, $secondTopic);

        // Each day's request names that day's topic, and the second one lists
        // yesterday's fact as something to avoid.
        $prompts = Http::recorded(fn (Request $request) => ! $this->isFactCheck($request))
            ->map(fn (array $pair) => $pair[0]['messages'][1]['content'])
            ->values();

        $this->assertCount(2, $prompts);
        $this->assertStringContainsString($firstTopic, $prompts[0]);
        $this->assertStringNotContainsString('used recently', $prompts[0]);
        $this->assertStringContainsString($secondTopic, $prompts[1]);
        $this->assertStringContainsString('Bananas are berries.', $prompts[1]);
    }

    public function test_a_repeated_fact_from_the_ai_is_rejected(): void
    {
        $this->travelTo('2026-10-13 09:00');
        $this->fakeAi('Bananas are berries.');
        $this->assertSame('Bananas are berries.', $this->fact());

        // Next day the AI says the same thing again (different punctuation and case).
        $this->travelTo('2026-10-14 09:00');
        $this->fakeAi('bananas are BERRIES!');
        $fact = $this->fact();

        $this->assertNotEmpty($fact);
        $this->assertStringNotContainsStringIgnoringCase('bananas are berries', $fact);
    }

    public function test_a_fact_the_checker_does_not_confirm_is_not_shown(): void
    {
        $this->fakeAi('A sheet of nori has as much calcium as a glass of milk.', verdict: 'NO');

        $fact = $this->fact();

        $this->assertNotEmpty($fact);
        $this->assertStringNotContainsString('nori', $fact);

        // The fact was sent for checking at temperature 0, for a steady verdict.
        Http::assertSent(fn (Request $request) => $this->isFactCheck($request)
            && $request['temperature'] === 0.0
            && str_contains($request['messages'][1]['content'], 'A sheet of nori'));
    }

    public function test_curated_facts_are_used_when_the_ai_is_down_and_still_change_daily(): void
    {
        Http::fake(['api.groq.com/*' => Http::response('unavailable', 500)]);

        $facts = [];

        foreach (range(0, 6) as $offset) {
            $this->travelTo(now()->parse('2026-10-13 09:00')->addDays($offset));
            $facts[] = $this->fact();
        }

        // A week of outages still shows seven different facts.
        $this->assertCount(7, array_unique($facts));
    }

    public function test_the_ai_is_asked_for_variety_when_writing(): void
    {
        $this->fakeAi('Bananas are berries.');

        $this->fact();

        // A higher temperature than the nutrition calls (0.2), so wording varies.
        Http::assertSent(fn (Request $request) => ! $this->isFactCheck($request) && $request['temperature'] === 0.9);
    }
}
