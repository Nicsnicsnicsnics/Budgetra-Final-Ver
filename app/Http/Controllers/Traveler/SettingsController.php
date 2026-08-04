<?php
namespace App\Http\Controllers\Traveler;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function edit()
    {
        return view('traveler.settings.index', ['user' => auth()->user()]);
    }

    public function updateTheme(Request $request)
    {
        $validated = $request->validate([
            'theme' => 'required|in:daylight,nightflight,terracotta,retro-wanderlust,sakura-bloom,auto',
        ]);

        auth()->user()->update($validated);

        if ($request->wantsJson()) {
            return response()->json(['theme' => $validated['theme']]);
        }

        return back()->with('success', 'Theme updated.');
    }
}
