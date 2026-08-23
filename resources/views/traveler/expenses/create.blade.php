@extends('layouts.app')
@section('title', 'Scan Expense')

@push('styles')
<style>
    .dropzone-preview img { display: block; max-width: 100%; }
    .dz-preview-frame { position: relative; border-radius: 10px; overflow: hidden; }
    .dz-preview-frame img { display: block; width: 100%; max-height: 300px; object-fit: contain; background: var(--bg); }
    /* Full dim scrim + spinner over the receipt image WHILE the OCR call is
       in flight — replaces the old below-dropzone alert box, which used the
       global .alert-warning colors (hardcoded cream/brown) and looked badly
       out of place on the dark themes. This overlay is theme-neutral by
       design (a translucent black scrim reads fine on every theme). */
    .dz-scan-overlay {
        position: absolute; inset: 0; display: none; flex-direction: column;
        align-items: center; justify-content: center; gap: 10px;
        background: rgba(10, 12, 20, 0.62); backdrop-filter: blur(2px);
        color: #fff; font-size: 13px; font-weight: 600;
    }
    .dz-scan-overlay.is-active { display: flex; }
    .dz-scan-spinner {
        width: 42px; height: 42px; border-radius: 50%;
        background: rgba(255,255,255,0.14); display: flex; align-items: center;
        justify-content: center; font-size: 18px;
    }
    /* Post-scan result — a small themed pill instead of the full-width
       alert box, so it reads as a quick confirmation rather than a warning
       banner even when it IS reporting a scan failure. */
    .dz-scan-result {
        display: none; align-items: center; gap: 8px; margin-top: 12px;
        padding: 9px 14px; border-radius: var(--radius-sm);
        font-size: 13px; font-weight: 600; text-align: left;
    }
    .dz-scan-result.is-visible { display: flex; }
    .dz-scan-result-success { background: var(--primary-light); color: var(--primary); border: 1px solid var(--primary); }
    .dz-scan-result-warning { background: rgba(217,119,6,0.14); color: var(--warning); border: 1px solid var(--warning); }
    /* Briefly highlights a field OCR just filled in — cleared the moment
       the traveler edits it themselves, so the highlight always means
       "this came from the scan," never a stale leftover. */
    .form-control.ocr-filled { border-color: var(--primary); background: var(--primary-light); }
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

    /* Upload prompt — matches the profile photo dropzone. These live in a
       class rather than inline styles because the preview toggle resets
       promptEl.style.display to '', which would wipe an inline display:flex
       and leave the prompt stacked as a plain block when a file is cleared. */
    .dz-prompt {
        display: flex; flex-direction: column; align-items: center; justify-content: center;
        gap: 16px; text-align: center; cursor: pointer;
    }
    .dz-prompt-icon {
        width: 56px; height: 56px; border-radius: 16px; background: var(--primary-light);
        display: flex; align-items: center; justify-content: center;
        color: var(--primary); font-size: 22px; flex-shrink: 0;
    }
    /* .dropzone's own background turns --primary-light on hover/drag/scan —
       the same value as the tile — so the icon square vanished into it. */
    .dropzone:hover .dz-prompt-icon,
    .dropzone.drag-over .dz-prompt-icon,
    .dropzone.ocr-active .dz-prompt-icon { background: var(--bg-white); }
    .dz-prompt-title { font-size: 16px; font-weight: 700; color: var(--dark); }
    .dz-prompt-title span { color: var(--primary); }
    .dz-prompt-hint { font-size: 12px; color: var(--muted); margin-top: 4px; }
    .dz-format-tags { display: flex; gap: 8px; margin-top: 14px; }
    .dz-format-tag {
        font-size: 11px; font-weight: 700; letter-spacing: .02em; color: var(--muted);
        background: var(--bg-white); border: 1px solid var(--border); border-radius: 999px;
        padding: 4px 11px;
    }
    .dropzone:hover .dz-prompt-icon i,
    .dropzone.drag-over .dz-prompt-icon i { transform: translateY(-2px); }
    .dz-prompt-icon i { transition: transform .2s ease; }

    /* Single column: the dropzone sits full-width above the form instead
       of beside it — one thing to look at at a time, top to bottom,
       rather than two panels competing for attention side by side. */
    .scan-layout { display: flex; flex-direction: column; gap: 24px; }
    .scan-layout #dropZone { min-height: 340px; }
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

{{-- margin:auto, not 0 auto — .dash-content is a flex column, so auto block
     margins split its leftover height evenly above and below instead of
     letting all the slack pile up underneath the cards. --}}
<div style="max-width:1180px;width:100%;margin:auto;">
    {{-- One unified card instead of two separate boxes sitting side by
         side — a single header/intro makes this read as one "add an
         expense" step, with the receipt scan just being how you can
         optionally fill it in, not a disconnected second panel. --}}
    <div class="card expense-form-card"><div class="card-body" style="padding:32px;">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:4px;">
            <div style="width:38px;height:38px;border-radius:10px;background:var(--primary-light);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="fa-solid fa-receipt" style="color:var(--primary);font-size:15px;"></i>
            </div>
            <h3 style="font-size:20px;margin:0;">Add Expense</h3>
        </div>
        <p class="text-muted mb-16" style="font-size:14px;">Upload a receipt to auto-fill the details below, or enter them yourself.</p>

        @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        <div class="scan-layout">
            {{-- Drop zone --}}
            <div>
                <div class="dropzone" id="dropZone">
                    {{-- A <label for> opens the picker natively, so the whole
                         prompt is clickable without the extra Select File button. --}}
                    <label for="receiptFile" id="dropzonePrompt" class="dz-prompt">
                        <div class="dz-prompt-icon"><i class="fa-solid fa-cloud-arrow-up"></i></div>
                        <div>
                            <div class="dz-prompt-title"><span>Click to upload</span> a receipt</div>
                            <div class="dz-prompt-hint">or drag and drop it in here</div>
                            <div class="dz-format-tags">
                                <span class="dz-format-tag">PNG</span>
                                <span class="dz-format-tag">JPG</span>
                                <span class="dz-format-tag">Up to 10MB</span>
                            </div>
                        </div>
                    </label>
                    <input type="file" id="receiptFile" accept="image/*,application/pdf" style="display:none;">

                    <div class="dropzone-preview" id="dropzonePreview">
                        <div class="dz-preview-frame">
                            <img id="dropzonePreviewImg" alt="Receipt preview">
                            <div class="dz-scan-overlay" id="ocrScanOverlay">
                                <div class="dz-scan-spinner"><i class="fa-solid fa-spinner fa-spin"></i></div>
                                <div>Scanning receipt...</div>
                            </div>
                        </div>
                        <div id="ocrStatus" class="dz-scan-result"></div>
                        <button type="button" id="dropzoneClear" class="btn btn-outline btn-sm" style="margin-top:14px;">
                            <i class="fa-solid fa-arrow-rotate-left"></i> Choose a different file
                        </button>
                    </div>
                </div>
            </div>

            {{-- Expense Details form --}}
            <form method="POST" action="{{ route('expenses.store') }}" enctype="multipart/form-data" id="expenseForm">
                @csrf

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                    <div class="form-group">
                        <label class="form-label" for="trip_id">Trip</label>
                        <div class="input-wrapper">
                            <span class="input-icon"><i class="fa-solid fa-route"></i></span>
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
                        </div>
                        @error('trip_id')<div class="form-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="amount">Amount ({{ currency_symbol() }})</label>
                        <div class="input-wrapper">
                            <span class="input-icon"><i class="fa-solid fa-coins"></i></span>
                            <input type="number" id="amount" name="amount" step="0.01" min="0.01"
                                   value="{{ old('amount') }}"
                                   class="form-control {{ $errors->has('amount') ? 'is-invalid' : '' }}"
                                   placeholder="0.00" required>
                        </div>
                        @error('amount')<div class="form-error">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                    <div class="form-group">
                        <label class="form-label" for="category">Category</label>
                        <div class="input-wrapper">
                            <span class="input-icon"><i class="fa-solid fa-tag"></i></span>
                            <select name="category" id="category"
                                    class="form-control {{ $errors->has('category') ? 'is-invalid' : '' }}" required>
                                <option value="">Select category</option>
                                @foreach ($categories as $cat)
                                <option value="{{ $cat }}" {{ old('category') === $cat ? 'selected' : '' }}>{{ $cat }}</option>
                                @endforeach
                            </select>
                        </div>
                        @error('category')<div class="form-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="expense_date">Date</label>
                        <div class="input-wrapper">
                            <span class="input-icon"><i class="fa-solid fa-calendar-days"></i></span>
                            <input type="date" id="expense_date" name="expense_date"
                                   value="{{ old('expense_date', date('Y-m-d')) }}" min="2000-01-01" max="2099-12-31"
                                   class="form-control {{ $errors->has('expense_date') ? 'is-invalid' : '' }}" required>
                        </div>
                        @error('expense_date')<div class="form-error">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="description">Description / Merchant</label>
                    <div class="input-wrapper">
                        <span class="input-icon"><i class="fa-solid fa-store"></i></span>
                        <input type="text" id="description" name="description"
                               value="{{ old('description') }}"
                               class="form-control" placeholder="e.g. L'Osteria Pizza">
                    </div>
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
    var scanOverlay    = document.getElementById('ocrScanOverlay');
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
        scanOverlay.classList.remove('is-active');
        statusDiv.classList.remove('is-visible');
        [amountInput, dateInput, descInput, categorySelect].forEach(function (field) {
            if (field) field.classList.remove('ocr-filled');
        });
    }

    clearBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        clearPreview();
    });

    function setStatus(message, isWarning) {
        statusDiv.innerHTML = '<i class="fa-solid ' + (isWarning ? 'fa-triangle-exclamation' : 'fa-circle-check') + '"></i> ' + message;
        statusDiv.classList.toggle('dz-scan-result-warning', isWarning);
        statusDiv.classList.toggle('dz-scan-result-success', !isWarning);
        statusDiv.classList.add('is-visible');
    }

    // Briefly highlights a field OCR just filled, clearing the highlight
    // the moment the traveler touches that field themselves so it never
    // looks like a stale leftover from an earlier scan.
    function markOcrFilled(field) {
        if (!field) return;
        field.classList.add('ocr-filled');
        var clear = function () { field.classList.remove('ocr-filled'); };
        field.addEventListener('input', clear, { once: true });
        field.addEventListener('change', clear, { once: true });
    }

    function scanReceipt(file) {
        scanOverlay.classList.add('is-active');
        statusDiv.classList.remove('is-visible');
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
                scanOverlay.classList.remove('is-active');
                dropZone.classList.remove('ocr-active');
                if (data.amount)      { amountInput.value = data.amount; markOcrFilled(amountInput); }
                if (data.date)        { dateInput.value   = data.date;   markOcrFilled(dateInput); }
                if (data.description) { descInput.value   = data.description; markOcrFilled(descInput); }
                if (data.category && categorySelect) { categorySelect.value = data.category; markOcrFilled(categorySelect); }

                if (data.amount) {
                    setStatus('Receipt scanned — details filled in below.', false);
                } else {
                    setStatus("Couldn't auto-read this receipt. Please fill in the details manually.", true);
                }
                setTimeout(function () { statusDiv.classList.remove('is-visible'); }, 4000);
            })
            .catch(function (err) {
                scanOverlay.classList.remove('is-active');
                dropZone.classList.remove('ocr-active');
                setStatus((err && err.message) || 'Scan failed. Please fill in the details manually.', true);
                setTimeout(function () { statusDiv.classList.remove('is-visible'); }, 5000);
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

    // Several fields are now wrapped in .input-wrapper (icon + control), so
    // the error message's insertion point can't just be "next to the field"
    // anymore — it has to sit after the WRAPPER, matching where the
    // server-rendered @@error() block already renders (a sibling of
    // .input-wrapper, not of the input/select itself), or the live and
    // server-side errors would end up in different places.
    function errorAnchor(field) {
        return field.closest('.input-wrapper') || field;
    }

    function showFieldError(field, message) {
        field.classList.add('is-invalid');
        var anchor = errorAnchor(field);
        var msg = anchor.nextElementSibling;
        if (!msg || !msg.classList.contains('form-error-live')) {
            msg = document.createElement('div');
            msg.className = 'form-error form-error-live';
            anchor.insertAdjacentElement('afterend', msg);
        }
        msg.textContent = message;
    }

    function clearFieldError(field) {
        field.classList.remove('is-invalid');
        var anchor = errorAnchor(field);
        var msg = anchor.nextElementSibling;
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
