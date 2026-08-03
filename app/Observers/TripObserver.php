<?php
namespace App\Observers;

use App\Models\Notification;
use App\Models\Trip;

class TripObserver
{
    public function created(Trip $trip): void
    {
        Notification::create([
            'user_id' => $trip->user_id,
            'trip_id' => $trip->id,
            'type'    => 'trip_created',
            'message' => "🎉 Congratulations! Your trip to {$trip->destination} has been saved.",
            'is_read' => false,
        ]);
    }
}
