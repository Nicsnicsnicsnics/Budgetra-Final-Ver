<?php
namespace App\Http\Controllers\Traveler;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\Trip;

class AlertController extends Controller
{
    public function index()
    {
        $user  = auth()->user();
        $trips = $user->trips()->orderByDesc('start_date')->get();

        // Default to the latest trip if no trip_id specified
        $tripId     = request('trip_id');
        $activeTrip = $tripId ? $trips->find($tripId) : $trips->first();

        // Unread notification count per trip, for the badge on each trip pill.
        $unreadCounts = $user->notifications()
            ->where('is_read', false)
            ->whereNotNull('trip_id')
            ->selectRaw('trip_id, count(*) as count')
            ->groupBy('trip_id')
            ->pluck('count', 'trip_id');

        $query = $user->notifications()->with('trip')->latest();

        if ($trips->isEmpty()) {
            $query->whereRaw('1=0');
        } elseif ($activeTrip) {
            $query->where('trip_id', $activeTrip->id);
        }

        $notifications = $query->paginate(20);

        return view('traveler.alerts.index', compact('notifications', 'trips', 'activeTrip', 'unreadCounts'));
    }

    public function markRead(Notification $notification)
    {
        abort_if($notification->user_id !== auth()->id(), 403);
        $notification->update(['is_read' => true]);
        return back();
    }

    public function markAllRead()
    {
        auth()->user()->notifications()->where('is_read', false)->update(['is_read' => true]);
        return back();
    }
}
