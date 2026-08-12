@props(['active' => ''])
<<<<<<< HEAD
<aside class="admin-sidebar" id="adminSidebar">
    <div class="admin-sidebar-brand">
        <div class="admin-sidebar-logo"><i class="fa-solid fa-compass"></i></div>
        <div>
            <div class="admin-sidebar-name">Budgetra</div>
            <div class="admin-sidebar-tagline">Travel Admin</div>
        </div>
    </div>

    <nav class="admin-sidebar-nav">
        @php
        $primaryLinks = [
            ['href' => route('admin.dashboard'),          'icon' => 'fa-solid fa-table-cells-large', 'label' => 'Dashboard',      'key' => 'dashboard'],
            ['href' => route('admin.users.index'),         'icon' => 'fa-solid fa-users',              'label' => 'User Accounts',  'key' => 'users'],
            ['href' => route('admin.destinations.index'),  'icon' => 'fa-solid fa-compass',            'label' => 'Destinations',   'key' => 'destinations'],
            ['href' => route('admin.attractions.index'),   'icon' => 'fa-solid fa-map-location-dot',   'label' => 'Attractions',    'key' => 'attractions'],
            ['href' => route('admin.travel-costs.index'),  'icon' => 'fa-solid fa-sack-dollar',        'label' => 'Travel Costs',   'key' => 'travel-costs'],
            ['href' => route('admin.reviews.index'),       'icon' => 'fa-regular fa-flag',             'label' => 'User Reviews',   'key' => 'reviews'],
        ];
        $moreLinks = [
            ['href' => route('admin.reports.index'),      'icon' => 'fa-solid fa-chart-column',     'label' => 'Reports',      'key' => 'reports'],
            ['href' => route('admin.config.index'),       'icon' => 'fa-solid fa-sliders',          'label' => 'Config',       'key' => 'config'],
            ['href' => route('admin.ocr.index'),          'icon' => 'fa-solid fa-receipt',          'label' => 'OCR Logs',     'key' => 'ocr'],
            ['href' => route('admin.backup.index'),       'icon' => 'fa-solid fa-database',         'label' => 'Backup',       'key' => 'backup'],
        ];
        @endphp
        @foreach ($primaryLinks as $link)
        <a href="{{ $link['href'] }}" class="admin-sidebar-link {{ $active === $link['key'] ? 'active' : '' }}">
=======
@php
    $links = [
        ['href' => url('/admin'),              'icon' => 'fa-solid fa-gauge-high',       'label' => 'Overview',      'key' => 'dashboard',    'segment' => 'admin'],
        ['href' => url('/admin/users'),         'icon' => 'fa-solid fa-users',            'label' => 'Users',         'key' => 'users',        'segment' => 'admin/users'],
        ['href' => url('/admin/destinations'),  'icon' => 'fa-solid fa-map-pin',          'label' => 'Destinations',  'key' => 'destinations', 'segment' => 'admin/destinations'],
        ['href' => url('/admin/attractions'),   'icon' => 'fa-solid fa-mountain-sun',     'label' => 'Attractions',   'key' => 'attractions',  'segment' => 'admin/attractions'],
        ['href' => url('/admin/integrations'),  'icon' => 'fa-solid fa-plug-circle-bolt', 'label' => 'Integrations',  'key' => 'integrations', 'segment' => 'admin/integrations'],
        ['href' => url('/admin/reports'),       'icon' => 'fa-solid fa-chart-column',     'label' => 'Reports',       'key' => 'reports',      'segment' => 'admin/reports'],
        ['href' => url('/admin/reviews'),       'icon' => 'fa-solid fa-star-half-stroke', 'label' => 'Reviews',       'key' => 'reviews',      'segment' => 'admin/reviews'],
    ];

    // Auto-detect active from URL if not explicitly passed
    $currentPath = request()->path();
    if (!$active) {
        foreach ($links as $link) {
            if ($currentPath === $link['segment'] || str_starts_with($currentPath, $link['segment'] . '/')) {
                $active = $link['key'];
                break;
            }
        }
    }
@endphp

<aside class="sidebar" id="appSidebar">

    <div class="sidebar-header">
        <button class="sidebar-toggle-btn" id="sidebarToggle" title="Toggle sidebar">
            <i class="fa-solid fa-bars" id="sidebarToggleIcon"></i>
        </button>
    </div>

    <div class="sidebar-brand" style="display:flex;align-items:center;gap:10px;padding:4px 16px 14px;">
        <img src="{{ asset('systemicons/budgetra-new-icon.png') }}" alt="Budgetra"
             style="width:38px;height:38px;border-radius:10px;object-fit:contain;flex-shrink:0;">
        <span class="sidebar-link-label" style="font-size:18px;font-weight:800;color:var(--primary);letter-spacing:0.01em;">Budgetra</span>
    </div>
    <div class="sidebar-divider" style="margin-left:24px;margin-right:24px;"></div>

    <nav class="sidebar-nav">
        @foreach ($links as $link)
        <a href="{{ $link['href'] }}" data-segment="{{ $link['segment'] }}"
           class="sidebar-link {{ $active === $link['key'] ? 'active' : '' }}"
           title="{{ $link['label'] }}">
>>>>>>> 537609b8368acc8725e027fe8e60d1600528fadc
            <i class="{{ $link['icon'] }}"></i>
            <span>{{ $link['label'] }}</span>
        </a>
        @endforeach

        <div class="admin-sidebar-divider"></div>

        @foreach ($moreLinks as $link)
        <a href="{{ $link['href'] }}" class="admin-sidebar-link admin-sidebar-link-sub {{ $active === $link['key'] ? 'active' : '' }}">
            <i class="{{ $link['icon'] }}"></i>
            <span>{{ $link['label'] }}</span>
        </a>
        @endforeach
<<<<<<< HEAD
    </nav>

    <div class="admin-sidebar-foot">
        <a href="{{ route('dashboard') }}" class="admin-sidebar-link">
            <i class="fa-solid fa-arrow-left"></i>
            <span>Back to App</span>
        </a>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="admin-sidebar-link admin-sidebar-logout">
                <i class="fa-solid fa-right-from-bracket"></i>
                <span>Logout</span>
            </button>
        </form>
    </div>
</aside>
=======

        {{-- Divider --}}
        <div class="sidebar-divider"></div>

        <div class="sidebar-bottom-links">
            {{-- Back to traveler app --}}
            <a href="{{ route('admin.settings.index') }}" class="sidebar-link {{ request()->routeIs('admin.settings.index') ? 'active' : '' }}" title="Settings">
                <i class="fa-solid fa-gear"></i>
                <span class="sidebar-link-label">Settings</span>
            </a>

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

<script>
(function () {
    function applyState(collapsed) {
        var wrap = document.getElementById('dashWrapper');
        var icon = document.getElementById('sidebarToggleIcon');
        if (!wrap) return;
        wrap.classList.toggle('sidebar-collapsed', collapsed);
        if (icon) icon.className = collapsed ? 'fa-solid fa-chevron-right' : 'fa-solid fa-bars';
    }

    applyState(localStorage.getItem('sidebarCollapsed') === '1');

    if (!window.__adminSidebarToggleBound) {
        window.__adminSidebarToggleBound = true;
        document.addEventListener('click', function (e) {
            if (!e.target.closest('#sidebarToggle')) return;
            var wrap = document.getElementById('dashWrapper');
            if (!wrap) return;
            var c = !wrap.classList.contains('sidebar-collapsed');
            localStorage.setItem('sidebarCollapsed', c ? '1' : '0');
            applyState(c);
        });
    }
})();
</script>
>>>>>>> 537609b8368acc8725e027fe8e60d1600528fadc
