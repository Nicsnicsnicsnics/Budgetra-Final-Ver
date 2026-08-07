<?php
namespace App\Livewire\Traveler;

use App\Models\Expense;
use App\Models\Trip;
use Carbon\Carbon;
use Livewire\Component;

class MultiTripHub extends Component
{
    public string $search         = '';
    public ?int   $detailTripId   = null;
    public array  $compareIds     = [];
    public bool   $showComparison = false;

    private array $compareCategories = ['Transportation', 'Accommodation', 'Food', 'Tourist Attractions'];

    public function showDetail(int $id): void
    {
        $this->detailTripId = $this->detailTripId === $id ? null : $id;
    }

    public function closeDetail(): void
    {
        $this->detailTripId = null;
    }

    // Two-step selection: clicking a card's Compare button toggles it in/out
    // of the pick list (max 2 — picking a third drops the oldest pick) and
    // highlights the card, rather than instantly pairing it with whatever
    // other trip happens to be first. The actual comparison only opens once
    // two cards are picked, via the floating bar's own Compare button.
    public function toggleCompare(int $tripId): void
    {
        if (in_array($tripId, $this->compareIds, true)) {
            $this->compareIds = array_values(array_diff($this->compareIds, [$tripId]));
            return;
        }

        if (count($this->compareIds) >= 2) {
            array_shift($this->compareIds);
        }
        $this->compareIds[] = $tripId;
    }

    public function runComparison(): void
    {
        if (count($this->compareIds) === 2) {
            $this->showComparison = true;
        }
    }

    public function closeComparison(): void
    {
        $this->showComparison = false;
    }

    public function clearCompareSelection(): void
    {
        $this->showComparison = false;
        $this->compareIds     = [];
    }

    private function fetchTrips()
    {
        $query = auth()->user()->trips()->withSum('expenses', 'amount')->latest('start_date');
        if ($this->search) {
            $query->where('destination', 'like', "%{$this->search}%");
        }
        return $query->get()->map(function (Trip $trip) {
            $today = Carbon::today();
            $spent = $trip->expenses_sum_amount ?? 0;
            $days  = max(1, (int) $trip->start_date->diffInDays($trip->end_date));
            $trip->setAttribute('total_spent', (float) $spent);
            $trip->setAttribute('pct_used',    $trip->budget_limit > 0 ? round($spent / $trip->budget_limit * 100) : 0);
            $trip->setAttribute('days',        $days);
            $trip->setAttribute('status',
                $trip->start_date->gt($today) ? 'upcoming' :
                ($trip->end_date->lt($today)  ? 'past'     : 'active'));
            return $trip;
        });
    }

    private function fetchCompareData(\Illuminate\Support\Collection $trips): array
    {
        if (count($this->compareIds) !== 2) return [];

        return array_map(function ($id) use ($trips) {
            $categories = [];
            foreach ($this->compareCategories as $cat) {
                $categories[$cat] = (float) Expense::where('trip_id', $id)->where('category', $cat)->sum('amount');
            }
            return [
                'trip'       => $trips->firstWhere('id', $id),
                'categories' => $categories,
            ];
        }, $this->compareIds);
    }

    public function render()
    {
        $trips = $this->fetchTrips();
        $totals = [
            'count'  => $trips->count(),
            'budget' => $trips->sum('budget_limit'),
            'spent'  => $trips->sum('total_spent'),
        ];
        $detailTrip  = $this->detailTripId ? $trips->firstWhere('id', $this->detailTripId) : null;
        $compareData = $this->fetchCompareData($trips);

        return view('livewire.traveler.multi-trip-hub', compact('trips', 'totals', 'detailTrip', 'compareData'))
            ->layout('layouts.app', ['title' => 'Multi Trip Hub', 'active' => 'multi-trips']);
    }
}
