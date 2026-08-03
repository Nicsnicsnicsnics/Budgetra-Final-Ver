<?php
namespace App\Http\Controllers\Traveler;

use App\Http\Controllers\Controller;
use App\Models\Trip;

class TripShareController extends Controller
{
    public function toggle(Trip $trip)
    {
        abort_if($trip->user_id !== auth()->id(), 403);

        $trip->update(['is_shared' => !$trip->is_shared]);

        return back()->with('success', $trip->is_shared
            ? 'Trip shared — other travelers can now find it on their Dashboard.'
            : 'Trip is now private.');
    }
}
