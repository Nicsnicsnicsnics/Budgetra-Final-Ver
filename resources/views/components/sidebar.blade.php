@props(['active' => ''])
@php
    $user   = auth()->user();
    $unread = $user ? $user->notifications()->where('is_read', false)->count() : 0;

    $links = [
        ['href' => url('/dashboard'),  'icon' => 'fa-solid fa-house',            'label' => 'Dashboard',    'key' => 'dashboard',   'segment' => 'dashboard'],
        ['href' => url('/trips'),      'icon' => 'fa-solid fa-map-location-dot', 'label' => 'Planner',      'key' => 'trips',       'segment' => 'trips'],
        ['href' => route('saved-trips'), 'icon' => 'fa-solid fa-suitcase-rolling', 'label' => 'Saved Trips', 'key' => 'saved-trips', 'segment' => 'saved-trips'],
        ['href' => url('/savings'),    'icon' => 'fa-solid fa-piggy-bank',       'label' => 'Saving Goals', 'key' => 'savings',     'segment' => 'savings'],
        ['href' => url('/itinerary'),  'icon' => 'fa-regular fa-calendar-days',  'label' => 'Itinerary',    'key' => 'itinerary',   'segment' => 'itinerary'],
        ['href' => url('/expenses'),   'icon' => 'fa-solid fa-receipt',          'label' => 'Expenses',     'key' => 'expenses',    'segment' => 'expenses'],
        ['href' => url('/alerts'),     'icon' => 'fa-regular fa-bell',           'label' => 'Notifications','key' => 'alerts',      'segment' => 'alerts', 'badge' => $unread],
        ['href' => route('multi-trips.index'), 'icon' => 'fa-solid fa-layer-group', 'label' => 'Multi Trips', 'key' => 'multi-trips', 'segment' => 'multi-trips'],
        ['href' => route('moments.index'), 'icon' => 'fa-regular fa-images',    'label' => 'Moments',      'key' => 'moments',     'segment' => 'moments'],
    ];

    $bottomLinks = [
        ['href' => auth()->user()?->userProfile ? url('/profile') : url('/profile/setup'), 'icon' => 'fa-regular fa-user-circle', 'label' => 'Profile', 'key' => 'profile', 'segment' => 'profile'],
        ['href' => url('/dashboard'),'icon' => 'fa-solid fa-gear', 'label' => 'Settings', 'key' => 'settings', 'segment' => 'settings'],
    ];

    // Auto-detect active from URL if not explicitly passed
    $currentPath = request()->path();
    if (!$active) {
        foreach (array_merge($links, $bottomLinks) as $link) {
            if ($currentPath === $link['segment'] || str_starts_with($currentPath, $link['segment'] . '/')) {
                $active = $link['key'];
                break;
            }
        }
    }
@endphp

@persist('sidebar')
<aside class="sidebar" id="appSidebar">

    <div class="sidebar-header">
        <button class="sidebar-toggle-btn" id="sidebarToggle" title="Toggle sidebar">
            <i class="fa-solid fa-bars" id="sidebarToggleIcon"></i>
        </button>
    </div>

    <div class="sidebar-brand" style="display:flex;align-items:center;gap:10px;padding:4px 16px 14px;">
        <img src="{{ asset('systemicons/budgetra-main.png') }}" alt="Budgetra"
             style="width:38px;height:38px;border-radius:10px;object-fit:contain;flex-shrink:0;">
        <span class="sidebar-link-label" style="font-size:18px;font-weight:800;color:var(--primary);letter-spacing:0.01em;">Budgetra</span>
    </div>

    <nav class="sidebar-nav">
        @foreach ($links as $link)
        <a href="{{ $link['href'] }}" wire:navigate
           class="sidebar-link {{ $active === $link['key'] ? 'active' : '' }}"
           title="{{ $link['label'] }}">
            <i class="{{ $link['icon'] }}"></i>
            <span class="sidebar-link-label">{{ $link['label'] }}</span>
            @if (!empty($link['badge']) && $link['badge'] > 0)
                <span class="sidebar-badge sidebar-link-label">{{ $link['badge'] > 99 ? '99+' : $link['badge'] }}</span>
            @endif
        </a>
        @endforeach

        {{-- Divider --}}
        <div style="height:1px;background:rgba(255,255,255,.08);margin:6px 12px;"></div>

        <div class="sidebar-bottom-links">
            {{-- Profile & Settings --}}
            @foreach ($bottomLinks as $link)
            <a href="{{ $link['href'] }}" wire:navigate
               class="sidebar-link {{ $active === $link['key'] ? 'active' : '' }}"
               title="{{ $link['label'] }}">
                <i class="{{ $link['icon'] }}"></i>
                <span class="sidebar-link-label">{{ $link['label'] }}</span>
            </a>
            @endforeach

            {{-- Logout --}}
            <form method="POST" action="{{ route('logout') }}" style="margin:0;">
                @csrf
                <button type="submit" class="sidebar-link" style="width:100%;background:none;border:none;cursor:pointer;text-align:left;" title="Logout">
                    <i class="fa-solid fa-right-from-bracket"></i>
                    <span class="sidebar-link-label">Logout</span>
                </button>
            </form>
        </div>
    </nav>

</aside>
@endpersist

<script>
(function () {
    var wrap = document.getElementById('dashWrapper');
    var btn  = document.getElementById('sidebarToggle');
    var icon = document.getElementById('sidebarToggleIcon');
    if (!wrap || !btn) return;

    function applyState(collapsed) {
        wrap.classList.toggle('sidebar-collapsed', collapsed);
        icon.className = collapsed ? 'fa-solid fa-chevron-right' : 'fa-solid fa-bars';
    }

    applyState(localStorage.getItem('sidebarCollapsed') === '1');

    // The toggle button lives inside the persisted sidebar block, so its listener
    // survives wire:navigate transitions — guard against binding it more than once.
    if (!btn.dataset.bound) {
        btn.dataset.bound = '1';
        btn.addEventListener('click', function () {
            var c = !wrap.classList.contains('sidebar-collapsed');
            localStorage.setItem('sidebarCollapsed', c ? '1' : '0');
            applyState(c);
        });
    }
})();
</script>
