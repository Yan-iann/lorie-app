@extends('layouts.app')

@section('title', ($onboarding ? 'Welcome' : 'Profile').' · Calorie Tracker')

@php
    $hasMascot = file_exists(public_path('images/mascot.png'));
    $fieldClass = 'w-full rounded-xl border border-slate-300 bg-slate-50/50 px-4 py-2.5 text-sm font-medium shadow-sm outline-none transition focus:border-brand-500 focus:bg-white focus:ring-4 focus:ring-brand-500/15';
    $labelClass = 'mb-1.5 block text-xs font-semibold tracking-wide text-slate-400 uppercase';
@endphp

@section('content')
    @if ($onboarding)
        {{-- Mascot welcome --}}
        <div class="animate-rise mb-6">
            <div class="flex items-end gap-2 rounded-2xl bg-brand-600 px-3 py-3">
                @if ($hasMascot)
                    <img src="{{ asset('images/mascot.png') }}" alt="Lorie the mascot" class="-mt-9 -mb-3 h-36 w-auto shrink-0 self-end drop-shadow-lg sm:h-40">
                @else
                    <span class="grid size-20 shrink-0 place-items-center self-center rounded-2xl bg-white/20 text-white">
                        <x-icon name="leaf" class="size-9" />
                    </span>
                @endif
                <div class="flex-1 self-center rounded-2xl rounded-bl-md bg-white px-4 py-3 shadow-sm">
                    <p class="text-sm font-bold text-brand-600">Lorie</p>
                    <p class="mt-0.5 text-sm leading-snug text-slate-600">Kumusta! I'm Lorie. Let's set up your profile so I can tailor your daily calories.</p>
                </div>
            </div>
        </div>
        <h1 class="animate-rise text-2xl font-bold tracking-tight text-slate-900">Let's get you set up</h1>
        <p class="animate-rise mt-1 mb-6 text-sm text-slate-500">Tell me a bit about you and I'll suggest a daily calorie goal. You can change any of this later.</p>
    @else
        <div class="animate-rise mb-6 flex items-center gap-2.5">
            <span class="grid size-9 place-items-center rounded-xl bg-slate-100 text-slate-500">
                <x-icon name="settings" class="size-5" />
            </span>
            <h1 class="text-xl font-bold tracking-tight text-slate-900">Profile</h1>
        </div>
    @endif

    <form id="profile-form" method="POST" action="{{ route('profile.update') }}" class="animate-rise space-y-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        @csrf
        @method('PUT')

        {{-- Name --}}
        <div>
            <label for="display_name" class="{{ $labelClass }}">Your name</label>
            <input id="display_name" type="text" name="display_name" value="{{ old('display_name', $name) }}" maxlength="30" placeholder="What should I call you?" required class="{{ $fieldClass }}">
        </div>

        {{-- Gender --}}
        <div>
            <label for="gender" class="{{ $labelClass }}">Gender</label>
            <select id="gender" name="gender" required class="{{ $fieldClass }}">
                <option value="" disabled @selected(old('gender', $gender) === '')>Select…</option>
                <option value="male" @selected(old('gender', $gender) === 'male')>Male</option>
                <option value="female" @selected(old('gender', $gender) === 'female')>Female</option>
                <option value="other" @selected(old('gender', $gender) === 'other')>Rather not say</option>
            </select>
        </div>

        {{-- Height / Weight --}}
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label for="height_cm" class="{{ $labelClass }}">Height</label>
                <div class="relative">
                    <input id="height_cm" type="number" name="height_cm" value="{{ old('height_cm', $heightCm) }}" min="50" max="260" step="0.1" placeholder="—" class="{{ $fieldClass }} pr-9">
                    <span class="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-xs text-slate-400">cm</span>
                </div>
            </div>
            <div>
                <label for="weight_kg" class="{{ $labelClass }}">Weight</label>
                <div class="relative">
                    <input id="weight_kg" type="number" name="weight_kg" value="{{ old('weight_kg', $weightKg) }}" min="20" max="400" step="0.1" placeholder="—" class="{{ $fieldClass }} pr-9">
                    <span class="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-xs text-slate-400">kg</span>
                </div>
            </div>
        </div>

        {{-- Activity level --}}
        <div>
            <label for="activity_level" class="{{ $labelClass }}">Activity level</label>
            <select id="activity_level" name="activity_level" class="{{ $fieldClass }}">
                @php
                    $activityLabels = [
                        'sedentary' => 'Sedentary: little or no exercise',
                        'light' => 'Light: 1 to 3 days/week',
                        'moderate' => 'Moderate: 3 to 5 days/week',
                        'active' => 'Active: 6 to 7 days/week',
                        'athlete' => 'Athlete: hard daily training',
                    ];
                @endphp
                @foreach ($activityLabels as $value => $label)
                    <option value="{{ $value }}" @selected(old('activity_level', $activity) === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        {{-- Suggested goal --}}
        <div class="flex items-center justify-between gap-3 rounded-2xl bg-brand-50 px-4 py-3">
            <div>
                <p class="text-xs font-semibold tracking-wide text-brand-700 uppercase">Suggested daily goal</p>
                <p class="text-lg font-bold text-brand-700"><span id="goal-suggestion">—</span> <span class="text-sm font-medium text-brand-600/70">kcal</span></p>
            </div>
            <button type="button" id="use-suggestion" class="rounded-xl bg-white px-3 py-2 text-xs font-semibold text-brand-700 shadow-sm ring-1 ring-brand-200 transition hover:bg-brand-100 active:scale-95">
                Use this
            </button>
        </div>

        {{-- Daily goal --}}
        <div>
            <label for="daily_goal" class="{{ $labelClass }}">Daily calorie goal</label>
            <div class="relative">
                <input id="daily_goal" type="number" name="daily_goal" value="{{ old('daily_goal', $onboarding ? '' : $goal) }}" min="500" max="10000" step="10" required class="{{ $fieldClass }} pr-14">
                <span class="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-xs text-slate-400">kcal</span>
            </div>
        </div>

        <div class="flex items-center gap-3 pt-1">
            <button type="submit" class="flex-1 rounded-xl bg-brand-600 px-6 py-3 text-sm font-semibold text-white transition hover:bg-brand-700 active:scale-95">
                {{ $onboarding ? 'Get started' : 'Save profile' }}
            </button>
            @unless ($onboarding)
                <a href="{{ route('dashboard') }}" class="rounded-xl border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-600 shadow-sm transition hover:text-slate-900">Back</a>
            @endunless
        </div>
    </form>

    {{-- Account --}}
    <div class="animate-rise mt-5 flex items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white px-6 py-4 shadow-sm">
        <div class="min-w-0">
            <p class="text-xs font-semibold tracking-wide text-slate-400 uppercase">Signed in as</p>
            <p class="mt-0.5 truncate text-sm font-medium text-slate-700">{{ $email }}</p>
        </div>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-600 shadow-sm transition hover:text-rose-600 active:scale-95">
                Log out
            </button>
        </form>
    </div>
@endsection
