<?php
namespace App\Http\Controllers\Traveler;

use App\Http\Controllers\Controller;
use App\Services\TripImportService;

class TripImportController extends Controller
{
    public function import(string $code, TripImportService $importer)
    {
        $sourceTrip = $importer->findByCode($code);

        if (!$sourceTrip) {
            return redirect()->route('saved-trips')->with('error', 'That share code or link is no longer valid.');
        }
        if ($sourceTrip->user_id === auth()->id()) {
            return redirect()->route('saved-trips')->with('error', "That's your own trip — you can't import it.");
        }
        if (!$importer->isShareable($sourceTrip)) {
            return redirect()->route('saved-trips')->with('error', 'This trip has nothing shareable saved on it.');
        }

        $importer->import($sourceTrip, auth()->user());

        return redirect()->route('saved-trips')->with('success', 'Trip imported!');
    }
}
