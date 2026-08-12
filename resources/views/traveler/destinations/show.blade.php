@extends('layouts.app')
@section('title', $destination->name)
@section('content')

<a href="{{ route('destinations.index') }}" class="btn btn-outline btn-sm mb-16">
    <i class="fa-solid fa-arrow-left"></i> All Destinations
</a>

{{-- Hero --}}
<div class="card mb-24" style="position:relative;overflow:hidden;min-height:180px;{{ $destination->image ? '' : 'background:linear-gradient(135deg,#5C2D0E,#C9A84C);' }}color:white;border:none;">
    @if ($destination->image)
    <img src="{{ asset('storage/' . $destination->image) }}" alt="{{ $destination->name }}"
         style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;">
    <div style="position:absolute;inset:0;background:linear-gradient(0deg, rgba(20,10,4,.75), rgba(20,10,4,.15));"></div>
    @endif
    <div style="position:relative;padding:28px;">
        @if ($destination->country)
        <span class="badge" style="background:rgba(255,255,255,0.2);color:white;margin-bottom:12px;">{{ $destination->country }}</span>
        @endif
        <h1 style="color:white;font-size:30px;margin-bottom:6px;">{{ $destination->name }}</h1>
        <div style="font-size:14px;opacity:0.9;">
            <i class="fa-solid fa-map-location-dot"></i> {{ $attractions->count() }} {{ Str::plural('attraction', $attractions->count()) }}
        </div>
    </div>
</div>

{{-- Description --}}
@if ($destination->description)
<div class="card mb-24">
    <h2 class="mb-12">About</h2>
    <p style="font-size:14px;line-height:1.7;color:var(--color-text-muted);">{{ $destination->description }}</p>
</div>
@endif

<div class="card mb-24" style="display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;">
    <div>
        <h2 style="margin-bottom:4px;">Ready to go?</h2>
        <p class="text-muted" style="margin:0;">Start planning a trip to {{ $destination->name }}.</p>
    </div>
    <a href="{{ route('trips.plan', ['to' => $destination->name]) }}" class="btn btn-primary">
        <i class="fa-solid fa-plane-departure"></i> Plan a Trip Here
    </a>
</div>

{{-- Attractions in this destination --}}
<h2 class="mb-16">Things to Do</h2>
@if ($attractions->isEmpty())
<div style="text-align:center;padding:28px 24px;">
    <p class="text-muted">No attractions listed for {{ $destination->name }} yet.</p>
</div>
@else
<div class="attraction-grid">
    @foreach ($attractions as $attraction)
    <a href="{{ route('attractions.show', $attraction) }}" style="text-decoration:none;color:inherit;">
        <div class="card">
            <div style="height:140px;background:var(--border-light);border-radius:10px 10px 0 0;overflow:hidden;display:flex;align-items:center;justify-content:center;font-size:36px;">
                @if ($attraction->image)
                <img src="{{ asset('storage/' . $attraction->image) }}"
                     style="width:100%;height:100%;object-fit:cover;" alt="{{ $attraction->name }}">
                @else
                🗺️
                @endif
            </div>
            <div class="card-body">
                <div style="font-size:14px;font-weight:600;">{{ $attraction->name }}</div>
                @if ($attraction->category)
                <span class="badge badge-primary mt-4">{{ $attraction->category }}</span>
                @endif
            </div>
        </div>
    </a>
    @endforeach
</div>
@endif

@endsection
