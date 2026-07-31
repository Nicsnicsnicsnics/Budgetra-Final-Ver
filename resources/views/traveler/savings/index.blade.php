@extends('layouts.app')
@section('title', 'Savings Goals')
@section('content')


@if (session('success'))
<div class="alert alert-success mb-16">{{ session('success') }}</div>
@endif

@if ($goals->isEmpty())
<div class="empty-state-center" style="min-height:80vh;">
    <div style="width:64px;height:64px;border-radius:16px;background:#934B19;display:flex;align-items:center;justify-content:center;margin-bottom:24px;">
        <i class="fa-solid fa-piggy-bank" style="font-size:28px;color:#fff;"></i>
    </div>
    @if (!auth()->user()?->userProfile)
    <h2 style="font-weight:700;font-size:22px;margin-bottom:10px;color:#1A0A00;">Set up your profile first</h2>
    <p style="color:#9B8EA0;margin-bottom:28px;font-size:14px;max-width:320px;line-height:1.6;">Complete your travel profile before planning a trip and setting savings goals.</p>
    <a href="{{ route('profile.setup') }}" style="display:inline-flex;align-items:center;gap:10px;background:#934B19;color:#fff;border-radius:30px;padding:14px 32px;font-size:13px;font-weight:700;letter-spacing:.06em;text-decoration:none;text-transform:uppercase;">
        <i class="fa-solid fa-user"></i> Set Up Your Profile First
    </a>
    @else
    <h2 style="font-weight:700;font-size:22px;margin-bottom:10px;color:#1A0A00;">No savings goals yet</h2>
    <p style="color:#9B8EA0;margin-bottom:28px;font-size:14px;max-width:320px;line-height:1.6;">Plan a trip first before adding your savings goals for your destinations.</p>
    <a href="{{ route('trips.plan') }}" style="display:inline-flex;align-items:center;gap:10px;background:#934B19;color:#fff;border-radius:30px;padding:14px 32px;font-size:13px;font-weight:700;letter-spacing:.06em;text-decoration:none;text-transform:uppercase;">
        <i class="fa-solid fa-plane"></i> Plan Your First Trip
    </a>
    @endif
</div>
@else
@php
    $isDraftGoal = function ($goal) {
        $trip = $goal->trip;
        return $trip && ($trip->status ?? null) === 'draft';
    };
    $isPastGoal = function ($goal) {
        $trip = $goal->trip;
        if (!$trip) return false;
        return ($trip->status ?? null) === 'past'
            || ($trip->end_date && $trip->end_date->lt(\Carbon\Carbon::today()));
    };
    $draftGoals  = $goals->filter($isDraftGoal);
    $pastGoals   = $goals->filter(fn($g) => !$isDraftGoal($g) && $isPastGoal($g));
    $activeGoals = $goals->reject(fn($g) => $isDraftGoal($g) || $isPastGoal($g));
    $sgGroups = [
        ['key' => 'active', 'label' => 'Active Goals', 'icon' => 'fa-solid fa-piggy-bank',        'items' => $activeGoals, 'noun' => 'active goals'],
        ['key' => 'draft',  'label' => 'Draft Goals',  'icon' => 'fa-regular fa-file-lines',      'items' => $draftGoals,  'noun' => 'draft goals'],
        ['key' => 'past',   'label' => 'Past Goals',   'icon' => 'fa-solid fa-clock-rotate-left', 'items' => $pastGoals,   'noun' => 'past goals'],
    ];
@endphp

@foreach ($sgGroups as $sgGroup)
<div x-data="{ open: false }" style="margin-bottom:20px;background:#fff;border:1.5px solid var(--border);border-radius:999px;transition:border-radius .15s,border-color .15s,box-shadow .15s;"
     :style="open ? 'border-radius:20px;border-color:#934B19;box-shadow:0 4px 16px rgba(147,75,25,.10);' : ''">
    <button @click="open=!open" type="button" style="width:100%;display:flex;align-items:center;justify-content:space-between;padding:12px 18px;background:none;border:none;cursor:pointer;">
        <div style="display:flex;align-items:center;gap:10px;">
            <div style="width:30px;height:30px;border-radius:50%;background:#FDF3EB;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="{{ $sgGroup['icon'] }}" style="color:#934B19;font-size:13px;"></i>
            </div>
            <span style="font-size:15px;font-weight:700;color:#1A0A00;">{{ $sgGroup['label'] }}</span>
            <span style="font-size:11px;font-weight:700;color:#934B19;background:#FDF3EB;border-radius:20px;padding:2px 10px;">{{ $sgGroup['items']->count() }}</span>
        </div>
        <i class="fa-solid fa-chevron-down" style="font-size:12px;color:var(--muted);transition:.2s;" :style="open?'transform:rotate(180deg)':''"></i>
    </button>
    <div x-show="open" x-transition style="padding:16px 18px 20px;border-top:1px solid var(--border);">
        @if ($sgGroup['items']->isEmpty())
        <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:40px 20px;">
            <div style="width:56px;height:56px;border-radius:16px;background:#934B19;display:flex;align-items:center;justify-content:center;margin-bottom:18px;">
                <i class="{{ $sgGroup['icon'] }}" style="font-size:24px;color:#fff;"></i>
            </div>
            <h3 style="font-weight:700;font-size:17px;margin:0 0 6px;color:#1A0A00;">No {{ $sgGroup['label'] }} yet</h3>
            <p style="color:#9B8EA0;font-size:13px;max-width:280px;line-height:1.6;margin:0;">Plan a trip first to see your {{ $sgGroup['noun'] }}.</p>
        </div>
        @else
        <div style="display:flex;flex-wrap:wrap;gap:20px;justify-content:center;align-items:flex-start;">
            @foreach ($sgGroup['items'] as $goal)
            @livewire('traveler.savings-goal-manager', ['goal' => $goal], key($goal->id))
            @endforeach
        </div>
        @endif
    </div>
</div>
@endforeach
@endif

@endsection
