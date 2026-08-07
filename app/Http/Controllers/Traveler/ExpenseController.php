<?php
namespace App\Http\Controllers\Traveler;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ExpenseController extends Controller
{
    private const CATEGORIES = ['Transportation','Accommodation','Food','Activities','Shopping','Emergency Expenses'];

    public function index(Request $request)
    {
        $user  = auth()->user();
        $trips = $user->trips()->latest()->get()
            ->filter(fn ($t) => in_array($t->resolved_status, ['active', 'upcoming'], true))
            ->values();
        $query = $user->expenses()->with('trip')->latest('expense_date');

        // The page is built around viewing one trip's expenses at a time
        // (destination selector, single-trip "Add Expense" link) — default
        // to the first trip whenever none is specified, not just when
        // there's exactly one. Matches the same default the view already
        // assumes for which destination looks "selected".
        $tripId = $request->filled('trip_id') ? $request->trip_id : $trips->first()?->id;

        if ($tripId)                       $query->where('trip_id', $tripId);
        if ($request->filled('category'))  $query->where('category', $request->category);
        // strtotime() guards against a malformed date reaching the query —
        // on Postgres (the real database), comparing a date column against
        // a string that isn't a valid date throws a QueryException instead
        // of just matching nothing, crashing the whole page over what
        // should just be an ignorable bad filter value.
        if ($request->filled('date_from') && strtotime($request->date_from) !== false) {
            $query->where('expense_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to') && strtotime($request->date_to) !== false) {
            $query->where('expense_date', '<=', $request->date_to);
        }

        $expenses   = $query->paginate(20)->withQueryString();
        $categories = self::CATEGORIES;

        return view('traveler.expenses.index', compact('expenses', 'trips', 'categories'));
    }

    public function create()
    {
        $trips      = auth()->user()->trips()->latest()->get()
            ->filter(fn ($t) => in_array($t->resolved_status, ['active', 'upcoming'], true))
            ->values();
        $categories = self::CATEGORIES;
        return view('traveler.expenses.create', compact('trips', 'categories'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'trip_id'      => 'required|exists:trips,id',
            'amount'       => 'required|numeric|min:0.01',
            'category'     => 'required|in:' . implode(',', self::CATEGORIES),
            'description'  => 'nullable|string|max:500',
            'expense_date' => 'required|date',
            'receipt'      => 'nullable|image|mimes:jpeg,png,jpg,webp|max:10240',
        ]);

        abort_if(
            !auth()->user()->trips()->where('id', $validated['trip_id'])->exists(),
            403
        );

        if ($request->hasFile('receipt')) {
            $validated['receipt_path'] = $request->file('receipt')->store('receipts', 'public');
        }
        unset($validated['receipt']);

        $validated['user_id'] = auth()->id();
        $expense = Expense::create($validated);
        \App\Observers\ExpenseObserver::syncBudgetForExpense($expense);

        return redirect()->route('expenses.index')->with('success', 'Expense recorded.');
    }

    public function edit(Expense $expense)
    {
        abort_if($expense->user_id !== auth()->id(), 403);
        $trips      = auth()->user()->trips()->latest()->get();
        $categories = self::CATEGORIES;
        return view('traveler.expenses.edit', compact('expense', 'trips', 'categories'));
    }

    public function update(Request $request, Expense $expense)
    {
        abort_if($expense->user_id !== auth()->id(), 403);

        $validated = $request->validate([
            'trip_id'      => 'required|exists:trips,id',
            'amount'       => 'required|numeric|min:0.01',
            'category'     => 'required|in:' . implode(',', self::CATEGORIES),
            'description'  => 'nullable|string|max:500',
            'expense_date' => 'required|date',
        ]);

        // exists:trips,id above only checks the trip is real, not that it's
        // this traveler's — same check store() already applies, needed here
        // too since trip_id can be changed on edit, not just set once.
        abort_if(
            !auth()->user()->trips()->where('id', $validated['trip_id'])->exists(),
            403
        );

        $expense->update($validated);

        return redirect()->route('expenses.index')->with('success', 'Expense updated.');
    }

    public function destroy(Expense $expense)
    {
        abort_if($expense->user_id !== auth()->id(), 403);

        if ($expense->receipt_path) {
            Storage::disk('public')->delete($expense->receipt_path);
        }

        $expense->delete();
        return redirect()->route('expenses.index')->with('success', 'Expense deleted.');
    }

    public function ocr(Request $request, \App\Services\OcrService $ocrService)
    {
        $request->validate(['receipt' => 'required|file|mimes:jpeg,png,jpg,webp,pdf|max:10240']);
        $result = $ocrService->scan($request->file('receipt'), auth()->id());

        if (! auth()->user()->ocr_auto_categorize) {
            unset($result['category']);
        }

        return response()->json($result);
    }
}
