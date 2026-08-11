@props(['active' => ''])
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
            <i class="{{ $link['icon'] }}"></i>
            <span class="sidebar-link-label">{{ $link['label'] }}</span>
        </a>
        @endforeach

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
