@extends('layouts.admin')
<<<<<<< HEAD
@section('content')
<div class="admin-page-head">
    <div>
        <h1>User Management</h1>
        <p>Manage, monitor, and moderate user accounts across the Budgetra platform.</p>
    </div>
    <div class="admin-page-head-actions">
        <form method="GET" class="admin-inline-filter">
            @if(request('search'))<input type="hidden" name="search" value="{{ request('search') }}">@endif
            <select name="status" class="admin-input" onchange="this.form.submit()">
                <option value="">All Users</option>
                <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                <option value="banned" {{ request('status') === 'banned' ? 'selected' : '' }}>Banned</option>
            </select>
            <button type="submit" class="admin-btn admin-btn-outline"><i class="fa-solid fa-filter"></i> Filter</button>
        </form>
        <a href="{{ route('admin.users.export', request()->query()) }}" class="admin-btn admin-btn-outline"><i class="fa-solid fa-download"></i> Export</a>
    </div>
</div>
@if(session('success'))<div class="admin-alert-success">{{ session('success') }}</div>@endif

<div class="admin-stat-row">
    <div class="admin-stat-card">
        <span class="admin-stat-label">Total Active Users</span>
        <strong class="admin-stat-value">{{ $activeUsers }}</strong>
    </div>
    <div class="admin-stat-card">
        <span class="admin-stat-label">Trips Planned (Month)</span>
        <strong class="admin-stat-value">{{ $tripsThisMonth }}</strong>
    </div>
    <div class="admin-stat-card">
        <span class="admin-stat-label">Banned Users</span>
        <strong class="admin-stat-value">{{ $bannedUsers }}</strong>
    </div>
</div>

<form method="GET" class="admin-search-form" style="margin-bottom:16px;">
    @if(request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search name or email..." class="admin-input">
    <button type="submit" class="admin-btn admin-btn-outline">Search</button>
</form>

<div class="admin-card">
    <table class="admin-table">
        <thead>
            <tr><th>User</th><th>Trip Count</th><th>Status</th><th>Actions</th></tr>
        </thead>
        <tbody>
        @forelse($users as $user)
            <tr>
                <td>
                    <div class="admin-user-cell">
                        <div class="admin-user-avatar">{{ strtoupper(substr($user->full_name ?: $user->email, 0, 1)) }}</div>
                        <div>
                            <div class="admin-user-name">{{ $user->full_name ?: '—' }}</div>
                            <div class="admin-user-email">{{ $user->email }}</div>
                        </div>
                    </div>
                </td>
                <td>{{ $user->trips_count }} {{ Str::plural('Trip', $user->trips_count) }}</td>
                <td>
                    @if($user->role === 'banned')
                        <span class="admin-badge admin-badge-danger">BANNED</span>
                    @else
                        <span class="admin-badge admin-badge-success">ACTIVE</span>
                    @endif
                </td>
                <td class="admin-table-actions">
                    @if($user->id !== auth()->id())
                        <form method="POST" action="{{ route('admin.users.destroy', $user) }}" onsubmit="return confirm('Delete this user? This cannot be undone.')">
                            @csrf @method('DELETE')
                            <button type="submit" class="admin-icon-btn admin-icon-btn-danger" title="Delete"><i class="fa-solid fa-trash"></i></button>
                        </form>
                        <form method="POST" action="{{ route('admin.users.ban', $user) }}">
                            @csrf @method('PATCH')
                            <button type="submit" class="admin-icon-btn {{ $user->role === 'banned' ? 'admin-icon-btn-active' : '' }}" title="{{ $user->role === 'banned' ? 'Unban' : 'Ban' }}">
                                <i class="fa-solid fa-ban"></i>
                            </button>
                        </form>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="4" class="admin-table-empty">No users found.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="admin-pagination">{{ $users->links() }}</div>
=======
@section('title', 'Manage Users')
@section('content')

<h1 style="font-size:26px;font-weight:800;margin:0 0 20px;color:var(--dark);">Manage Users</h1>

@if(session('success'))
<div class="alert alert-success mb-16">{{ session('success') }}</div>
@endif

{{-- Stat chips --}}
<div style="display:flex;gap:14px;margin-bottom:20px;flex-wrap:wrap;">
    <div style="flex:1;min-width:180px;background:var(--bg-white);border:1.5px solid var(--border);border-radius:12px;padding:16px 18px;">
        <p style="margin:0;font-size:11px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:var(--muted);">Total Active Users</p>
        <p style="margin:6px 0 0;font-size:24px;font-weight:800;color:var(--dark);">{{ $totalActiveUsers }}</p>
    </div>
    <div style="flex:1;min-width:180px;background:var(--bg-white);border:1.5px solid var(--border);border-radius:12px;padding:16px 18px;">
        <p style="margin:0;font-size:11px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:var(--muted);">Trips Planned (Month)</p>
        <p style="margin:6px 0 0;font-size:24px;font-weight:800;color:var(--dark);">{{ $tripsThisMonth }}</p>
    </div>
    <div style="flex:1;min-width:180px;background:var(--bg-white);border:1.5px solid var(--border);border-radius:12px;padding:16px 18px;">
        <p style="margin:0;font-size:11px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:var(--muted);">Pending Verifications</p>
        <p style="margin:6px 0 0;font-size:24px;font-weight:800;color:var(--dark);">0</p>
    </div>
</div>

{{-- Search / filter --}}
<form method="GET" style="display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap;">
    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search name or email..."
           style="flex:1;min-width:220px;background:var(--bg-white);border:1.5px solid var(--border);border-radius:10px;padding:10px 14px;font-size:13px;color:var(--dark);">
    <select name="role" style="background:var(--bg-white);border:1.5px solid var(--border);border-radius:10px;padding:10px 14px;font-size:13px;color:var(--dark);">
        <option value="">All roles</option>
        <option value="traveler" {{ request('role') === 'traveler' ? 'selected' : '' }}>Traveler</option>
        <option value="admin"    {{ request('role') === 'admin'    ? 'selected' : '' }}>Admin</option>
        <option value="banned"   {{ request('role') === 'banned'   ? 'selected' : '' }}>Banned</option>
    </select>
    <button type="submit" style="background:var(--primary);color:#fff;border:none;border-radius:10px;padding:10px 20px;font-size:13px;font-weight:700;cursor:pointer;">Filter</button>
</form>

{{-- Users table --}}
<div style="background:var(--bg-white);border:1.5px solid var(--border);border-radius:14px;overflow:hidden;">
    <div style="display:grid;grid-template-columns:1fr 120px 110px 90px;gap:12px;padding:11px 20px;border-bottom:1px solid var(--border);">
        <span style="font-size:10.5px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:var(--muted);">User</span>
        <span style="font-size:10.5px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:var(--muted);">Trip Count</span>
        <span style="font-size:10.5px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:var(--muted);">Status</span>
        <span style="font-size:10.5px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:var(--muted);text-align:right;">Actions</span>
    </div>

    @forelse ($users as $user)
    @php
        $isBanned    = $user->role === 'banned';
        $avatarColor = ['#F1A53D', '#3B82F6', '#0D9488', '#EF4444', '#8B5CF6'][crc32($user->email) % 5];
    @endphp
    <div style="display:grid;grid-template-columns:1fr 120px 110px 90px;gap:12px;align-items:center;padding:13px 20px;{{ !$loop->last ? 'border-bottom:1px solid var(--border);' : '' }}">
        <a href="{{ route('admin.users.show', $user) }}" style="display:flex;align-items:center;gap:11px;text-decoration:none;min-width:0;">
            <div style="width:34px;height:34px;border-radius:10px;background:{{ $avatarColor }}1A;color:{{ $avatarColor }};display:flex;align-items:center;justify-content:center;font-weight:800;font-size:13px;flex-shrink:0;">
                {{ mb_substr($user->full_name ?: $user->email, 0, 1) }}
            </div>
            <div style="min-width:0;">
                <div style="font-size:13.5px;font-weight:700;color:var(--dark);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $user->full_name }}</div>
                <div style="font-size:12px;color:var(--muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $user->email }}</div>
            </div>
        </a>
        <span style="font-size:13px;color:var(--dark);">{{ $user->trips_count }} {{ Str::plural('Trip', $user->trips_count) }}</span>
        <span style="display:inline-block;width:fit-content;padding:3px 10px;border-radius:99px;font-size:10.5px;font-weight:700;letter-spacing:.03em;text-transform:uppercase;
                     {{ $isBanned ? 'background:rgba(220,38,38,0.14);color:#DC2626;' : 'background:rgba(22,163,74,0.14);color:#16A34A;' }}">
            {{ $isBanned ? 'Banned' : 'Active' }}
        </span>
        <div style="display:flex;align-items:center;justify-content:flex-end;gap:6px;">
            @if($user->id !== auth()->id())
            <form method="POST" action="{{ route('admin.users.destroy', $user) }}" onsubmit="return confirm('Delete this user?')" style="margin:0;">
                @csrf @method('DELETE')
                <button type="submit" title="Delete"
                        style="width:30px;height:30px;border-radius:8px;border:1.5px solid var(--border);background:var(--bg-white);color:var(--muted);cursor:pointer;display:flex;align-items:center;justify-content:center;transition:border-color .15s,color .15s;"
                        onmouseenter="this.style.borderColor='#DC2626';this.style.color='#DC2626'" onmouseleave="this.style.borderColor='var(--border)';this.style.color='var(--muted)'">
                    <i class="fa-solid fa-trash-can" style="font-size:12px;"></i>
                </button>
            </form>
            <form method="POST" action="{{ route('admin.users.ban', $user) }}" style="margin:0;">
                @csrf @method('PATCH')
                <button type="submit" title="{{ $isBanned ? 'Unban' : 'Ban' }}"
                        style="width:30px;height:30px;border-radius:8px;border:1.5px solid var(--border);background:var(--bg-white);color:{{ $isBanned ? '#16A34A' : 'var(--muted)' }};cursor:pointer;display:flex;align-items:center;justify-content:center;transition:border-color .15s,color .15s;"
                        onmouseenter="this.style.borderColor='{{ $isBanned ? '#16A34A' : '#DC2626' }}';this.style.color='{{ $isBanned ? '#16A34A' : '#DC2626' }}'"
                        onmouseleave="this.style.borderColor='var(--border)';this.style.color='{{ $isBanned ? '#16A34A' : 'var(--muted)' }}'">
                    <i class="fa-solid {{ $isBanned ? 'fa-circle-check' : 'fa-ban' }}" style="font-size:12px;"></i>
                </button>
            </form>
            @endif
        </div>
    </div>
    @empty
    <div style="padding:48px 20px;text-align:center;color:var(--muted);font-size:13px;">No users found.</div>
    @endforelse
</div>

@if ($users->hasPages())
<div style="margin-top:20px;">{{ $users->links() }}</div>
@endif

>>>>>>> 537609b8368acc8725e027fe8e60d1600528fadc
@endsection
