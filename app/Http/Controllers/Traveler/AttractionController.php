<?php
namespace App\Http\Controllers\Traveler;

use App\Http\Controllers\Controller;
use App\Models\Attraction;
use App\Models\Review;

class AttractionController extends Controller
{
    public function index()
    {
        return view('traveler.attractions.index');
    }

    public function show(Attraction $attraction)
    {
        $reviews = Review::with(['user', 'helpfulVotes'])
            ->where('attraction_id', $attraction->id)
            ->where('status', 'active')
            ->latest()
            ->get();

        $avgRating = $reviews->avg('rating') ?? 0;
        $myReview  = auth()->user()->reviews()
            ->where('attraction_id', $attraction->id)
            ->first();
        $hasReviewed = (bool) $myReview;

        // "Matched estimate" = actual per-person spend landed within 20% of
        // the attraction's estimated_cost — only computed from reviews that
        // actually supplied both spent_amount and pax_count, and only shown
        // at all once the attraction has an estimate to compare against.
        // Null (not 0%) when there isn't enough real data yet, so the view
        // can tell "no data" apart from "everyone was way off".
        $reviewsWithSpend = $reviews->filter(fn ($r) => $r->spent_amount !== null && $r->pax_count);
        $costAccuracyPct  = null;
        if ($attraction->estimated_cost && $reviewsWithSpend->isNotEmpty()) {
            $matched = $reviewsWithSpend->filter(function ($r) use ($attraction) {
                $perPerson = (float) $r->spent_amount / max(1, $r->pax_count);
                return abs($perPerson - (float) $attraction->estimated_cost) / (float) $attraction->estimated_cost <= 0.20;
            })->count();
            $costAccuracyPct = (int) round($matched / $reviewsWithSpend->count() * 100);
        }

        return view('traveler.attractions.show', compact(
            'attraction', 'reviews', 'avgRating', 'hasReviewed', 'myReview',
            'costAccuracyPct', 'reviewsWithSpend'
        ));
    }
}
