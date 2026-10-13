@extends('layouts.app')

@section('title', 'Create account · Calorie Tracker')

@php
    $fieldClass = 'w-full rounded-xl border border-slate-300 bg-slate-50/50 px-4 py-2.5 text-sm font-medium shadow-sm outline-none transition focus:border-brand-600 focus:bg-white focus:ring-4 focus:ring-brand-600/15';
    $labelClass = 'mb-1.5 block text-xs font-semibold tracking-wide text-slate-400 uppercase';
    $googleEnabled = filled(config('services.google.client_id'));
@endphp

@section('content')
    <div class="mx-auto max-w-md">
        <x-mascot-message class="mt-6 mb-6">Kumusta! I'm Lorie. Create an account and I'll keep your food log just for you.</x-mascot-message>

        <h1 class="animate-rise text-2xl font-bold tracking-tight text-slate-900">Create your account</h1>
        <p class="animate-rise mt-1 mb-6 text-sm text-slate-500">It takes a minute. Your entries stay private to you.</p>

        <div class="animate-rise rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            @if ($googleEnabled)
                <x-google-button>Sign up with Google</x-google-button>

                <div class="my-5 flex items-center gap-3 text-xs font-medium text-slate-400">
                    <span class="h-px flex-1 bg-slate-200"></span>
                    OR
                    <span class="h-px flex-1 bg-slate-200"></span>
                </div>
            @endif

            <form method="POST" action="{{ route('register.store') }}" class="flex flex-col gap-5">
                @csrf

                <div>
                    <label for="name" class="{{ $labelClass }}">Your name</label>
                    <input id="name" type="text" name="name" value="{{ old('name') }}" maxlength="30" autocomplete="given-name" placeholder="What should I call you?" required autofocus class="{{ $fieldClass }}">
                </div>

                <div>
                    <label for="email" class="{{ $labelClass }}">Email</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" required class="{{ $fieldClass }}">
                </div>

                <div>
                    <label for="password" class="{{ $labelClass }}">Password</label>
                    <input id="password" type="password" name="password" autocomplete="new-password" minlength="8" required class="{{ $fieldClass }}">
                    <p class="mt-1.5 text-xs text-slate-400">At least 8 characters.</p>
                </div>

                <div>
                    <label for="password_confirmation" class="{{ $labelClass }}">Confirm password</label>
                    <input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" minlength="8" required class="{{ $fieldClass }}">
                </div>

                <button type="submit" class="rounded-xl bg-brand-600 px-6 py-3 text-sm font-semibold text-white transition hover:bg-brand-700 active:scale-95">
                    Create account
                </button>
            </form>
        </div>

        <p class="mt-5 text-center text-sm text-slate-500">
            Already have an account?
            <a href="{{ route('login') }}" class="font-semibold text-brand-600 hover:underline">Sign in</a>
        </p>
    </div>
@endsection
