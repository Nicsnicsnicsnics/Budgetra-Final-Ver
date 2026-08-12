@extends('layouts.admin')
@section('content')
<div class="admin-page-head">
    <div>
        <h1>Tourist Attractions</h1>
        <p>Global overview and budgetary management for curated points of interest.</p>
    </div>
    <div class="admin-page-head-actions">
        @if($missingImageCount > 0)
        <form method="POST" action="{{ route('admin.attractions.fill-missing-images') }}" id="fillAttrImagesForm">
            @csrf
            <button type="submit" class="admin-btn admin-btn-outline" id="fillAttrImagesBtn">
                <i class="fa-solid fa-cloud-arrow-down"></i> Fill Missing Images ({{ $missingImageCount }})
            </button>
        </form>
        @endif
        <a href="{{ route('admin.attractions.create') }}" class="admin-btn admin-btn-primary"><i class="fa-solid fa-plus"></i> Add Attraction</a>
    </div>
</div>
@if(session('success'))<div class="admin-alert-success">{{ session('success') }}</div>@endif
@if(session('error'))<div class="admin-alert-error">{{ session('error') }}</div>@endif

<div class="admin-tabs">
    <a href="{{ route('admin.attractions.index') }}" class="admin-tab {{ request('category') ? '' : 'active' }}">All</a>
    @foreach($categories as $cat)
        <a href="{{ route('admin.attractions.index', ['category' => $cat]) }}" class="admin-tab {{ request('category') === $cat ? 'active' : '' }}">{{ $cat }}</a>
    @endforeach
</div>

<div class="admin-card-grid">
    @forelse($attractions as $attr)
    <div class="admin-attr-card">
        <div class="admin-attr-card-img" style="{{ $attr->image ? 'background-image:url(' . asset('storage/' . $attr->image) . ')' : '' }}">
            @unless($attr->image)
                <i class="fa-solid fa-image"></i>
            @endunless
            <button type="button" class="admin-attr-card-edit js-edit-attraction-btn"
                data-action="{{ route('admin.attractions.update', $attr) }}"
                data-fetch-action="{{ route('admin.attractions.fetch-image', $attr) }}"
                data-name="{{ $attr->name }}"
                data-destination="{{ $attr->destination }}"
                data-category="{{ $attr->category }}"
                data-rating="{{ $attr->rating }}"
                data-description="{{ $attr->description }}"
                data-image="{{ $attr->image ? asset('storage/' . $attr->image) : '' }}"
                title="Edit"><i class="fa-solid fa-pen"></i></button>
        </div>
        <div class="admin-attr-card-body">
            <div class="admin-card-head">
                <h3>{{ $attr->name }}</h3>
                @if($attr->category)<span class="admin-badge admin-badge-outline">{{ strtoupper($attr->category) }}</span>@endif
            </div>
            <div class="admin-dest-card-country"><i class="fa-solid fa-location-dot"></i> {{ $attr->destination }}</div>
            <p>{{ $attr->description ?: 'No description yet.' }}</p>
            <div class="admin-attr-card-foot">
                <span>{{ $attr->rating ? number_format($attr->rating, 1) . ' ★' : 'Unrated' }}</span>
                <form method="POST" action="{{ route('admin.attractions.destroy', $attr) }}" onsubmit="return confirm('Delete this attraction?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="admin-icon-btn admin-icon-btn-danger" title="Delete"><i class="fa-solid fa-trash"></i></button>
                </form>
            </div>
        </div>
    </div>
    @empty
    <div class="admin-empty-state">No attractions found.</div>
    @endforelse
</div>
<div class="admin-pagination">{{ $attractions->links() }}</div>

<div id="attractionModal" class="admin-modal-backdrop" style="display:none;" onclick="if(event.target===this)closeAttractionModal();">
    <div class="admin-modal-card">
        <div class="admin-modal-head">
            <h3>Edit Attraction</h3>
            <button type="button" class="admin-modal-close" onclick="closeAttractionModal();"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form id="attractionForm" method="POST" enctype="multipart/form-data">
            @csrf @method('PUT')
            <div class="admin-form-row">
                <div class="admin-form-group">
                    <label>Attraction Name</label>
                    <input type="text" name="name" id="attrModalName" class="admin-input" required>
                </div>
                <div class="admin-form-group">
                    <label>Destination</label>
                    <input type="text" name="destination" id="attrModalDestination" class="admin-input" required>
                </div>
            </div>
            <div class="admin-form-row">
                <div class="admin-form-group">
                    <label>Category</label>
                    <input type="text" name="category" id="attrModalCategory" class="admin-input">
                </div>
                <div class="admin-form-group">
                    <label>Rating (0–5)</label>
                    <input type="number" step="0.1" min="0" max="5" name="rating" id="attrModalRating" class="admin-input">
                </div>
            </div>
            <div class="admin-form-group">
                <label>Description</label>
                <textarea name="description" id="attrModalDescription" class="admin-input" rows="4"></textarea>
            </div>
            <div class="admin-form-group">
                <label>Featured Image</label>
                <img id="attrModalImagePreview" src="" alt="" style="display:none;height:70px;border-radius:8px;margin-bottom:8px;">
                <label class="admin-file-drop">
                    <input type="file" name="image" accept="image/*" style="display:none;" onchange="this.closest('.admin-file-drop').querySelector('span').textContent = this.files[0]?.name || 'Click to upload or drag and drop';">
                    <i class="fa-solid fa-upload"></i>
                    <span>Click to upload or drag and drop</span>
                    <small>PNG, JPG up to 10MB</small>
                </label>
                <button type="button" id="attrFetchImageBtn" class="admin-btn admin-btn-outline admin-btn-sm" style="margin-top:8px;">
                    <i class="fa-solid fa-cloud-arrow-down"></i> Fetch Photo via API
                </button>
            </div>
            <div class="admin-modal-actions">
                <button type="button" class="admin-btn admin-btn-outline" onclick="closeAttractionModal();">Cancel</button>
                <button type="submit" class="admin-btn admin-btn-primary">Save Changes</button>
            </div>
        </form>
        <form id="attractionFetchImageForm" method="POST" style="display:none;">@csrf</form>
    </div>
</div>

<script>
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.js-edit-attraction-btn');
        if (!btn) return;
        document.getElementById('attrModalName').value = btn.dataset.name || '';
        document.getElementById('attrModalDestination').value = btn.dataset.destination || '';
        document.getElementById('attrModalCategory').value = btn.dataset.category || '';
        document.getElementById('attrModalRating').value = btn.dataset.rating || '';
        document.getElementById('attrModalDescription').value = btn.dataset.description || '';
        document.getElementById('attractionForm').action = btn.dataset.action;
        document.getElementById('attractionFetchImageForm').action = btn.dataset.fetchAction;
        const preview = document.getElementById('attrModalImagePreview');
        if (btn.dataset.image) {
            preview.src = btn.dataset.image;
            preview.style.display = 'block';
        } else {
            preview.style.display = 'none';
        }
        document.getElementById('attractionModal').style.display = 'flex';
    });
    function closeAttractionModal() {
        document.getElementById('attractionModal').style.display = 'none';
    }
    document.getElementById('attrFetchImageBtn').addEventListener('click', function () {
        this.disabled = true;
        this.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Fetching...';
        document.getElementById('attractionFetchImageForm').submit();
    });
    const fillAttrImagesForm = document.getElementById('fillAttrImagesForm');
    if (fillAttrImagesForm) {
        fillAttrImagesForm.addEventListener('submit', function () {
            const btn = document.getElementById('fillAttrImagesBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Fetching photos… this can take a minute';
        });
    }
</script>
@endsection
