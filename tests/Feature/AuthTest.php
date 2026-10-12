<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use RuntimeException;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Http::preventStrayRequests();

        config()->set('services.google.client_id', 'test-client-id');
        config()->set('services.google.client_secret', 'test-client-secret');
    }

    private function fakeGoogleUser(array $attributes = []): void
    {
        Socialite::fake('google', SocialiteUser::fake([
            'id' => 'google-123',
            'name' => 'Lorie Tester',
            'email' => 'lorie@example.com',
            'email_verified' => true,
            ...$attributes,
        ]));
    }

    public function test_guests_are_sent_to_the_login_page(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->get(route('history'))->assertRedirect(route('login'));
        $this->get(route('profile'))->assertRedirect(route('login'));
        $this->post(route('entries.store'), ['description' => 'an apple'])->assertRedirect(route('login'));

        $this->assertDatabaseCount('food_entries', 0);
    }

    public function test_login_and_register_pages_render(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Sign in')->assertSee('Continue with Google');
        $this->get(route('register'))->assertOk()->assertSee('Create your account')->assertSee('Sign up with Google');
    }

    public function test_the_google_button_is_hidden_until_google_is_configured(): void
    {
        config()->set('services.google.client_id', null);

        $this->get(route('login'))->assertOk()->assertDontSee('Continue with Google');
        $this->get(route('auth.google.redirect'))->assertNotFound();
        $this->get(route('auth.google.callback'))->assertNotFound();
    }

    public function test_the_google_redirect_points_at_google_with_this_apps_callback(): void
    {
        $location = $this->get(route('auth.google.redirect'))->assertRedirect()->headers->get('Location');

        $this->assertStringStartsWith('https://accounts.google.com/', $location);
        $this->assertStringContainsString('client_id=test-client-id', $location);
        $this->assertStringContainsString('redirect_uri='.urlencode(route('auth.google.callback')), $location);
    }

    public function test_a_visitor_can_register_and_is_signed_in(): void
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Edrian',
            'email' => 'Edrian@Example.com',
            'password' => 'correct-horse-battery',
            'password_confirmation' => 'correct-horse-battery',
        ]);

        $response->assertRedirect(route('dashboard'));

        $user = User::query()->where('email', 'edrian@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('Edrian', $user->name);
        $this->assertTrue(Hash::check('correct-horse-battery', $user->password));

        // A brand-new account goes through profile onboarding first.
        $this->get(route('dashboard'))->assertRedirect(route('profile'));
    }

    public function test_registration_rejects_an_email_that_is_already_taken(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->post(route('register.store'), [
            'name' => 'Someone',
            'email' => 'TAKEN@example.com',
            'password' => 'correct-horse-battery',
            'password_confirmation' => 'correct-horse-battery',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertDatabaseCount('users', 1);
    }

    public function test_a_user_can_sign_in_with_their_password(): void
    {
        $user = User::factory()->onboarded()->create(['email' => 'edrian@example.com']);

        $this->post(route('login.store'), ['email' => 'Edrian@example.com', 'password' => 'password'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_sign_in_fails_with_a_wrong_password(): void
    {
        User::factory()->create(['email' => 'edrian@example.com']);

        $this->post(route('login.store'), ['email' => 'edrian@example.com', 'password' => 'nope'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_repeated_failed_sign_ins_are_throttled(): void
    {
        User::factory()->create(['email' => 'edrian@example.com']);

        foreach (range(1, 5) as $attempt) {
            $this->post(route('login.store'), ['email' => 'edrian@example.com', 'password' => 'nope']);
        }

        // Even the right password is refused while the lockout is active.
        $this->post(route('login.store'), ['email' => 'edrian@example.com', 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_a_user_can_log_out(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_google_sign_in_creates_an_account(): void
    {
        $this->fakeGoogleUser();

        $this->get(route('auth.google.callback'))->assertRedirect(route('dashboard'));

        $user = User::query()->where('email', 'lorie@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('google-123', $user->google_id);
        $this->assertSame('Lorie Tester', $user->name);
        $this->assertTrue($user->hasVerifiedEmail());
    }

    public function test_google_sign_in_recognises_a_returning_user(): void
    {
        $user = User::factory()->onboarded()->create(['email' => 'old-address@example.com']);
        $user->forceFill(['google_id' => 'google-123'])->save();

        // Matched on the Google ID, even though the Google email has since changed.
        $this->fakeGoogleUser(['email' => 'new-address@example.com']);

        $this->get(route('auth.google.callback'))->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_google_sign_in_links_a_password_account_with_the_same_email(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'lorie@example.com']);

        $this->fakeGoogleUser();

        $this->get(route('auth.google.callback'))->assertRedirect(route('dashboard'));

        $user->refresh();
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseCount('users', 1);
        $this->assertSame('google-123', $user->google_id);

        // The address was never verified, so the old password is retired.
        $this->assertFalse(Hash::check('password', $user->password));
        $this->assertTrue($user->hasVerifiedEmail());
    }

    public function test_google_sign_in_requires_a_verified_google_email(): void
    {
        $this->fakeGoogleUser(['email_verified' => false]);

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error');

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_a_cancelled_or_failed_google_sign_in_returns_to_login(): void
    {
        Exceptions::fake();

        Socialite::fake('google', fn () => throw new RuntimeException('access_denied'));

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error');

        $this->assertGuest();
        Exceptions::assertReported(RuntimeException::class);
    }
}
