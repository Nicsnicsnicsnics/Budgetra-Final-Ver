<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DestinationCost;
use Illuminate\Http\Request;

class TravelCostController extends Controller
{
    public function index()
    {
        $active = 'travel-costs';
        $destinations = DestinationCost::orderBy('destination')->paginate(25);
        return view('admin.travel-costs.index', compact('destinations', 'active'));
    }

    public function create()
    {
        $active = 'travel-costs';
        $costLevels = ['Budget-friendly', 'Moderate', 'Pricey', 'Very Expensive'];
        return view('admin.travel-costs.create', compact('costLevels', 'active'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'destination' => 'required|string|max:255|unique:destination_costs',
            'cost_level'  => 'required|in:Budget-friendly,Moderate,Pricey,Very Expensive',
            'multiplier'  => 'required|numeric|min:0.1|max:10',
            'category'    => 'nullable|string|max:100',
            'image_url'   => 'nullable|url|max:500',
            'description' => 'nullable|string|max:2000',
        ]);
        DestinationCost::create($validated);
        return redirect()->route('admin.travel-costs.index')->with('success', 'Travel cost added.');
    }

    public function edit(DestinationCost $travel_cost)
    {
        $active = 'travel-costs';
        $costLevels = ['Budget-friendly', 'Moderate', 'Pricey', 'Very Expensive'];
        $destination = $travel_cost;
        return view('admin.travel-costs.edit', compact('destination', 'costLevels', 'active'));
    }

    public function update(Request $request, DestinationCost $travel_cost)
    {
        $validated = $request->validate([
            'destination' => "required|string|max:255|unique:destination_costs,destination,{$travel_cost->id}",
            'cost_level'  => 'required|in:Budget-friendly,Moderate,Pricey,Very Expensive',
            'multiplier'  => 'required|numeric|min:0.1|max:10',
            'category'    => 'nullable|string|max:100',
            'image_url'   => 'nullable|url|max:500',
            'description' => 'nullable|string|max:2000',
        ]);
        $travel_cost->update($validated);
        return redirect()->route('admin.travel-costs.index')->with('success', 'Travel cost updated.');
    }

    public function destroy(DestinationCost $travel_cost)
    {
        $travel_cost->delete();
        return redirect()->route('admin.travel-costs.index')->with('success', 'Travel cost deleted.');
    }
}
