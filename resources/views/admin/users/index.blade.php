@extends('layouts.admin')
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
            <tr><th>User</th><th>Trip Count</th><th>Total Travel Cost</th><th>Average Travel Cost</th><th>Status</th><th>Actions</th></tr>
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
                @php $totalTravelCost = $user->expenses_sum_amount ?? 0; @endphp
                <td>₱{{ number_format($totalTravelCost, 2) }}</td>
                <td>{{ $user->trips_count > 0 ? '₱' . number_format($totalTravelCost / $user->trips_count, 2) : '—' }}</td>
                <td>
                    @if($user->role === 'banned')
                        <span class="admin-badge admin-badge-danger">BANNED</span>
                    @else
                        <span class="admin-badge admin-badge-success">ACTIVE</span>
                    @endif
                </td>
                <td class="admin-table-actions">
                    @if($user->id !== auth()->id())
                        <button type="button" class="admin-icon-btn admin-icon-btn-danger js-delete-user-btn"
                            data-action="{{ route('admin.users.destroy', $user) }}"
                            data-name="{{ $user->full_name ?: $user->email }}"
                            title="Delete"><i class="fa-solid fa-trash"></i></button>
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
            <tr><td colspan="6" class="admin-table-empty">No users found.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="admin-pagination">{{ $users->links() }}</div>

<div id="deleteUserModal" class="admin-modal-backdrop" style="display:none;" onclick="if(event.target===this)closeDeleteUserModal();">
    <div class="admin-modal-card">
        <div class="admin-modal-head">
            <h3>Delete User</h3>
            <button type="button" class="admin-modal-close" onclick="closeDeleteUserModal();"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div style="padding:20px 24px;">
            <p style="margin:0 0 20px;font-size:14px;color:var(--admin-muted, #8A7A6C);line-height:1.6;">
                Delete <strong id="deleteUserName" style="color:var(--admin-ink, #241208);"></strong>? This cannot be undone — their trips, expenses, and other data will be permanently removed.
            </p>
            <form id="deleteUserForm" method="POST">
                @csrf @method('DELETE')
                <div class="admin-modal-actions">
                    <button type="button" class="admin-btn admin-btn-outline" onclick="closeDeleteUserModal();">Cancel</button>
                    <button type="submit" class="admin-btn admin-btn-primary" style="background:var(--admin-danger, #D64545);border-color:var(--admin-danger, #D64545);">Delete User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.js-delete-user-btn');
        if (!btn) return;
        document.getElementById('deleteUserName').textContent = btn.dataset.name || 'this user';
        document.getElementById('deleteUserForm').action = btn.dataset.action;
        document.getElementById('deleteUserModal').style.display = 'flex';
    });
    function closeDeleteUserModal() {
        document.getElementById('deleteUserModal').style.display = 'none';
    }
</script>
@endsection
