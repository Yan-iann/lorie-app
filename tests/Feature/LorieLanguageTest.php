<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\DailyFoodFact;
use App\Support\LorieCoach;
use App\Support\WaterDay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LorieLanguageTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Http::preventStrayRequests();
        Cache::put(DailyFoodFact::key(), 'Bananas are berries.', now()->addDay());

        // A 2,000 kcal goal and (70 kg, moderate) a 2.8 L water goal.
        $this->user = User::factory()->onboarded()->create(['name' => 'Edrian', 'weight_kg' => 70, 'daily_goal' => 2000]);
        $this->actingAs($this->user);
    }

    private function speak(string $language, int $entries = 1, int $remainingKcal = 1700, int $percent = 15): string
    {
        $this->user->lorie_language = $language;

        return app(LorieCoach::class)->message($this->user, $entries, $remainingKcal, $percent, WaterDay::for($this->user, today()->toDateString()));
    }

    private function drink(int $ml): void
    {
        $this->user->waterLogs()->create(['amount_ml' => $ml, 'container' => 'bottle', 'consumed_on' => today()]);
    }

    public function test_lorie_speaks_each_language(): void
    {
        $this->assertSame("You've got 1,700 kcal left today. You're pacing well! Don't forget your water. You haven't had any yet.", $this->speak('en'));

        $this->assertStringStartsWith('May 1,700 kcal ka pang natitira ngayong araw.', $this->speak('tl'));
        $this->assertStringEndsWith('Wala ka pang naiinom ngayong araw.', $this->speak('tl'));

        $this->assertStringStartsWith('Naa pa kay 1,700 kcal nga nahabilin karong adlawa.', $this->speak('ceb'));
        $this->assertStringEndsWith('Wala pa kay nainom karong adlawa.', $this->speak('ceb'));

        $this->assertStringStartsWith("You've got 1,700 kcal left today. Steady lang, you're pacing well!", $this->speak('mix'));
    }

    public function test_the_food_line_follows_the_day(): void
    {
        $this->assertStringStartsWith('Fresh start, Edrian!', $this->speak('en', entries: 0, remainingKcal: 2000, percent: 0));
        $this->assertStringStartsWith("You're close to your calorie goal", $this->speak('en', remainingKcal: 300, percent: 85));
        $this->assertStringStartsWith("You're 250 kcal over today.", $this->speak('en', remainingKcal: -250, percent: 100));

        $this->assertStringStartsWith('Bag-ong sugod, Edrian!', $this->speak('ceb', entries: 0, remainingKcal: 2000, percent: 0));
        $this->assertStringStartsWith('Sobra na ta og 250 kcal karong adlawa.', $this->speak('ceb', remainingKcal: -250, percent: 100));
    }

    public function test_the_water_line_follows_how_much_has_been_drunk(): void
    {
        $this->assertStringEndsWith("Don't forget your water. You haven't had any yet.", $this->speak('en'));

        $this->drink(1000);
        $this->assertStringEndsWith('1.8 L of water to go. Keep sipping!', $this->speak('en'));
        $this->assertStringEndsWith('1.8 L pa nga tubig ang kulang. Padayon lang og inom!', $this->speak('ceb'));
        $this->assertStringEndsWith('1.8 L pa ng tubig ang kulang. Inom lang nang inom!', $this->speak('tl'));

        $this->drink(2000);
        $this->assertStringEndsWith('And your water goal is done. Nice!', $this->speak('en'));
        $this->assertStringEndsWith('Naabot na pud nimo ang imong water goal. Maayo kaayo!', $this->speak('ceb'));
    }

    public function test_new_accounts_start_on_the_mix_and_an_unknown_language_falls_back_to_it(): void
    {
        $this->assertSame('mix', User::factory()->create()->refresh()->lorie_language);

        $this->assertStringContainsString('Steady lang', $this->speak('klingon'));
    }

    public function test_the_language_is_chosen_on_the_profile_page_and_used_on_the_dashboard(): void
    {
        $profile = ['display_name' => 'Edrian', 'gender' => 'male', 'weight_kg' => 70, 'daily_goal' => 2000];

        $this->get(route('profile'))->assertOk()->assertSee('Lorie speaks in')->assertSee('Bisaya / Cebuano');

        $this->put(route('profile.update'), [...$profile, 'lorie_language' => 'ceb'])->assertSessionHasNoErrors();
        $this->assertSame('ceb', $this->user->refresh()->lorie_language);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Bag-ong sugod, Edrian!')
            ->assertSee('Ayaw kalimti ang pag-inom og tubig.');

        $this->put(route('profile.update'), [...$profile, 'lorie_language' => 'klingon'])->assertSessionHasErrors('lorie_language');

        // Saving the profile without the field leaves the choice alone.
        $this->put(route('profile.update'), $profile)->assertSessionHasNoErrors();
        $this->assertSame('ceb', $this->user->refresh()->lorie_language);
    }
}
