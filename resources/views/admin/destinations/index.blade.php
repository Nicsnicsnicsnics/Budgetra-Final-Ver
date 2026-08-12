@extends('layouts.admin')
@section('content')
<div class="admin-page-head">
    <div>
        <h1>Manage Destinations</h1>
        <p>Configure travel data for global locations.</p>
    </div>
    <div class="admin-page-head-actions">
        <form method="GET" class="admin-search-form">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search destinations..." class="admin-input">
            <button type="submit" class="admin-btn admin-btn-outline"><i class="fa-solid fa-filter"></i> Search</button>
        </form>
        @if($missingImageCount > 0)
        <form method="POST" action="{{ route('admin.destinations.fill-missing-images') }}" id="fillImagesForm">
            @csrf
            <button type="submit" class="admin-btn admin-btn-primary" id="fillImagesBtn">
                <i class="fa-solid fa-cloud-arrow-down"></i> Fill Missing Images ({{ $missingImageCount }})
            </button>
        </form>
        @endif
    </div>
</div>
@if(session('success'))<div class="admin-alert-success">{{ session('success') }}</div>@endif
@if(session('error'))<div class="admin-alert-error">{{ session('error') }}</div>@endif

<div class="admin-card-grid">
    @forelse($destinations as $dest)
    <div class="admin-dest-card">
        <div class="admin-dest-card-img" style="{{ $dest->image ? 'background-image:url(' . asset('storage/' . $dest->image) . ')' : '' }}">
            @unless($dest->image)
                <i class="fa-solid fa-image"></i>
            @endunless
        </div>
        <div class="admin-dest-card-body">
            <div class="admin-card-head">
                <h3>{{ $dest->name }}</h3>
                @if($dest->active_trip_count > 0)
                    <span class="admin-badge admin-badge-success">ACTIVE TRIP</span>
                @endif
            </div>
            @if($dest->country)
                <div class="admin-dest-card-country"><i class="fa-solid fa-location-dot"></i> {{ $dest->country }}</div>
            @endif
            <p>{{ $dest->description ?: 'No description yet.' }}</p>
            <div class="admin-dest-card-stat">
                <span>Attractions</span>
                <strong>{{ $dest->attractions_count }} Active</strong>
            </div>
            <div class="admin-dest-card-foot">
                <span class="admin-dest-card-edited">Last edit: {{ $dest->updated_at->diffForHumans() }}</span>
                <button type="button" class="admin-btn admin-btn-outline admin-btn-sm js-edit-destination-btn"
                    data-action="{{ route('admin.destinations.update', $dest) }}"
                    data-fetch-action="{{ route('admin.destinations.fetch-image', $dest) }}"
                    data-name="{{ $dest->name }}"
                    data-country="{{ $dest->country }}"
                    data-description="{{ $dest->description }}"
                    data-image="{{ $dest->image ? asset('storage/' . $dest->image) : '' }}">
                    Edit Info
                </button>
            </div>
        </div>
    </div>
    @empty
    <div class="admin-empty-state">No destinations found.</div>
    @endforelse
</div>
<div class="admin-pagination">{{ $destinations->links() }}</div>

<div id="destinationModal" class="admin-modal-backdrop" style="display:none;" onclick="if(event.target===this)closeDestinationModal();">
    <div class="admin-modal-card">
        <div class="admin-modal-head">
            <h3>Edit Destination</h3>
            <button type="button" class="admin-modal-close" onclick="closeDestinationModal();"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form id="destinationForm" method="POST" enctype="multipart/form-data">
            @csrf @method('PUT')
            <div class="admin-form-group">
                <label>Destination Name</label>
                <input type="text" name="name" id="destModalName" class="admin-input" required>
            </div>
            <div class="admin-form-group">
                <label>Country</label>
                <input type="text" name="country" id="destModalCountry" class="admin-input">
            </div>
            <div class="admin-form-group">
                <label>Description</label>
                <textarea name="description" id="destModalDescription" class="admin-input" rows="4"></textarea>
            </div>
            <div class="admin-form-group">
                <label>Featured Image</label>
                <img id="destModalImagePreview" src="" alt="" style="display:none;height:70px;border-radius:8px;margin-bottom:8px;">
                <label class="admin-file-drop">
                    <input type="file" name="image" accept="image/*" style="display:none;" onchange="this.closest('.admin-file-drop').querySelector('span').textContent = this.files[0]?.name || 'Click to upload or drag and drop';">
                    <i class="fa-solid fa-upload"></i>
                    <span>Click to upload or drag and drop</span>
                    <small>PNG, JPG up to 10MB</small>
                </label>
                <button type="button" id="destFetchImageBtn" class="admin-btn admin-btn-outline admin-btn-sm" style="margin-top:8px;">
                    <i class="fa-solid fa-cloud-arrow-down"></i> Fetch Photo via API
                </button>
            </div>
            <div class="admin-modal-actions">
                <button type="button" class="admin-btn admin-btn-outline" onclick="closeDestinationModal();">Cancel</button>
                <button type="submit" class="admin-btn admin-btn-primary">Save Changes</button>
            </div>
        </form>
        <form id="destinationFetchImageForm" method="POST" style="display:none;">@csrf</form>
    </div>
</div>

<script>
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.js-edit-destination-btn');
        if (!btn) return;
        document.getElementById('destModalName').value = btn.dataset.name || '';
        document.getElementById('destModalCountry').value = btn.dataset.country || '';
        document.getElementById('destModalDescription').value = btn.dataset.description || '';
        document.getElementById('destinationForm').action = btn.dataset.action;
        document.getElementById('destinationFetchImageForm').action = btn.dataset.fetchAction;

        const preview = document.getElementById('destModalImagePreview');
        if (btn.dataset.image) {
            preview.src = btn.dataset.image;
            preview.style.display = 'block';
        } else {
            preview.style.display = 'none';
        }

        document.getElementById('destinationModal').style.display = 'flex';
    });
    function closeDestinationModal() {
        document.getElementById('destinationModal').style.display = 'none';
    }
    document.getElementById('destFetchImageBtn').addEventListener('click', function () {
        this.disabled = true;
        this.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Fetching...';
        document.getElementById('destinationFetchImageForm').submit();
    });
    const fillImagesForm = document.getElementById('fillImagesForm');
    if (fillImagesForm) {
        fillImagesForm.addEventListener('submit', function () {
            const btn = document.getElementById('fillImagesBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Fetching photos… this can take a minute';
        });
    }
</script>
@endsection
