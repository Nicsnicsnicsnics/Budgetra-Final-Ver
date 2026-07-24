@extends('layouts.app')
@section('title', 'Expenses')

@push('styles')
<style>
    .expense-dest-link { transition: background .15s ease, border-color .15s ease; }
    .expense-filter-select { transition: border-color .2s; }

    /* ── Transaction cards ─────────────────────────────────── */
    .txn-card {
        display: flex; align-items: center; gap: 14px; padding: 14px 16px;
        border-bottom: 1px solid var(--border-light); transition: background .15s ease;
    }
    .txn-card:last-child { border-bottom: none; }
    .txn-card:hover { background: var(--bg); }
    .txn-icon { width: 40px; height: 40px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 15px; flex-shrink: 0; }
    .txn-main { flex: 1; min-width: 0; }
    .txn-desc { font-size: 14px; font-weight: 600; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .txn-meta { font-size: 12px; color: var(--muted); margin-top: 2px; display: flex; align-items: center; gap: 6px; }
    .txn-amount { font-size: 15px; font-weight: 700; color: var(--primary); flex-shrink: 0; font-variant-numeric: tabular-nums; }
    .txn-actions { display: flex; gap: 4px; flex-shrink: 0; }
    .txn-action-btn {
        width: 32px; height: 32px; border-radius: 8px; border: 1.5px solid var(--border); background: #fff;
        color: var(--muted); cursor: pointer; display: flex; align-items: center; justify-content: center;
        font-size: 12px; transition: transform .12s ease, border-color .15s ease, color .15s ease;
    }
    .txn-action-btn:hover { transform: translateY(-1px); border-color: var(--primary); color: var(--primary); }
    .txn-action-danger:hover { border-color: var(--danger); color: var(--danger); }
    @media (max-width: 560px) {
        .txn-meta { flex-wrap: wrap; }
        .txn-desc { white-space: normal; }
    }

    /* ── Floating Add Expense button ──────────────────────── */
    .expense-fab {
        position: fixed; bottom: 28px; right: 28px; width: 56px; height: 56px; border-radius: 50%;
        background: var(--primary); color: #fff; border: none; box-shadow: var(--shadow-lg);
        font-size: 20px; cursor: pointer; z-index: 500; display: flex; align-items: center; justify-content: center;
        transition: transform .2s ease, background .2s ease;
    }
    .expense-fab:hover { transform: scale(1.08); background: var(--primary-dark); }
    @media (max-width: 640px) { .expense-fab { bottom: 20px; right: 20px; width: 50px; height: 50px; font-size: 18px; } }
</style>
@endpush

@section('content')

@if (session('success'))
<div class="alert alert-success">{{ session('success') }}</div>
@endif

@if ($trips->isEmpty())
{{-- No trips empty state --}}
<div class="empty-state-center" style="min-height:80vh;">
    <div style="width:64px;height:64px;border-radius:16px;background:var(--primary);display:flex;align-items:center;justify-content:center;margin-bottom:24px;">
        <i class="fa-solid fa-receipt" style="font-size:28px;color:#fff;"></i>
    </div>
    @if (!auth()->user()?->userProfile)
    <h2 style="font-weight:700;font-size:22px;margin-bottom:10px;">Set up your profile first</h2>
    <p class="text-muted" style="margin-bottom:28px;font-size:14px;max-width:320px;line-height:1.6;">Complete your travel profile before planning a trip and logging expenses.</p>
    <a href="{{ route('profile.setup') }}" class="btn btn-primary btn-lg">
        <i class="fa-solid fa-user"></i> Set Up Your Profile First
    </a>
    @else
    <h2 style="font-weight:700;font-size:22px;margin-bottom:10px;">No expenses yet</h2>
    <p class="text-muted" style="margin-bottom:28px;font-size:14px;max-width:320px;line-height:1.6;">Plan a trip first before logging your expenses.</p>
    <a href="{{ route('trips.plan') }}" class="btn btn-primary btn-lg">
        <i class="fa-solid fa-plane"></i> Plan Your First Trip
    </a>
    @endif
</div>

@else
@php
    $selectedTripId = request('trip_id') ?? $trips->first()?->id;
    $selectedTrip   = $selectedTripId ? $trips->firstWhere('id', $selectedTripId) : null;
    $iataToCity = [
        'MNL'=>'Manila','CEB'=>'Cebu','IAO'=>'Siargao','PPS'=>'Puerto Princesa',
        'DVO'=>'Davao','ILO'=>'Iloilo','BCD'=>'Bacolod','TAG'=>'Tagbilaran',
        'GES'=>'General Santos','CBO'=>'Cotabato','ZAM'=>'Zamboanga',
        'KLO'=>'Kalibo','MPH'=>'Boracay','RXS'=>'Roxas','TAC'=>'Tacloban',
        'SIN'=>'Singapore','KUL'=>'Kuala Lumpur','BKK'=>'Bangkok','HKG'=>'Hong Kong',
        'NRT'=>'Tokyo','ICN'=>'Seoul','HND'=>'Tokyo','KIX'=>'Osaka',
        'SYD'=>'Sydney','MEL'=>'Melbourne','LAX'=>'Los Angeles','JFK'=>'New York',
        'DXB'=>'Dubai','CDG'=>'Paris','LHR'=>'London','FCO'=>'Rome',
        'BCN'=>'Barcelona','AMS'=>'Amsterdam','HAN'=>'Hanoi','SGN'=>'Ho Chi Minh',
        'DPS'=>'Bali','CGK'=>'Jakarta','MLE'=>'Maldives',
    ];
    $tripLabel = fn($t) => ($t->origin ?? 'Manila') . ' to ' . ($iataToCity[$t->destination_code ?? ''] ?? $t->destination);

    // Validated categorical palette (dataviz skill: 6 slots, fixed order,
    // CVD-safe). Used for the category icon badge on each transaction card.
    $categoryIcons = [
        'Transportation' => 'fa-plane', 'Accommodation' => 'fa-bed', 'Food' => 'fa-utensils',
        'Activities' => 'fa-camera-retro', 'Shopping' => 'fa-bag-shopping', 'Emergency Expenses' => 'fa-shield-halved',
    ];
    $categoryColors = [
        'Transportation'     => '#2a78d6',
        'Accommodation'      => '#eb6834',
        'Food'               => '#1baf7a',
        'Activities'         => '#eda100',
        'Shopping'           => '#e87ba4',
        'Emergency Expenses' => '#008300',
    ];

    // Preserve active filters when switching destination.
    $filterParams = request()->only(['category', 'date_from', 'date_to']);
@endphp

<div style="max-width:960px;margin:0 auto;width:100%;">

    {{-- Header: destination selector --}}
    <div style="margin-bottom:20px;">
        <div style="font-size:10px;font-weight:700;letter-spacing:.1em;color:var(--primary);text-transform:uppercase;margin-bottom:6px;">Destination</div>
        @if ($trips->count() === 1)
        @php $t = $trips->first(); @endphp
        <div class="card" style="padding:13px 16px;display:flex;align-items:center;gap:10px;">
            <i class="fa-solid fa-plane" style="color:var(--primary);font-size:13px;flex-shrink:0;"></i>
            <span style="font-size:14px;font-weight:600;">{{ $tripLabel($t) }}</span>
        </div>
        @else
        <div x-data="{ open: false }" style="position:relative;">
            <button @click="open = !open" @click.away="open = false" type="button"
                    class="card" style="width:100%;padding:13px 16px;display:flex;align-items:center;gap:10px;cursor:pointer;text-align:left;border:1.5px solid var(--border);">
                <i class="fa-solid fa-plane" style="color:var(--primary);font-size:13px;flex-shrink:0;"></i>
                <span style="flex:1;font-size:14px;font-weight:600;">
                    @php $sel = $trips->firstWhere('id', $selectedTripId); @endphp
                    {{ $sel ? $tripLabel($sel) : 'Select a trip' }}
                </span>
                <i class="fa-solid fa-chevron-down" style="font-size:11px;color:var(--muted);transition:transform .2s;" :style="open ? 'transform:rotate(180deg)' : ''"></i>
            </button>
            <div x-show="open" x-transition
                 class="card" style="position:absolute;top:calc(100% + 6px);left:0;right:0;z-index:50;overflow:hidden;">
                @foreach($trips as $t)
                <a href="{{ route('expenses.index') }}?{{ http_build_query(array_merge($filterParams, ['trip_id' => $t->id])) }}"
                   class="expense-dest-link"
                   style="display:flex;align-items:center;gap:10px;padding:12px 16px;text-decoration:none;background:{{ $selectedTripId == $t->id ? 'var(--primary-light)' : '#fff' }};border-bottom:1px solid var(--border-light);">
                    <i class="fa-solid fa-plane" style="color:var(--primary);font-size:12px;flex-shrink:0;"></i>
                    <span style="font-size:13px;font-weight:{{ $selectedTripId == $t->id ? '700' : '500' }};color:{{ $selectedTripId == $t->id ? 'var(--primary)' : 'var(--text)' }};">{{ $tripLabel($t) }}</span>
                </a>
                @endforeach
            </div>
        </div>
        @endif
    </div>

    {{-- Filters --}}
    <div class="card mb-16"><div class="card-body" style="padding:16px 20px;">
        <form method="GET" action="{{ route('expenses.index') }}" style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;">
            <input type="hidden" name="trip_id" value="{{ $selectedTripId }}">
            <div style="flex:1;min-width:150px;">
                <label class="form-label">Category</label>
                <select name="category" class="form-control expense-filter-select" onchange="this.form.submit()">
                    <option value="">All Categories</option>
                    @foreach ($categories as $cat)
                    <option value="{{ $cat }}" {{ request('category') === $cat ? 'selected' : '' }}>{{ $cat }}</option>
                    @endforeach
                </select>
            </div>
            <div style="flex:1;min-width:150px;">
                <label class="form-label">From</label>
                <input type="date" name="date_from" class="form-control expense-filter-select" value="{{ request('date_from') }}" onchange="this.form.submit()">
            </div>
            <div style="flex:1;min-width:150px;">
                <label class="form-label">To</label>
                <input type="date" name="date_to" class="form-control expense-filter-select" value="{{ request('date_to') }}" onchange="this.form.submit()">
            </div>
            @if (request('category') || request('date_from') || request('date_to'))
            <a href="{{ route('expenses.index') }}?trip_id={{ $selectedTripId }}" class="btn btn-outline btn-sm">
                <i class="fa-solid fa-xmark"></i> Clear
            </a>
            @endif
        </form>
    </div></div>

    {{-- Transactions --}}
    <div class="card" style="overflow:hidden;padding-bottom:60px;">
        @if (!$selectedTrip || $expenses->isEmpty())
        <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;padding:64px 24px;text-align:center;">
            <div style="width:56px;height:56px;border-radius:16px;background:var(--primary-light);display:flex;align-items:center;justify-content:center;margin-bottom:16px;">
                <i class="fa-solid fa-receipt" style="font-size:24px;color:var(--primary);"></i>
            </div>
            <div style="font-size:15px;font-weight:600;margin-bottom:6px;">
                {{ (request('category') || request('date_from') || request('date_to')) ? 'No expenses match these filters' : 'No expenses recorded yet' }}
            </div>
            <div class="text-muted" style="font-size:13px;max-width:280px;line-height:1.5;">
                Start tracking your trip costs by adding your first expense. Keep your budget on track with real-time ledger updates.
            </div>
        </div>
        @else
        <div>
            @foreach ($expenses as $expense)
            @php $color = $categoryColors[$expense->category] ?? '#6B7280'; @endphp
            <div class="txn-card">
                <div class="txn-icon" style="background:{{ $color }}22;color:{{ $color }};">
                    <i class="fa-solid {{ $categoryIcons[$expense->category] ?? 'fa-circle' }}"></i>
                </div>
                <div class="txn-main">
                    <div class="txn-desc">{{ $expense->description ?? $expense->category }}</div>
                    <div class="txn-meta">
                        <span>{{ $expense->category }}</span> · <span>{{ $expense->expense_date->format('M j, Y') }}</span>
                        @if ($expense->receipt_path)
                        <span title="Receipt attached"><i class="fa-solid fa-paperclip" style="color:var(--success);"></i></span>
                        @endif
                    </div>
                </div>
                <div class="txn-amount">₱{{ number_format($expense->amount, 0) }}</div>
                <div class="txn-actions">
                    <a href="{{ route('expenses.edit', $expense) }}" class="txn-action-btn" title="Edit">
                        <i class="fa-solid fa-pen"></i>
                    </a>
                    <form method="POST" action="{{ route('expenses.destroy', $expense) }}" onsubmit="return confirm('Delete this expense?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="txn-action-btn txn-action-danger" title="Delete">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </form>
                </div>
            </div>
            @endforeach
        </div>
        @if ($expenses->hasPages())
        <div style="padding:12px 16px;border-top:1px solid var(--border-light);">{{ $expenses->links() }}</div>
        @endif
        @endif
    </div>

    {{-- Floating Add Expense button --}}
    <a href="{{ route('expenses.create') }}{{ $selectedTripId ? '?trip_id='.$selectedTripId : '' }}" class="expense-fab" title="Add Expense">
        <i class="fa-solid fa-plus"></i>
    </a>

</div>

@endif

@endsection
