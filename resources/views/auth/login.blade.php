@extends('layouts.app')

@section('title', 'Sign in · Calorie Tracker')

@php
    $fieldClass = 'w-full rounded-xl border border-slate-300 bg-slate-50/50 px-4 py-2.5 text-sm font-medium shadow-sm outline-none transition focus:border-brand-600 focus:bg-white focus:ring-4 focus:ring-brand-600/15';
    $labelClass = 'mb-1.5 block text-xs font-semibold tracking-wide text-slate-400 uppercase';
    $googleEnabled = filled(config('services.google.client_id'));
@endphp

@section('content')
    <div class="mx-auto max-w-md">
        <x-mascot-message class="mt-6 mb-6">Welcome back! Sign in and let's pick up where you left off.</x-mascot-message>

        <h1 class="animate-rise text-2xl font-bold tracking-tight text-slate-900">Sign in</h1>
        <p class="animate-rise mt-1 mb-6 text-sm text-slate-500">Your food log and goals are saved to your account.</p>

        <div class="animate-rise rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            @if ($googleEnabled)
                <x-google-button>Continue with Google</x-google-button>

                <div class="my-5 flex items-center gap-3 text-xs font-medium text-slate-400">
                    <span class="h-px flex-1 bg-slate-200"></span>
                    OR
                    <span class="h-px flex-1 bg-slate-200"></span>
                </div>
            @endif

            <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-5">
                @csrf

                <div>
                    <label for="email" class="{{ $labelClass }}">Email</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" required autofocus class="{{ $fieldClass }}">
                </div>

                <div>
                    <label for="password" class="{{ $labelClass }}">Password</label>
                    <input id="password" type="password" name="password" autocomplete="current-password" required class="{{ $fieldClass }}">
                </div>

                <label class="flex items-center gap-2 text-sm font-medium text-slate-600">
                    <input type="checkbox" name="remember" value="1" checked class="size-4 rounded border-slate-300 accent-brand-600">
                    Keep me signed in
                </label>

                <button type="submit" class="rounded-xl bg-brand-600 px-6 py-3 text-sm font-semibold text-white transition hover:bg-brand-700 active:scale-95">
                    Sign in
                </button>
            </form>
        </div>

        <p class="mt-5 text-center text-sm text-slate-500">
            New here?
            <a href="{{ route('register') }}" class="font-semibold text-brand-600 hover:underline">Create an account</a>
        </p>
    </div>
@endsection
