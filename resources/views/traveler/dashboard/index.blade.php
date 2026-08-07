@extends('layouts.app')
@section('title', 'Dashboard')
@section('content')

{{-- Header --}}
<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;" class="mb-24">
    <div>
        <h1>Dashboard</h1>
        <p class="text-muted">Welcome back, {{ auth()->user()?->full_name ?? '' }}!</p>
    </div>
</div>

{{-- Aggregate stat cards --}}
<div class="stats-row mb-24">
    <div class="stat-card">
        <div class="stat-card-accent" style="background:var(--primary);"></div>
        <div class="stat-label"><i class="fa-solid fa-suitcase-rolling"></i> Total Trips</div>
        <div class="stat-value">{{ $trips->count() }}</div>
        <div class="stat-sub" style="color:var(--primary);">All time</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-accent" style="background:var(--secondary);"></div>
        <div class="stat-label"><i class="fa-solid fa-coins"></i> Total Budget</div>
        <div class="stat-value" style="color:var(--secondary);">{{ currency_symbol() }}{{ number_format($totalBudget, 0) }}</div>
        <div class="stat-sub">Across all trips</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-accent" style="background:var(--tertiary);"></div>
        <div class="stat-label"><i class="fa-regular fa-credit-card"></i> Total Spent</div>
        <div class="stat-value" style="color:var(--tertiary);">{{ currency_symbol() }}{{ number_format($totalSpent, 0) }}</div>
        <div class="stat-sub">Across all trips</div>
    </div>
</div>

{{-- Trips accordion --}}
@php
    $activeTrips = $trips->whereIn('status', ['upcoming', 'active']);
    $pastTrips   = $trips->where('status', 'past');
    $dashGroups = [
        ['key' => 'active', 'label' => 'Active Trips', 'icon' => 'fa-solid fa-suitcase-rolling',  'items' => $activeTrips],
        ['key' => 'past',   'label' => 'Past Trips',   'icon' => 'fa-solid fa-clock-rotate-left', 'items' => $pastTrips],
    ];
@endphp
@if ($trips->isNotEmpty())
@foreach ($dashGroups as $dashGroup)
<div x-data="{ open: {{ $dashGroup['key'] === 'active' ? 'true' : 'false' }} }" style="margin-bottom:20px;background:var(--bg-white);border:1.5px solid var(--border);border-radius:999px;transition:border-radius .2s,border-color .2s;"
     :style="open ? 'border-radius:22px;border-color:var(--primary);' : ''">
    <button @click="open=!open" type="button" style="width:100%;display:flex;align-items:center;justify-content:space-between;padding:14px 20px;background:none;border:none;cursor:pointer;">
        <div style="display:flex;align-items:center;gap:12px;">
            <div style="width:32px;height:32px;border-radius:10px;background:var(--primary);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="{{ $dashGroup['icon'] }}" style="color:#fff;font-size:13px;"></i>
            </div>
            <span style="font-size:15px;font-weight:700;color:var(--dark);">{{ $dashGroup['label'] }}</span>
            @php $dashCount = $dashGroup['items']->count(); @endphp
            <span style="font-size:11px;font-weight:800;{{ $dashCount > 0 ? 'color:#fff;background:var(--primary);' : 'color:var(--muted);background:var(--bg);' }}border-radius:99px;min-width:22px;height:22px;padding:0 7px;display:inline-flex;align-items:center;justify-content:center;line-height:1;">{{ $dashCount }}</span>
        </div>
        <i class="fa-solid fa-chevron-down" style="font-size:12px;color:var(--muted);transition:.2s;" :style="open?'transform:rotate(180deg)':''"></i>
    </button>
    <div x-show="open" x-transition style="padding:16px 18px 20px;border-top:1px solid var(--border);">
        @if ($dashGroup['items']->isEmpty())
        <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:40px 20px;">
            <div style="width:56px;height:56px;border-radius:16px;background:var(--primary);display:flex;align-items:center;justify-content:center;margin-bottom:18px;">
                <i class="{{ $dashGroup['icon'] }}" style="font-size:24px;color:#fff;"></i>
            </div>
            <h3 style="font-weight:700;font-size:17px;margin:0 0 6px;color:var(--dark);">No {{ $dashGroup['label'] }} yet</h3>
            <p style="color:var(--muted);font-size:13px;max-width:280px;line-height:1.6;margin:0;">
                @if ($dashGroup['key'] === 'active')
                Plan a trip to see it appear here.
                @else
                Trips move here automatically once their end date has passed.
                @endif
            </p>
        </div>
        @else
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:20px;">
            @foreach ($dashGroup['items'] as $trip)
            @php
                $spent   = $trip->total_spent ?? 0;
                $pct     = $trip->budget_limit > 0 ? min(100, round($spent / $trip->budget_limit * 100)) : 0;
                $tType   = strtoupper($trip->travel_type ?? 'Solo');
                $typeColor = $tType === 'GROUP' ? '#A855F7' : '#14B8A6';
            @endphp
            <div style="background:var(--bg-white);border:1.5px solid var(--border);border-radius:14px;overflow:hidden;">
                <div style="position:relative;height:150px;background:linear-gradient(135deg,var(--primary),#C8874A);">
                    @if ($trip->cover_image)
                    <img src="{{ $trip->cover_image }}" style="width:100%;height:100%;object-fit:cover;display:block;" alt="{{ $trip->destination }}">
                    @endif
                    <div style="position:absolute;inset:0;background:linear-gradient(to bottom,rgba(0,0,0,0.05),rgba(0,0,0,0.35));"></div>
                    <span style="position:absolute;top:12px;left:12px;background:{{ $typeColor }};color:#fff;font-size:11px;font-weight:700;padding:4px 12px;border-radius:20px;letter-spacing:.4px;">
                        {{ $tType }}
                    </span>
                </div>
                <div style="padding:18px 20px;">
                    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:10px;margin-bottom:2px;">
                        <div style="font-size:16px;font-weight:700;color:var(--dark);">{{ $trip->trip_name ?? $trip->destination }}</div>
                        <div style="color:var(--muted);font-size:12px;white-space:nowrap;">{{ $trip->start_date->format('M j') }} - {{ $trip->end_date->format('M j, Y') }}</div>
                    </div>
                    <div style="color:var(--muted);font-size:13px;margin-bottom:16px;">
                        <i class="fa-solid fa-plane" style="font-size:11px;"></i> {{ $trip->origin_code ?? 'MNL' }} &rarr; {{ $trip->destination_code ?? '—' }}
                    </div>

                    <div style="display:flex;justify-content:space-between;align-items:baseline;margin-bottom:6px;">
                        <span style="color:var(--muted);font-size:12px;">Budget Used</span>
                        <span style="font-weight:700;font-size:13px;color:var(--dark);">{{ $pct }}%</span>
                    </div>
                    <div style="height:6px;background:var(--border-light);border-radius:99px;overflow:hidden;margin-bottom:12px;">
                        <div style="height:100%;width:{{ $pct }}%;background:{{ $spent > $trip->budget_limit && $trip->budget_limit > 0 ? 'var(--danger)' : 'var(--primary)' }};border-radius:99px;"></div>
                    </div>
                    <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:18px;">
                        <span style="color:var(--dark);">Spent: <strong style="color:var(--primary);">{{ currency_symbol() }}{{ number_format($spent, 2) }}</strong></span>
                        <span style="color:var(--muted);">Allocated Budget: {{ currency_symbol() }}{{ number_format($trip->budget_limit, 2) }}</span>
                    </div>

                    <div style="display:flex;gap:10px;">
                        <a href="{{ route('trips.dashboard', $trip) }}" class="btn btn-secondary" style="flex:1;text-align:center;text-transform:uppercase;letter-spacing:.05em;font-size:12px;">Details</a>
                        <a href="{{ route('multi-trips.index') }}" class="btn btn-primary" style="flex:1;text-align:center;text-transform:uppercase;letter-spacing:.05em;font-size:12px;">Compare</a>
                    </div>

                    <form id="delete-trip-form-{{ $trip->id }}" method="POST" action="{{ route('trips.destroy', $trip) }}" style="display:none;">
                        @csrf
                        @method('DELETE')
                    </form>
                    <button type="button" class="js-delete-trip-btn"
                            data-form="delete-trip-form-{{ $trip->id }}"
                            data-name="{{ $trip->trip_name ?? $trip->destination }}"
                            style="width:100%;margin-top:10px;background:none;border:none;padding:4px 0 0;font-size:12px;font-weight:600;color:var(--danger);cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px;" title="Delete trip">
                        <i class="fa-regular fa-trash-can"></i> Delete trip
                    </button>
                </div>
            </div>
            @endforeach

            @if ($dashGroup['key'] === 'active')
            {{-- Plan New Adventure card --}}
            <a href="{{ route('trips.plan') }}"
               style="background:transparent;border:2px dashed var(--border);border-radius:14px;padding:20px;
                      display:flex;flex-direction:column;align-items:center;justify-content:center;gap:10px;
                      min-height:160px;text-decoration:none;transition:border-color 0.15s;"
               onmouseover="this.style.borderColor='var(--primary)'"
               onmouseout="this.style.borderColor='var(--border)'">
                <div style="width:44px;height:44px;border-radius:50%;background:var(--border-light);display:flex;align-items:center;justify-content:center;">
                    <i class="fa-solid fa-plus" style="font-size:20px;color:var(--muted);"></i>
                </div>
                <div style="font-size:14px;font-weight:600;color:var(--muted);">Plan New Adventure</div>
                <div style="font-size:12px;color:var(--muted);text-align:center;">Start tracking your next destination today.</div>
            </a>
            @endif
        </div>
        @endif
    </div>
</div>
@endforeach
@elseif ($trips->isEmpty())
{{-- Empty state for brand-new users with no trips yet --}}
<div style="display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;min-height:50vh;">
    <div style="width:64px;height:64px;border-radius:16px;background:var(--primary);display:flex;align-items:center;justify-content:center;margin-bottom:24px;">
        <i class="fa-solid fa-plane" style="font-size:28px;color:#fff;"></i>
    </div>
    @if (!auth()->user()?->userProfile)
    <h2 style="font-weight:700;font-size:22px;margin-bottom:10px;color:var(--dark);">Let's get you set up</h2>
    <p style="color:var(--muted);margin-bottom:28px;font-size:14px;max-width:320px;line-height:1.6;">Complete your travel profile so we can tailor budget suggestions before you plan your first trip.</p>
    <a href="{{ route('profile.setup') }}" style="display:inline-flex;align-items:center;gap:10px;background:var(--primary);color:#fff;border-radius:30px;padding:14px 32px;font-size:13px;font-weight:700;letter-spacing:.06em;text-decoration:none;text-transform:uppercase;">
        <i class="fa-solid fa-user"></i> Set Up Your Profile First
    </a>
    @else
    <h2 style="font-weight:700;font-size:22px;margin-bottom:10px;color:var(--dark);">No trips yet</h2>
    <p style="color:var(--muted);margin-bottom:28px;font-size:14px;max-width:320px;line-height:1.6;">Your profile is all set! Plan your first trip to start tracking your budget and itinerary.</p>
    <a href="{{ route('trips.plan') }}" style="display:inline-flex;align-items:center;gap:10px;background:var(--primary);color:#fff;border-radius:30px;padding:14px 32px;font-size:13px;font-weight:700;letter-spacing:.06em;text-decoration:none;text-transform:uppercase;">
        <i class="fa-solid fa-plane"></i> Plan Your First Trip
    </a>
    @endif
</div>
@endif

{{-- Recommended destinations --}}
@if ($recommended->isNotEmpty())
<div style="display:flex;align-items:center;justify-content:space-between;" class="mb-16">
    <h2 style="margin:0;">Places You May Like</h2>
    <a href="{{ route('attractions.index') }}" style="font-size:13px;font-weight:600;color:var(--primary);text-decoration:none;display:flex;align-items:center;gap:4px;">
        More <i class="fa-solid fa-chevron-right" style="font-size:11px;"></i>
    </a>
</div>
<div class="attraction-grid mb-24">
    @foreach ($recommended as $attraction)
    <a href="{{ route('attractions.show', $attraction) }}" style="text-decoration:none;color:inherit;">
        <div class="card" style="overflow:hidden;border-radius:14px;">
            <div style="position:relative;height:220px;background:linear-gradient(135deg,var(--primary),#C8874A);">
                @if ($attraction->image)
                <img src="{{ asset('storage/' . $attraction->image) }}"
                     style="width:100%;height:100%;object-fit:cover;display:block;" alt="{{ $attraction->name }}">
                @endif
                <div style="position:absolute;inset:0;background:linear-gradient(to bottom,rgba(0,0,0,0.05) 40%,rgba(0,0,0,0.55));"></div>

                <span style="position:absolute;top:12px;left:12px;background:#FDF3EB;color:var(--primary);font-size:11px;font-weight:700;padding:4px 10px;border-radius:20px;">
                    {{ $attraction->category }}
                </span>

                <div style="position:absolute;bottom:12px;left:12px;display:flex;gap:6px;">
                    <span style="background:rgba(255,255,255,0.92);color:#1A0A00;font-size:11px;font-weight:600;padding:3px 10px;border-radius:20px;">
                        <i class="fa-solid fa-location-dot" style="font-size:9px;color:var(--primary);"></i> {{ $attraction->destination }}
                    </span>
                    <span style="background:rgba(0,0,0,0.5);color:#fff;font-size:11px;font-weight:700;padding:3px 10px;border-radius:20px;">
                        <i class="fa-solid fa-fire" style="font-size:9px;color:#F5A623;"></i> {{ number_format($attraction->rating, 1) }}
                    </span>
                </div>
            </div>
            <div class="card-body" style="padding:14px 16px;">
                <div style="font-size:15px;font-weight:700;color:var(--dark);margin-bottom:4px;">{{ $attraction->name }}</div>
                <div style="display:flex;align-items:center;gap:6px;">
                    <span class="stars">{{ str_repeat('★', round($attraction->rating)) }}{{ str_repeat('☆', 5 - round($attraction->rating)) }}</span>
                    <span class="text-muted" style="font-size:12px;">{{ number_format($attraction->rating, 1) }} · {{ number_format($attraction->reviews_count) }} {{ $attraction->reviews_count == 1 ? 'review' : 'reviews' }}</span>
                </div>
            </div>
        </div>
    </a>
    @endforeach
</div>
@endif

{{-- Delete Trip Confirmation Modal --}}
<div id="deleteTripModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:2100;align-items:center;justify-content:center;padding:20px;">
    <div style="background:var(--bg-white);border-radius:20px;width:100%;max-width:360px;box-shadow:0 20px 60px rgba(0,0,0,0.2);overflow:hidden;">
        <div style="background:#FEF2F2;padding:28px 24px 20px;text-align:center;">
            <div style="width:52px;height:52px;border-radius:50%;background:#FEE2E2;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;">
                <i class="fa-solid fa-trash-can" style="font-size:22px;color:#DC2626;"></i>
            </div>
            <div style="font-size:17px;font-weight:700;color:var(--dark);margin-bottom:6px;">Delete Trip?</div>
            <div style="font-size:13px;color:var(--muted);line-height:1.5;">
                <strong id="deleteTripModalName"></strong> will be permanently deleted.<br>This action cannot be undone.
            </div>
        </div>
        <div style="display:flex;gap:10px;padding:18px 20px;">
            <button type="button" onclick="closeDeleteTripModal()"
                    style="flex:1;background:transparent;color:var(--muted);border:1.5px solid var(--border);border-radius:10px;padding:11px 0;font-size:13px;font-weight:600;cursor:pointer;">
                Cancel
            </button>
            <button type="button" onclick="submitDeleteTripModal()"
                    style="flex:1;background:#DC2626;color:#fff;border:none;border-radius:10px;padding:11px 0;font-size:13px;font-weight:600;cursor:pointer;">
                Delete
            </button>
        </div>
    </div>
</div>

{{-- Footer --}}
<div class="dash-footer">
    <a href="mailto:Budgetra@gmail.com" class="dash-footer-link">Contact us</a>
    <a href="#" class="dash-footer-link" onclick="document.getElementById('aboutModal').style.display='flex';return false;">About</a>
</div>

<div id="aboutModal" class="footer-modal-backdrop" style="display:none;" onclick="if(event.target===this)this.style.display='none';">
    <div class="footer-modal-card">
        <h3 style="margin:0 0 10px;font-size:18px;font-weight:800;color:var(--dark);">About Budgetra</h3>
        <p style="margin:0 0 18px;font-size:14px;color:var(--muted);line-height:1.6;">
            Budgetra helps Filipino travelers plan smarter trips — budgeting, itineraries, and AI-powered trip suggestions all in one place, so you can spend less time guessing and more time traveling.
        </p>
        <button type="button" class="btn btn-primary" style="width:100%;" onclick="document.getElementById('aboutModal').style.display='none';">Close</button>
    </div>
</div>

<script>
    let deleteTripFormId = null;

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.js-delete-trip-btn');
        if (!btn) return;
        deleteTripFormId = btn.dataset.form;
        document.getElementById('deleteTripModalName').textContent = btn.dataset.name;
        document.getElementById('deleteTripModal').style.display = 'flex';
    });

    function closeDeleteTripModal() {
        deleteTripFormId = null;
        document.getElementById('deleteTripModal').style.display = 'none';
    }

    function submitDeleteTripModal() {
        if (deleteTripFormId) document.getElementById(deleteTripFormId).submit();
    }
</script>

@endsection
