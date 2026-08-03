<?php
namespace App\Http\Controllers\Traveler;

use App\Http\Controllers\Controller;
use App\Models\Attraction;
use App\Models\Trip;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $user = auth()->user();
        $recommended = Attraction::withCount(['reviews' => fn ($q) => $q->where('status', 'active')])
            ->orderByDesc('rating')->limit(4)->get();

        if (!$user) {
            return view('traveler.dashboard.index', ['trips' => collect(), 'totalBudget' => 0, 'totalSpent' => 0, 'recommended' => $recommended]);
        }

        $trips = $user->trips()->withSum('expenses', 'amount')->latest()->get()->map(function (Trip $trip) {
            $spent = $trip->expenses_sum_amount ?? 0;
            $today = Carbon::today();
            $trip->setAttribute('total_spent', $spent);
            $trip->setAttribute('pct_used', $trip->budget_limit > 0 ? round($spent / $trip->budget_limit * 100) : 0);
            $trip->setAttribute('status',
                $trip->start_date->gt($today) ? 'upcoming' :
                ($trip->end_date->lt($today) ? 'past' : 'active'));
            return $trip;
        });
        $totalBudget = $trips->sum('budget_limit');
        $totalSpent  = $trips->sum('total_spent');
        return view('traveler.dashboard.index', compact('trips', 'totalBudget', 'totalSpent', 'recommended'));
    }
}
