@props(['active' => ''])
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
