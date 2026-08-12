<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attraction;
use App\Models\Destination;
use App\Models\DestinationCost;
use App\Models\User;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $active = 'dashboard';
        $stats = [
            'users'        => User::where('role', '!=', 'banned')->count(),
            'destinations' => Destination::count(),
            'attractions'  => Attraction::count(),
            'travelCosts'  => DestinationCost::count(),
        ];
        return view('admin.dashboard', compact('active', 'stats'));
    }
}
