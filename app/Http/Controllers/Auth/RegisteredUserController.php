<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Services\LegacyDataClaimer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(RegisterRequest $request, LegacyDataClaimer $legacyData): RedirectResponse
    {
        $user = User::create($request->safe()->only(['name', 'email', 'password']));

        $legacyData->claimFor($user);

        Auth::login($user, remember: true);

        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }
}
