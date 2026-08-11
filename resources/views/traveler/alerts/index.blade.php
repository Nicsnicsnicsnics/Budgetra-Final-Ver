@extends('layouts.app')
@section('title', 'Alerts')
@section('content')

@php
    $unreadCount  = $notifications->getCollection()->where('is_read', false)->count();
    $hasAny       = $notifications->getCollection()->isNotEmpty();
@endphp

@if (session('success'))
<div class="alert alert-success mb-16">{{ session('success') }}</div>
@endif

{{-- No trips at all --}}
@if ($trips->isEmpty())
<div class="empty-state-center" style="min-height:80vh;">
    <div style="width:64px;height:64px;border-radius:16px;background:var(--primary);display:flex;align-items:center;justify-content:center;margin-bottom:24px;">
        <i class="fa-solid fa-bell" style="font-size:28px;color:#fff;"></i>
    </div>
    @if (!auth()->user()?->userProfile)
    <h2 style="font-weight:700;font-size:22px;margin-bottom:10px;color:var(--dark);">Set up your profile first</h2>
    <p style="color:var(--muted);margin-bottom:28px;font-size:14px;max-width:320px;line-height:1.6;">Complete your travel profile before planning a trip and receiving budget alerts.</p>
    <a href="{{ route('profile.setup') }}" style="display:inline-flex;align-items:center;gap:10px;background:var(--primary);color:#fff;border-radius:30px;padding:14px 32px;font-size:13px;font-weight:700;letter-spacing:.06em;text-decoration:none;text-transform:uppercase;">
        <i class="fa-solid fa-user"></i> Set Up Your Profile First
    </a>
    @else
    <h2 style="font-weight:700;font-size:22px;margin-bottom:10px;color:var(--dark);">No trips yet</h2>
    <p style="color:var(--muted);margin-bottom:28px;font-size:14px;max-width:320px;line-height:1.6;">Plan a trip first to start receiving budget alerts and notifications.</p>
    <a href="{{ route('trips.plan') }}" style="display:inline-flex;align-items:center;gap:10px;background:var(--primary);color:#fff;border-radius:30px;padding:14px 32px;font-size:13px;font-weight:700;letter-spacing:.06em;text-decoration:none;text-transform:uppercase;">
        <i class="fa-solid fa-plane"></i> Plan Your First Trip
    </a>
    @endif
</div>

@elseif (!$hasAny)
{{-- Has trips but no notifications --}}
<div class="empty-state-center" style="min-height:80vh;">
    <div style="width:64px;height:64px;border-radius:16px;background:var(--primary);display:flex;align-items:center;justify-content:center;margin-bottom:24px;">
        <i class="fa-solid fa-bell" style="font-size:28px;color:#fff;"></i>
    </div>
    <h2 style="font-weight:700;font-size:22px;margin-bottom:10px;color:var(--dark);">All caught up!</h2>
    <p style="color:var(--muted);font-size:14px;max-width:320px;line-height:1.6;">
        @if ($activeTrip)
            No notifications for <strong>{{ $activeTrip->destination }}</strong> yet.
        @else
            No notifications yet. We'll alert you when your budget needs attention.
        @endif
    </p>
</div>

@else
@php
    // Icon/color per notification type — same three-way palette (danger /
    // success / neutral) the mockup used, mapped onto this app's own type
    // strings instead of hardcoded hex so it adapts across themes.
    $typeMeta = function (string $type): array {
        return match (true) {
            $type === 'budget_alert'                                  => ['style' => 'danger',  'icon' => 'fa-triangle-exclamation', 'title' => 'Budget exceeded'],
            $type === 'budget_warning'                                => ['style' => 'warning', 'icon' => 'fa-clock',               'title' => 'Budget almost reached'],
            $type === 'trip_created'                                  => ['style' => 'success', 'icon' => 'fa-circle-check',        'title' => 'Trip saved'],
            $type === 'savings_goal_reached'                          => ['style' => 'success', 'icon' => 'fa-piggy-bank',          'title' => 'Savings goal reached'],
            $type === 'expense_added'                                 => ['style' => 'neutral', 'icon' => 'fa-receipt',             'title' => 'Receipt scanned and added'],
            $type === 'trip_reminder'                                 => ['style' => 'neutral', 'icon' => 'fa-plane',               'title' => 'Trip reminder'],
            $type === 'itinerary_reminder'                            => ['style' => 'neutral', 'icon' => 'fa-calendar',            'title' => 'Itinerary reminder'],
            default                                                    => ['style' => 'neutral', 'icon' => 'fa-bell',               'title' => 'Notification'],
        };
    };
    $styleColors = [
        'danger'  => ['bg' => 'rgba(220,38,38,0.14)',  'fg' => '#DC2626'],
        'warning' => ['bg' => 'rgba(217,119,6,0.14)',  'fg' => '#D97706'],
        'success' => ['bg' => 'rgba(22,163,74,0.14)',  'fg' => '#16A34A'],
        'neutral' => ['bg' => 'var(--border)',          'fg' => 'var(--muted)'],
    ];
    $grouped = $notifications->getCollection()->groupBy(fn ($n) => $n->created_at->isToday() ? 'Today' : 'Earlier');
@endphp

{{-- Trip filter pills --}}
@if ($trips->count() > 1)
<div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;margin-bottom:20px;">
    @foreach ($trips as $trip)
    @php $tripUnread = $unreadCounts[$trip->id] ?? 0; @endphp
    <a href="{{ route('alerts.index', ['trip_id' => $trip->id]) }}"
       style="position:relative;font-size:12px;font-weight:600;padding:5px 14px;border-radius:20px;text-decoration:none;
              background:{{ $activeTrip?->id === $trip->id ? 'var(--primary)' : 'var(--border)' }};
              color:{{ $activeTrip?->id === $trip->id ? '#fff' : 'var(--dark)' }};">
        {{ $trip->destination }}
        @if ($tripUnread > 0)
        <span style="position:absolute;top:-7px;right:-6px;min-width:17px;height:17px;padding:0 4px;border-radius:99px;background:#DC2626;color:#fff;font-size:10px;font-weight:700;display:flex;align-items:center;justify-content:center;line-height:1;border:1.5px solid var(--bg-white);">
            {{ $tripUnread > 9 ? '9+' : $tripUnread }}
        </span>
        @endif
    </a>
    @endforeach
</div>
@endif

{{-- Notifications card --}}
<div x-data="{ tab: 'all' }" style="background:var(--bg-white);border:1.5px solid var(--border);border-radius:16px;overflow:hidden;">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:15px 20px;border-bottom:1px solid var(--border);">
        <div style="display:flex;align-items:center;gap:10px;">
            <span style="font-size:14px;font-weight:700;color:var(--dark);">Notifications</span>
            <div style="display:flex;gap:4px;background:var(--bg);border-radius:8px;padding:2px;">
                <button type="button" @click="tab = 'all'"
                        :style="(tab === 'all' ? 'background:var(--bg-white);color:var(--dark);' : 'background:none;color:var(--muted);') + 'font-size:12px;font-weight:600;padding:4px 12px;border-radius:6px;border:none;cursor:pointer;'">All</button>
                <button type="button" @click="tab = 'unread'"
                        :style="(tab === 'unread' ? 'background:var(--bg-white);color:var(--dark);' : 'background:none;color:var(--muted);') + 'font-size:12px;font-weight:600;padding:4px 12px;border-radius:6px;border:none;cursor:pointer;'">Unread</button>
            </div>
        </div>
        <div style="display:flex;align-items:center;gap:10px;">
            @if ($unreadCount > 0)
            <span style="padding:3px 10px;border-radius:99px;background:var(--primary);color:#fff;font-size:11px;font-weight:700;">{{ $unreadCount }} unread</span>
            @endif
            <form method="POST" action="{{ route('alerts.read-all') }}">
                @csrf @method('PATCH')
                <button class="btn btn-outline btn-sm" type="submit" {{ $unreadCount === 0 ? 'disabled' : '' }}>
                    <i class="fa-solid fa-check-double"></i> Mark all as read
                </button>
            </form>
        </div>
    </div>

    @if ($unreadCount === 0)
    <div x-show="tab === 'unread'" style="width:100%;box-sizing:border-box;text-align:center;padding:48px 20px;">
        <div style="width:48px;height:48px;border-radius:50%;background:var(--bg);display:flex;align-items:center;justify-content:center;margin:0 auto 14px;">
            <i class="fa-solid fa-bell-slash" style="font-size:18px;color:var(--muted);"></i>
        </div>
        <p style="margin:0;font-size:13.5px;font-weight:600;color:var(--dark);">No unread notifications</p>
        <p style="margin:4px 0 0;font-size:12.5px;color:var(--muted);">You're all caught up!</p>
    </div>
    @endif

    @foreach ($grouped as $groupLabel => $groupNotifs)
    @php $groupHasUnread = $groupNotifs->contains(fn ($n) => !$n->is_read); @endphp
    <div x-show="tab === 'all' || {{ $groupHasUnread ? 'true' : 'false' }}"
         style="padding:8px 20px 4px;font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.03em;{{ !$loop->first ? 'border-top:1px solid var(--border);' : '' }}">
        {{ $groupLabel }}
    </div>
    @foreach ($groupNotifs as $notif)
    @php
        $meta   = $typeMeta($notif->type);
        $colors = $styleColors[$meta['style']];
    @endphp
    <div x-show="tab === 'all' || {{ $notif->is_read ? 'false' : 'true' }}"
         style="position:relative;{{ !$loop->last ? 'border-bottom:1px solid var(--border);' : '' }}">
        @if (!$notif->is_read)
        <form method="POST" action="{{ route('alerts.read', $notif) }}" style="margin:0;">
            @csrf @method('PATCH')
            <button type="submit" style="display:grid;grid-template-columns:28px 1fr;column-gap:10px;width:100%;padding:14px 20px;background:none;border:none;text-align:left;cursor:pointer;font-family:inherit;transition:background .15s;"
                    onmouseenter="this.style.background='var(--bg)'" onmouseleave="this.style.background='none'">
                <span style="grid-column:1;width:28px;height:28px;border-radius:50%;background:{{ $colors['bg'] }};display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="fa-solid {{ $meta['icon'] }}" style="color:{{ $colors['fg'] }};font-size:12px;"></i>
                </span>
                <span style="grid-column:2;min-width:0;">
                    <span style="display:block;font-size:14px;font-weight:700;color:var(--dark);line-height:1.3;">{{ $meta['title'] }}</span>
                    <span style="display:block;margin-top:3px;font-size:12.5px;color:var(--muted);line-height:1.4;">
                        {{ $notif->message }}
                        @if (!$activeTrip && $notif->trip)
                        <span style="white-space:nowrap;"><i class="fa-solid fa-location-dot" style="font-size:9px;"></i> {{ $notif->trip->destination }}</span>
                        @endif
                    </span>
                    <span style="display:block;margin-top:5px;font-size:11.5px;color:var(--muted);opacity:.75;">{{ $notif->created_at->diffForHumans() }}</span>
                </span>
            </button>
        </form>
        @else
        <div style="display:grid;grid-template-columns:28px 1fr;column-gap:10px;padding:14px 20px;">
            <div style="grid-column:1;width:28px;height:28px;border-radius:50%;background:{{ $colors['bg'] }};display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="fa-solid {{ $meta['icon'] }}" style="color:{{ $colors['fg'] }};font-size:12px;"></i>
            </div>
            <div style="grid-column:2;min-width:0;">
                <p style="margin:0;font-size:14px;font-weight:700;color:var(--dark);line-height:1.3;">{{ $meta['title'] }}</p>
                <p style="margin:3px 0 0;font-size:12.5px;color:var(--muted);line-height:1.4;">
                    {{ $notif->message }}
                    @if (!$activeTrip && $notif->trip)
                    <span style="white-space:nowrap;"><i class="fa-solid fa-location-dot" style="font-size:9px;"></i> {{ $notif->trip->destination }}</span>
                    @endif
                </p>
                <p style="margin:5px 0 0;font-size:11.5px;color:var(--muted);opacity:.75;">{{ $notif->created_at->diffForHumans() }}</p>
            </div>
        </div>
        @endif
    </div>
    @endforeach
    @endforeach
</div>

@if ($notifications->hasPages())
<div class="mt-24">{{ $notifications->links() }}</div>
@endif

@endif

@endsection
