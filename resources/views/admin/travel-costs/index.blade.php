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
                    <form method="POST" action="{{ route('admin.travel-costs.destroy', $dest) }}" onsubmit="return confirm('Delete this travel cost entry?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="admin-icon-btn admin-icon-btn-danger" title="Delete"><i class="fa-solid fa-trash"></i></button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="admin-table-empty">No travel cost entries yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="admin-pagination">{{ $destinations->links() }}</div>
@endsection
