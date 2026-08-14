@extends('layouts.admin')
@section('content')
<div class="admin-page-head">
    <div>
        <h1>Travel Costs</h1>
        <p>Average cost benchmarks used to budget trips per destination.</p>
    </div>
    <a href="{{ route('admin.travel-costs.create') }}" class="admin-btn admin-btn-primary"><i class="fa-solid fa-plus"></i> Add Travel Cost</a>
</div>
@if(session('success'))<div class="admin-alert-success">{{ session('success') }}</div>@endif
<div class="admin-card">
    <table class="admin-table">
        <thead><tr><th>Destination</th><th>Cost Level</th><th>Multiplier</th><th>Category</th><th>Actions</th></tr></thead>
        <tbody>
        @forelse($destinations as $dest)
            <tr>
                <td>{{ $dest->destination }}</td>
                <td>{{ $dest->cost_level }}</td>
                <td>{{ $dest->multiplier }}&times;</td>
                <td>{{ $dest->category ?? '—' }}</td>
                <td class="admin-table-actions">
                    <a href="{{ route('admin.travel-costs.edit', $dest) }}" class="admin-icon-btn" title="Edit"><i class="fa-solid fa-pen"></i></a>
                    <button type="button" class="admin-icon-btn admin-icon-btn-danger js-delete-travelcost-btn"
                        data-action="{{ route('admin.travel-costs.destroy', $dest) }}"
                        data-name="{{ $dest->destination }}"
                        title="Delete"><i class="fa-solid fa-trash"></i></button>
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="admin-table-empty">No travel cost entries yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="admin-pagination">{{ $destinations->links() }}</div>

<div id="deleteTravelCostModal" class="admin-modal-backdrop" style="display:none;" onclick="if(event.target===this)closeDeleteTravelCostModal();">
    <div class="admin-modal-card">
        <div class="admin-modal-head">
            <h3>Delete Travel Cost</h3>
            <button type="button" class="admin-modal-close" onclick="closeDeleteTravelCostModal();"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div style="padding:20px 24px;">
            <p style="margin:0 0 20px;font-size:14px;color:var(--admin-muted, #8A7A6C);line-height:1.6;">
                Delete the travel cost entry for <strong id="deleteTravelCostName" style="color:var(--admin-ink, #241208);"></strong>? This cannot be undone.
            </p>
            <form id="deleteTravelCostForm" method="POST">
                @csrf @method('DELETE')
                <div class="admin-modal-actions">
                    <button type="button" class="admin-btn admin-btn-outline" onclick="closeDeleteTravelCostModal();">Cancel</button>
                    <button type="submit" class="admin-btn admin-btn-primary" style="background:var(--admin-danger, #D64545);border-color:var(--admin-danger, #D64545);">Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.js-delete-travelcost-btn');
        if (!btn) return;
        document.getElementById('deleteTravelCostName').textContent = btn.dataset.name || 'this entry';
        document.getElementById('deleteTravelCostForm').action = btn.dataset.action;
        document.getElementById('deleteTravelCostModal').style.display = 'flex';
    });
    function closeDeleteTravelCostModal() {
        document.getElementById('deleteTravelCostModal').style.display = 'none';
    }
</script>
@endsection
