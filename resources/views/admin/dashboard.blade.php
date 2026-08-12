@extends('layouts.admin')
@section('content')
<div class="admin-page-head">
    <div>
        <h1>Overview</h1>
        <p>Quick glance at the Budgetra platform.</p>
    </div>
</div>

<div class="admin-stat-row">
    <a href="{{ route('admin.users.index') }}" class="admin-stat-card" style="text-decoration:none;">
        <span class="admin-stat-label">Active Users</span>
        <strong class="admin-stat-value">{{ $stats['users'] }}</strong>
    </a>
    <a href="{{ route('admin.destinations.index') }}" class="admin-stat-card" style="text-decoration:none;">
        <span class="admin-stat-label">Destinations</span>
        <strong class="admin-stat-value">{{ $stats['destinations'] }}</strong>
    </a>
    <a href="{{ route('admin.attractions.index') }}" class="admin-stat-card" style="text-decoration:none;">
        <span class="admin-stat-label">Attractions</span>
        <strong class="admin-stat-value">{{ $stats['attractions'] }}</strong>
    </a>
    <a href="{{ route('admin.travel-costs.index') }}" class="admin-stat-card" style="text-decoration:none;">
        <span class="admin-stat-label">Travel Cost Entries</span>
        <strong class="admin-stat-value">{{ $stats['travelCosts'] }}</strong>
    </a>
</div>
@endsection
