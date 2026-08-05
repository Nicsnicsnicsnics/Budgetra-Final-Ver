@extends('layouts.app')
@section('title', 'Scan Expense')

@push('styles')
<style>
    .dropzone-preview img { display: block; max-width: 100%; }
    .scan-item { transition: background .15s ease; border-radius: var(--radius-sm); }
    .scan-item:hover { background: var(--bg); }
    .scan-select-menu { position: absolute; top: calc(100% + 6px); left: 50%; transform: translateX(-50%); background: var(--bg-white); border: 1px solid var(--border); border-radius: var(--radius-sm); box-shadow: var(--shadow); min-width: 190px; z-index: 50; overflow: hidden; }
    .scan-select-option {
        display: flex; align-items: center; gap: 8px; width: 100%; padding: 11px 14px;
        background: none; border: none; text-align: left; font-size: 13px; font-weight: 600;
        color: var(--text); cursor: pointer; transition: background .15s ease; font-family: inherit;
    }
    .scan-select-option:hover { background: var(--bg); }
    .scan-select-option + .scan-select-option { border-top: 1px solid var(--border-light); }
    .scan-select-option i { color: var(--primary); width: 14px; }
    #dropZone { animation: expenseCardIn .3s ease both; }
    .expense-form-card { animation: expenseCardIn .35s ease both; }
    @keyframes expenseCardIn { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: translateY(0); } }

    /* .is-invalid is already used to flag failed server-side validation on
       every form in this app, but was never actually given a style anywhere
       — the class did nothing. This also backs the new inline (client-side,
       as-you-type) validation added on this page. */
    .form-control.is-invalid { border-color: var(--danger); }
    .form-control.is-invalid:focus { border-color: var(--danger); box-shadow: 0 0 0 3px rgba(220,38,38,0.12); }

    .scan-layout { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; align-items: stretch; }
    .scan-layout > div:first-child { display: flex; flex-direction: column; }
    .scan-layout #dropZone { flex: 1; }
    @media (max-width: 860px) { .scan-layout { grid-template-columns: 1fr; } }
</style>
@endpush

@section('content')

@if ($trips->isEmpty())
<div class="empty-state-center" style="min-height:70vh;">
    <div style="width:72px;height:72px;border-radius:20px;background:var(--primary-light);display:flex;align-items:center;justify-content:center;margin-bottom:20px;">
        <i class="fa-solid fa-receipt" style="font-size:32px;color:var(--primary);"></i>
    </div>
    <h2 style="font-weight:700;margin-bottom:8px;">No trips planned yet</h2>
    <p class="text-muted" style="max-width:320px;margin-bottom:24px;">Plan a trip first before logging your expenses.</p>
    <a href="{{ route('trips.plan') }}" class="btn btn-primary btn-lg">
        <i class="fa-solid fa-paper-plane"></i> Plan a Trip
    </a>
</div>
@else

<div class="mb-24" style="max-width:1400px;margin-left:auto;margin-right:auto;text-align:center;">
    <h1>Scan Your Receipt</h1>
    <p class="text-muted">Upload a photo of your receipt to automatically extract and track expenses.</p>
</div>

<div style="max-width:1400px;margin:0 auto;">
    <div class="scan-layout">
        {{-- Drop zone --}}
        <div>
            <div class="dropzone" id="dropZone">
                <div id="dropzonePrompt">
                    <div class="dropzone-icon"><i class="fa-solid fa-camera"></i></div>
                    <div class="dropzone-title">Drag &amp; Drop Receipt</div>
                    <div class="dropzone-sub">or click to browse from your computer</div>
                    <button type="button" class="btn btn-primary" onclick="document.getElementById('receiptFile').click()">
                        <i class="fa-solid fa-upload"></i> Select File
                    </button>
                </div>
                <input type="file" id="receiptFile" accept="image/*,application/pdf" style="display:none;">

                <div class="dropzone-preview" id="dropzonePreview">
                    <img id="dropzonePreviewImg" alt="Receipt preview">
                    <button type="button" id="dropzoneClear" class="btn btn-outline btn-sm" style="margin-top:14px;">
                        <i class="fa-solid fa-arrow-rotate-left"></i> Choose a different file
                    </button>
                </div>
            </div>

            <div id="ocrStatus" style="display:none;" class="alert alert-warning mt-8">
                <i class="fa-solid fa-spinner fa-spin"></i> Scanning receipt...
            </div>
        </div>

        {{-- Expense Details form --}}
        <div class="card expense-form-card"><div class="card-body" style="padding:32px;">
            <h3 class="mb-4" style="font-size:20px;">Expense Details</h3>
            <p class="text-muted mb-16" style="font-size:14px;">Fill in details below or let OCR auto-fill after scanning.</p>

            @if ($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('expenses.store') }}" enctype="multipart/form-data" id="expenseForm">
                @csrf

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                    <div class="form-group">
                        <label class="form-label" for="trip_id">Trip</label>
                        <select name="trip_id" id="trip_id"
                                class="form-control {{ $errors->has('trip_id') ? 'is-invalid' : '' }}" required>
                            <option value="">Select trip</option>
                            @foreach ($trips as $trip)
                            <option value="{{ $trip->id }}"
                                {{ (old('trip_id', request('trip_id')) == $trip->id) ? 'selected' : '' }}>
                                {{ $trip->destination }} ({{ $trip->start_date->format('M Y') }})
                            </option>
                            @endforeach
                        </select>
                        @error('trip_id')<div class="form-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="amount">Amount ({{ currency_symbol() }})</label>
                        <input type="number" id="amount" name="amount" step="0.01" min="0.01"
                               value="{{ old('amount') }}"
                               class="form-control {{ $errors->has('amount') ? 'is-invalid' : '' }}"
                               placeholder="0.00" required>
                        @error('amount')<div class="form-error">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                    <div class="form-group">
                        <label class="form-label" for="category">Category</label>
                        <select name="category" id="category"
                                class="form-control {{ $errors->has('category') ? 'is-invalid' : '' }}" required>
                            <option value="">Select category</option>
                            @foreach ($categories as $cat)
                            <option value="{{ $cat }}" {{ old('category') === $cat ? 'selected' : '' }}>{{ $cat }}</option>
                            @endforeach
                        </select>
                        @error('category')<div class="form-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="expense_date">Date</label>
                        <input type="date" id="expense_date" name="expense_date"
                               value="{{ old('expense_date', date('Y-m-d')) }}"
                               class="form-control {{ $errors->has('expense_date') ? 'is-invalid' : '' }}" required>
                        @error('expense_date')<div class="form-error">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="description">Description / Merchant</label>
                    <input type="text" id="description" name="description"
                           value="{{ old('description') }}"
                           class="form-control" placeholder="e.g. L'Osteria Pizza">
                </div>

                {{-- Hidden real file input for form submission --}}
                <input type="file" name="receipt" id="receiptSubmit" style="display:none;" accept="image/*,application/pdf">

                <button type="submit" class="btn btn-primary btn-lg btn-block mt-8">
                    <i class="fa-solid fa-floppy-disk"></i> Save Expense
                </button>
            </form>
        </div></div>
    </div>
</div>

@endif
@endsection

@push('scripts')
<script>
(function () {
    var dropZone       = document.getElementById('dropZone');
    var fileInput      = document.getElementById('receiptFile');
    var submitInput    = document.getElementById('receiptSubmit');
    var statusDiv      = document.getElementById('ocrStatus');
    var amountInput    = document.getElementById('amount');
    var dateInput      = document.getElementById('expense_date');
    var descInput      = document.getElementById('description');
    var categorySelect = document.getElementById('category');
    var promptEl       = document.getElementById('dropzonePrompt');
    var previewWrap    = document.getElementById('dropzonePreview');
    var previewImg     = document.getElementById('dropzonePreviewImg');
    var clearBtn       = document.getElementById('dropzoneClear');

    if (!dropZone) return; // no trips: the form isn't on the page at all

    function showPreview(file) {
        var reader = new FileReader();
        reader.onload = function (e) {
            previewImg.src = e.target.result;
            promptEl.style.display = 'none';
            previewWrap.style.display = 'block';
        };
        reader.readAsDataURL(file);
    }

    function clearPreview() {
        previewImg.src = '';
        previewWrap.style.display = 'none';
        promptEl.style.display = '';
        fileInput.value = '';
        submitInput.value = '';
    }

    clearBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        clearPreview();
    });

    function setStatus(message, isWarning) {
        statusDiv.innerHTML = isWarning
            ? '<i class="fa-solid fa-triangle-exclamation"></i> ' + message
            : '<i class="fa-solid fa-spinner fa-spin"></i> ' + message;
        statusDiv.classList.toggle('alert-warning', true);
        statusDiv.style.display = '';
    }

    function scanReceipt(file) {
        setStatus('Scanning receipt...', false);
        dropZone.classList.add('ocr-active');

        var formData = new FormData();
        formData.append('receipt', file);
        formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);

        fetch('/expenses/ocr', {
            method: 'POST',
            body: formData,
            // Without this, Laravel can't tell this is an AJAX request and
            // responds to a validation failure with an HTML redirect instead
            // of JSON — which would fail r.json() below with a parse error
            // rather than a real message.
            headers: { 'Accept': 'application/json' }
        })
            .then(function (r) {
                if (!r.ok) {
                    // Validation error (e.g. file too large/unsupported format) or
                    // server error — still valid JSON, just not OCR fields, so it
                    // has to be handled here rather than falling through silently.
                    return r.json().then(function (body) {
                        var msg = (body && body.errors && body.errors.receipt)
                            ? body.errors.receipt[0]
                            : "Couldn't scan this receipt.";
                        throw new Error(msg);
                    });
                }
                return r.json();
            })
            .then(function (data) {
                dropZone.classList.remove('ocr-active');
                if (data.amount)      amountInput.value = data.amount;
                if (data.date)        dateInput.value   = data.date;
                if (data.description) descInput.value   = data.description;
                if (data.category && categorySelect) categorySelect.value = data.category;

                if (data.amount) {
                    setStatus('Receipt scanned — details filled in below.', false);
                } else {
                    setStatus("Couldn't auto-read this receipt. Please fill in the details manually.", true);
                }
                setTimeout(function () { statusDiv.style.display = 'none'; }, 4000);
            })
            .catch(function (err) {
                dropZone.classList.remove('ocr-active');
                setStatus((err && err.message) || 'Scan failed. Please fill in the details manually.', true);
                setTimeout(function () { statusDiv.style.display = 'none'; }, 5000);
            });
    }

    function handleFile(file) {
        if (!file) return;

        // Copy the file to the real submit input so the form POST includes it.
        var dt = new DataTransfer();
        dt.items.add(file);
        submitInput.files = dt.files;

        showPreview(file);
        scanReceipt(file);
    }

    // Click to browse
    fileInput.addEventListener('change', function () { handleFile(this.files[0]); });

    // Drag and drop
    dropZone.addEventListener('dragover', function (e) {
        e.preventDefault(); this.classList.add('drag-over');
    });
    dropZone.addEventListener('dragleave', function () { this.classList.remove('drag-over'); });
    dropZone.addEventListener('drop', function (e) {
        e.preventDefault(); this.classList.remove('drag-over');
        handleFile(e.dataTransfer.files[0]);
    });
})();
</script>
<script>
(function () {
    var form = document.getElementById('expenseForm');
    if (!form) return;

    function showFieldError(field, message) {
        field.classList.add('is-invalid');
        var msg = field.nextElementSibling;
        if (!msg || !msg.classList.contains('form-error-live')) {
            msg = document.createElement('div');
            msg.className = 'form-error form-error-live';
            field.insertAdjacentElement('afterend', msg);
        }
        msg.textContent = message;
    }

    function clearFieldError(field) {
        field.classList.remove('is-invalid');
        var msg = field.nextElementSibling;
        if (msg && msg.classList.contains('form-error-live')) msg.remove();
    }

    function friendlyMessage(field) {
        if (field.validity.valueMissing) return 'This field is required.';
        if (field.name === 'amount' && field.validity.rangeUnderflow) return 'Amount must be greater than 0.';
        if (field.validity.badInput || field.validity.typeMismatch) return 'Please enter a valid value.';
        return field.validationMessage || 'Please check this field.';
    }

    function validateField(field) {
        if (field.checkValidity()) { clearFieldError(field); return true; }
        showFieldError(field, friendlyMessage(field));
        return false;
    }

    var fields = form.querySelectorAll('[required]');
    fields.forEach(function (field) {
        field.addEventListener('blur', function () { validateField(field); });
        field.addEventListener('input', function () {
            if (field.classList.contains('is-invalid')) validateField(field);
        });
        field.addEventListener('change', function () {
            if (field.classList.contains('is-invalid')) validateField(field);
        });
    });

    form.addEventListener('submit', function (e) {
        var allValid = true;
        fields.forEach(function (field) { if (!validateField(field)) allValid = false; });
        if (!allValid) { e.preventDefault(); return; }

        var btn = form.querySelector('button[type="submit"]');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
        }
    });
})();
</script>
@endpush
