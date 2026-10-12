<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\LegacyDataClaimer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as GoogleUser;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;
use Throwable;

class GoogleController extends Controller
{
    public function __construct(private readonly LegacyDataClaimer $legacyData) {}

    public function redirect(): SymfonyRedirectResponse
    {
        abort_unless(filled(config('services.google.client_id')), 404);

        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        abort_unless(filled(config('services.google.client_id')), 404);

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable $e) {
            // Cancelled consent, an expired state, or a Google outage.
            report($e);

            return $this->failed('Google sign-in was cancelled or failed. Please try again.');
        }

        if (blank($googleUser->getEmail()) || ! filter_var(Arr::get($googleUser->getRaw(), 'email_verified'), FILTER_VALIDATE_BOOLEAN)) {
            return $this->failed('Your Google account needs a verified email address to sign in.');
        }

        Auth::login($this->findOrCreateUser($googleUser), remember: true);

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    private function findOrCreateUser(GoogleUser $googleUser): User
    {
        $email = Str::lower($googleUser->getEmail());

        $user = User::query()->where('google_id', $googleUser->getId())->first()
            ?? User::query()->where('email', $email)->first();

        if ($user === null) {
            return $this->createUser($googleUser, $email);
        }

        if ($user->google_id === null) {
            $this->linkGoogle($user, $googleUser);
        }

        return $user;
    }

    private function createUser(GoogleUser $googleUser, string $email): User
    {
        $user = User::create([
            'name' => Str::limit($googleUser->getName() ?: Str::before($email, '@'), 30, ''),
            'email' => $email,
            // Google-only accounts never use this; it just keeps password login closed.
            'password' => Str::password(32),
        ]);

        $user->forceFill(['google_id' => $googleUser->getId(), 'email_verified_at' => now()])->save();

        $this->legacyData->claimFor($user);

        return $user;
    }

    /**
     * Attach Google to an account that was registered with a password.
     *
     * Registration doesn't verify email ownership, so the existing password may
     * have been set by someone who doesn't own this address. Google has now
     * proven who does — retire that password so only they can get in.
     */
    private function linkGoogle(User $user, GoogleUser $googleUser): void
    {
        $user->forceFill(['google_id' => $googleUser->getId()]);

        if (! $user->hasVerifiedEmail()) {
            $user->forceFill([
                'password' => Str::password(32),
                'remember_token' => Str::random(60),
                'email_verified_at' => now(),
            ]);
        }

        $user->save();
    }

    private function failed(string $message): RedirectResponse
    {
        return redirect()->route('login')->with('error', $message);
    }
}
