@extends('layouts.app')
@section('title', 'Savings Goals')
@section('content')

<div class="mb-24">
    @if ($goals->isNotEmpty())
    <div style="font-size:11px;font-weight:700;letter-spacing:.1em;color:var(--primary);text-transform:uppercase;margin-bottom:4px;">SAVINGS GOAL</div>
    @endif
</div>

@if (session('success'))
<div class="alert alert-success mb-16">{{ session('success') }}</div>
@endif

@if ($goals->isEmpty())
<div class="empty-state-center" style="min-height:80vh;">
    <div style="width:64px;height:64px;border-radius:16px;background:#934B19;display:flex;align-items:center;justify-content:center;margin-bottom:24px;">
        <i class="fa-solid fa-piggy-bank" style="font-size:28px;color:#fff;"></i>
    </div>
    <h2 style="font-weight:700;font-size:22px;margin-bottom:10px;color:#1A0A00;">No savings goals yet</h2>
    <p style="color:#9B8EA0;margin-bottom:28px;font-size:14px;max-width:320px;line-height:1.6;">Plan a trip first before adding your savings goals for your destinations.</p>
    <a href="{{ route('trips.plan') }}" style="display:inline-flex;align-items:center;gap:10px;background:#934B19;color:#fff;border-radius:30px;padding:14px 32px;font-size:13px;font-weight:700;letter-spacing:.06em;text-decoration:none;text-transform:uppercase;">
        <i class="fa-solid fa-plane"></i> Plan Your First Trip
    </a>
</div>
@else
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;align-items:stretch;">
    @foreach ($goals as $goal)
    @livewire('traveler.savings-goal-manager', ['goal' => $goal], key($goal->id))
    @endforeach
</div>
@endif

@endsection
