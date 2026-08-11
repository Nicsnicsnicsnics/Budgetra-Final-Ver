<?php
namespace App\Observers;

use App\Models\MomentPhoto;
use App\Models\Notification;
use App\Models\Trip;
use Illuminate\Support\Facades\Storage;

class TripObserver
{
    public function created(Trip $trip): void
    {
        Notification::create([
            'user_id' => $trip->user_id,
            'trip_id' => $trip->id,
            'type'    => 'trip_created',
            'message' => "Congratulations! Your trip to {$trip->destination} has been saved.",
            'is_read' => false,
        ]);
    }

    // expenses/moments/moment_photos all cascade-delete at the DB level
    // (see their cascadeOnDelete() foreign keys) when a trip is deleted —
    // that never touches the actual uploaded files on disk. The single-item
    // delete paths (ExpenseController::destroy(), MomentService::deleteMoment())
    // already clean those up individually, but nothing did so when a whole
    // trip goes at once. Runs on "deleting" (before the cascade fires, not
    // "deleted"/after), so the trip's child rows are still queryable here.
    public function deleting(Trip $trip): void
    {
        $paths = $trip->expenses()->whereNotNull('receipt_path')->pluck('receipt_path')
            ->merge(MomentPhoto::whereIn('moment_id', $trip->moments()->pluck('id'))->pluck('photo_path'));

        if ($paths->isNotEmpty()) {
            Storage::disk('public')->delete($paths->all());
        }
    }
}
