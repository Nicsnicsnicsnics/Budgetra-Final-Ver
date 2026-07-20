@extends('layouts.app')
@section('title', 'Expenses')
@section('content')

@if (session('success'))
<div class="alert alert-success" style="margin-bottom:16px;">{{ session('success') }}</div>
@endif

@if ($trips->isEmpty())
{{-- No trips empty state --}}
<div class="empty-state-center" style="min-height:80vh;">
    <div style="width:64px;height:64px;border-radius:16px;background:#934B19;display:flex;align-items:center;justify-content:center;margin-bottom:24px;">
        <i class="fa-solid fa-receipt" style="font-size:28px;color:#fff;"></i>
    </div>
    <h2 style="font-weight:700;font-size:22px;margin-bottom:10px;color:#1A0A00;">No expenses yet</h2>
    <p style="color:#9B8EA0;margin-bottom:28px;font-size:14px;max-width:320px;line-height:1.6;">Plan a trip first before logging your expenses.</p>
    <a href="{{ route('trips.plan') }}" style="display:inline-flex;align-items:center;gap:10px;background:#934B19;color:#fff;border-radius:30px;padding:14px 32px;font-size:13px;font-weight:700;letter-spacing:.06em;text-decoration:none;text-transform:uppercase;">
        <i class="fa-solid fa-plane"></i> Plan Your First Trip
    </a>
</div>

@else
@php
    $selectedTripId = request('trip_id') ?? $trips->first()?->id;
    $selectedTrip   = $selectedTripId ? $trips->firstWhere('id', $selectedTripId) : null;
    $iataToCity = [
        'MNL'=>'Manila','CEB'=>'Cebu','IAO'=>'Siargao','PPS'=>'Puerto Princesa',
        'DVO'=>'Davao','ILO'=>'Iloilo','BCD'=>'Bacolod','TAG'=>'Tagbilaran',
        'GES'=>'General Santos','CBO'=>'Cotabato','ZAM'=>'Zamboanga',
        'KLO'=>'Kalibo','MPH'=>'Malay','RXS'=>'Roxas','TAC'=>'Tacloban',
        'SIN'=>'Singapore','KUL'=>'Kuala Lumpur','BKK'=>'Bangkok','HKG'=>'Hong Kong',
        'NRT'=>'Tokyo','ICN'=>'Seoul','HND'=>'Tokyo','KIX'=>'Osaka',
        'SYD'=>'Sydney','MEL'=>'Melbourne','LAX'=>'Los Angeles','JFK'=>'New York',
        'DXB'=>'Dubai','CDG'=>'Paris','LHR'=>'London','FCO'=>'Rome',
        'BCN'=>'Barcelona','AMS'=>'Amsterdam','HAN'=>'Hanoi','SGN'=>'Ho Chi Minh',
        'DPS'=>'Bali','CGK'=>'Jakarta','MLE'=>'Maldives',
    ];
    $tripLabel = fn($t) => ($t->origin ?? 'Manila') . ' to ' . ($iataToCity[$t->destination_code ?? ''] ?? $t->destination);
@endphp

<div style="max-width:720px;margin:0 auto;width:100%;">

    {{-- Destination selector --}}
    <div style="margin-bottom:20px;">
        <div style="font-size:10px;font-weight:700;letter-spacing:.1em;color:var(--primary);text-transform:uppercase;margin-bottom:6px;">Destination</div>
        <div style="display:flex;gap:10px;align-items:center;">
            <div style="flex:1;">
                @if ($trips->count() === 1)
                @php $t = $trips->first(); @endphp
                <div style="background:#fff;border:1.5px solid var(--border);border-radius:12px;padding:13px 16px;display:flex;align-items:center;gap:10px;">
                    <i class="fa-solid fa-plane" style="color:#C8874A;font-size:13px;flex-shrink:0;"></i>
                    <span style="font-size:14px;font-weight:600;color:var(--dark);">{{ $tripLabel($t) }}</span>
                </div>
                @else
                <div x-data="{ open: false }" style="position:relative;">
                    <button @click="open = !open" @click.away="open = false" type="button"
                            style="width:100%;background:#fff;border:1.5px solid var(--border);border-radius:12px;padding:13px 16px;display:flex;align-items:center;gap:10px;cursor:pointer;text-align:left;">
                        <i class="fa-solid fa-plane" style="color:#C8874A;font-size:13px;flex-shrink:0;"></i>
                        <span style="flex:1;font-size:14px;font-weight:600;color:var(--dark);">
                            @php $sel = $trips->firstWhere('id', $selectedTripId); @endphp
                            {{ $tripLabel($sel) }}
                        </span>
                        <i class="fa-solid fa-chevron-down" style="font-size:11px;color:#9B8EA0;transition:transform .2s;" :style="open ? 'transform:rotate(180deg)' : ''"></i>
                    </button>
                    <div x-show="open" x-transition
                         style="position:absolute;top:calc(100% + 6px);left:0;right:0;background:#fff;border:1.5px solid var(--border);border-radius:12px;box-shadow:0 8px 24px rgba(45,27,20,.12);z-index:50;overflow:hidden;">
                        @foreach($trips as $t)
                        <a href="{{ route('expenses.index') }}?trip_id={{ $t->id }}"
                           style="display:flex;align-items:center;gap:10px;padding:12px 16px;text-decoration:none;background:{{ $selectedTripId == $t->id ? '#FDF3EB' : '#fff' }};border-bottom:1px solid #f5f0eb;"
                           onmouseenter="this.style.background='#f5f0eb'" onmouseleave="this.style.background='{{ $selectedTripId == $t->id ? '#FDF3EB' : '#fff' }}'">
                            <i class="fa-solid fa-plane" style="color:#C8874A;font-size:12px;flex-shrink:0;"></i>
                            <span style="font-size:13px;font-weight:{{ $selectedTripId == $t->id ? '700' : '500' }};color:{{ $selectedTripId == $t->id ? '#934b19' : 'var(--dark)' }};">{{ $tripLabel($t) }}</span>
                        </a>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>
            {{-- Add Expense button --}}
            <a href="{{ route('expenses.create') }}{{ $selectedTripId ? '?trip_id='.$selectedTripId : '' }}"
               style="flex-shrink:0;display:inline-flex;align-items:center;gap:7px;background:#934B19;color:#fff;border:none;border-radius:10px;padding:12px 18px;font-size:13px;font-weight:600;cursor:pointer;text-decoration:none;white-space:nowrap;">
                <i class="fa-solid fa-circle-plus" style="font-size:14px;"></i> Add Expense
            </a>
        </div>
    </div>

    {{-- Content card --}}
    <div style="background:#fff;border:1.5px solid var(--border);border-radius:16px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,.04);">

        @if (!$selectedTrip || $expenses->isEmpty())
        {{-- Empty state inside card --}}
        <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;padding:64px 24px;text-align:center;">
            <div style="width:56px;height:56px;border-radius:16px;background:#F5EDE7;display:flex;align-items:center;justify-content:center;margin-bottom:16px;">
                <i class="fa-solid fa-receipt" style="font-size:24px;color:#C8874A;"></i>
            </div>
            <div style="font-size:15px;font-weight:600;color:#1A0A00;margin-bottom:6px;">No expenses recorded yet</div>
            <div style="font-size:13px;color:#9B8EA0;max-width:280px;line-height:1.5;">
                Start tracking your trip costs by adding your first expense. Keep your budget on track with real-time ledger updates.
            </div>
        </div>

        @else
        {{-- Expenses table --}}
        <table style="width:100%;border-collapse:collapse;">
            <thead>
                <tr style="border-bottom:1.5px solid #F0E8DF;">
                    <th style="padding:12px 16px;text-align:left;font-size:11px;font-weight:700;color:#9B8EA0;text-transform:uppercase;letter-spacing:.05em;">Date</th>
                    <th style="padding:12px 16px;text-align:left;font-size:11px;font-weight:700;color:#9B8EA0;text-transform:uppercase;letter-spacing:.05em;">Category</th>
                    <th style="padding:12px 16px;text-align:left;font-size:11px;font-weight:700;color:#9B8EA0;text-transform:uppercase;letter-spacing:.05em;">Description</th>
                    <th style="padding:12px 16px;text-align:right;font-size:11px;font-weight:700;color:#9B8EA0;text-transform:uppercase;letter-spacing:.05em;">Amount</th>
                    <th style="padding:12px 16px;text-align:center;font-size:11px;font-weight:700;color:#9B8EA0;text-transform:uppercase;letter-spacing:.05em;"></th>
                </tr>
            </thead>
            <tbody>
            @foreach($expenses as $expense)
            <tr style="border-bottom:1px solid #FAF5F0;">
                <td style="padding:13px 16px;font-size:13px;color:#6B5E5B;">{{ $expense->expense_date->format('M j, Y') }}</td>
                <td style="padding:13px 16px;">
                    <span style="display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;background:#F5EDE7;color:#934B19;">{{ $expense->category }}</span>
                </td>
                <td style="padding:13px 16px;font-size:13px;color:#1A0A00;">{{ $expense->description ?? '—' }}</td>
                <td style="padding:13px 16px;font-size:14px;font-weight:700;color:#C8874A;text-align:right;">₱{{ number_format($expense->amount, 0) }}</td>
                <td style="padding:13px 16px;text-align:center;">
                    <div style="display:flex;gap:6px;justify-content:center;">
                        <a href="{{ route('expenses.edit', $expense) }}"
                           style="background:transparent;border:1.5px solid var(--border);border-radius:8px;padding:5px 12px;font-size:12px;font-weight:600;color:#6B5E5B;text-decoration:none;">Edit</a>
                        <form method="POST" action="{{ route('expenses.destroy', $expense) }}" style="display:inline;">
                            @csrf @method('DELETE')
                            <button onclick="return confirm('Delete this expense?')"
                                    style="background:transparent;border:1.5px solid #FEE2E2;border-radius:8px;padding:5px 12px;font-size:12px;font-weight:600;color:#DC2626;cursor:pointer;">Delete</button>
                        </form>
                    </div>
                </td>
            </tr>
            @endforeach
            </tbody>
        </table>
        @if($expenses->hasPages())
        <div style="padding:12px 16px;border-top:1px solid #F0E8DF;">{{ $expenses->links() }}</div>
        @endif
        @endif

    </div>

</div>
@endif

@endsection
