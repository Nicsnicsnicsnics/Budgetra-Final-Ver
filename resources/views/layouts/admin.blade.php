<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Budgetra Admin')</title>
    <link rel="icon" type="image/png" href="{{ asset('systemicons/budgetra-favicon.png') }}">
    <link rel="stylesheet" href="{{ asset('css/style.css') }}?v={{ filemtime(public_path('css/style.css')) }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    @livewireStyles
</head>
<<<<<<< HEAD
<body class="admin-body">
    <div class="admin-shell">
        <x-admin-sidebar :active="$active ?? ''" />
        <div class="admin-main">
            <div class="admin-topbar">
                <button type="button" class="admin-icon-btn" title="Notifications"><i class="fa-regular fa-bell"></i></button>
                <button type="button" class="admin-icon-btn" title="Help"><i class="fa-regular fa-circle-question"></i></button>
                <div class="admin-topbar-divider"></div>
                <div class="admin-topbar-profile">
                    <div class="admin-topbar-profile-text">
                        <div class="admin-topbar-name">Admin Profile</div>
                        <div class="admin-topbar-role">{{ auth()->user()?->role === 'admin' ? 'Super Admin' : 'Admin' }}</div>
                    </div>
                    <div class="admin-avatar">{{ strtoupper(substr(auth()->user()?->full_name ?: 'A', 0, 1)) }}</div>
                </div>
            </div>
            <div class="admin-content">
=======
<body class="dashboard-body" data-theme="{{ auth()->user()->theme ?? 'daylight' }}">
    <div class="dashboard-wrapper" id="dashWrapper">
        <x-admin-sidebar :active="$active ?? ''" />
        <div class="dash-main">
            <div class="dash-content">
>>>>>>> 537609b8368acc8725e027fe8e60d1600528fadc
                @yield('content')
            </div>
        </div>
    </div>
    @livewireScripts
</body>
</html>
