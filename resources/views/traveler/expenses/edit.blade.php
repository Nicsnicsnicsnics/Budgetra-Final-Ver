@extends('layouts.app')
@section('title', 'Edit Expense')

@push('styles')
<style>
    .edit-expense-card { max-width: 560px; margin: 0 auto; animation: expenseEditIn .3s ease both; }
    @keyframes expenseEditIn { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: translateY(0); } }
</style>
@endpush

@section('content')

<div class="edit-expense-card">
    <div style="display:flex;align-items:center;justify-content:space-between;" class="mb-24">
        <div>
            <h1 style="font-size:22px;">Edit Expense</h1>
            <p class="text-muted" style="font-size:13px;">Update the details for this expense.</p>
        </div>
        <a href="{{ route('expenses.index') }}" class="btn btn-outline btn-sm">← Back</a>
    </div>

    @if ($errors->any())
    <div class="alert alert-danger">
        <ul style="margin:0;padding-left:1.2em;">
            @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul>
    </div>
    @endif

    <div class="card"><div class="card-body">
        <form method="POST" action="{{ route('expenses.update', $expense) }}">
            @csrf @method('PUT')

            <div class="form-group">
                <label class="form-label" for="trip_id">Trip</label>
                <select name="trip_id" id="trip_id" class="form-control {{ $errors->has('trip_id') ? 'is-invalid' : '' }}" required>
                    @foreach ($trips as $trip)
                    <option value="{{ $trip->id }}" {{ old('trip_id', $expense->trip_id) == $trip->id ? 'selected' : '' }}>
                        {{ $trip->destination }} ({{ $trip->start_date->format('M Y') }})
                    </option>
                    @endforeach
                </select>
                @error('trip_id')<div class="form-error">{{ $message }}</div>@enderror
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                <div class="form-group">
                    <label class="form-label" for="amount">Amount (₱)</label>
                    <input type="number" step="0.01" min="0.01" id="amount" name="amount"
                           class="form-control {{ $errors->has('amount') ? 'is-invalid' : '' }}"
                           value="{{ old('amount', $expense->amount) }}" required>
                    @error('amount')<div class="form-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label" for="category">Category</label>
                    <select name="category" id="category" class="form-control {{ $errors->has('category') ? 'is-invalid' : '' }}" required>
                        @foreach ($categories as $cat)
                        <option value="{{ $cat }}" {{ old('category', $expense->category) === $cat ? 'selected' : '' }}>{{ $cat }}</option>
                        @endforeach
                    </select>
                    @error('category')<div class="form-error">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="description">Description</label>
                <input type="text" id="description" name="description" class="form-control"
                       value="{{ old('description', $expense->description) }}" placeholder="e.g. L'Osteria Pizza">
            </div>

            <div class="form-group">
                <label class="form-label" for="expense_date">Date</label>
                <input type="date" id="expense_date" name="expense_date"
                       class="form-control {{ $errors->has('expense_date') ? 'is-invalid' : '' }}"
                       value="{{ old('expense_date', $expense->expense_date->format('Y-m-d')) }}" required>
                @error('expense_date')<div class="form-error">{{ $message }}</div>@enderror
            </div>

            @if ($expense->receipt_path)
            <div class="form-group">
                <label class="form-label">Receipt</label>
                <div style="display:flex;align-items:center;gap:10px;background:var(--bg);border:1px solid var(--border);border-radius:var(--radius-sm);padding:10px 14px;">
                    <i class="fa-solid fa-paperclip" style="color:var(--primary);"></i>
                    <a href="{{ Storage::url($expense->receipt_path) }}" target="_blank" style="font-size:13px;font-weight:600;">View attached receipt</a>
                </div>
            </div>
            @endif

            <div style="display:flex;gap:10px;" class="mt-8">
                <button type="submit" class="btn btn-primary" style="flex:1;">
                    <i class="fa-solid fa-floppy-disk"></i> Update Expense
                </button>
                <a href="{{ route('expenses.index') }}" class="btn btn-outline">Cancel</a>
            </div>
        </form>
    </div></div>
</div>
@endsection
