<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attraction;
use App\Services\PlaceImageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

class AttractionController extends Controller
{
    public function index(Request $request)
    {
        $active = 'attractions';
        $missingImageCount = Attraction::whereNull('image')->count();
        $query = Attraction::query();
        if ($request->filled('destination')) {
            $query->where('destination', $request->destination);
        }
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }
        $attractions = $query->orderBy('destination')->orderBy('name')->paginate(24)->withQueryString();
        $categories  = Attraction::whereNotNull('category')->distinct()->orderBy('category')->pluck('category');
        return view('admin.attractions.index', compact('attractions', 'active', 'categories', 'missingImageCount'));
    }

    public function create()
    {
        $active = 'attractions';
        return view('admin.attractions.create', compact('active'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'destination' => 'required|string|max:255',
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'category'    => 'nullable|string|max:100',
            'rating'      => 'nullable|numeric|min:0|max:5',
            'image'       => 'nullable|image|max:5120',
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $filename  = Str::replace(' ', '_', $validated['name']) . '.' . $request->file('image')->getClientOriginalExtension();
            $request->file('image')->storeAs('attraction-images', $filename, 'public');
            $imagePath = 'attraction-images/' . $filename;
        }

        Attraction::create([
            'destination' => $validated['destination'],
            'name'        => $validated['name'],
            'description' => $validated['description'] ?? null,
            'category'    => $validated['category'] ?? null,
            'rating'      => $validated['rating'] ?? 0,
            'image'       => $imagePath,
        ]);

        return redirect()->route('admin.attractions.index')->with('success', 'Attraction added.');
    }

    public function edit(Attraction $attraction)
    {
        $active = 'attractions';
        return view('admin.attractions.edit', compact('attraction', 'active'));
    }

    public function update(Request $request, Attraction $attraction)
    {
        $validated = $request->validate([
            'destination' => 'required|string|max:255',
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'category'    => 'nullable|string|max:100',
            'rating'      => 'nullable|numeric|min:0|max:5',
            'image'       => 'nullable|image|max:5120',
        ]);

        $updateData = [
            'destination' => $validated['destination'],
            'name'        => $validated['name'],
            'description' => $validated['description'] ?? null,
            'category'    => $validated['category'] ?? null,
            'rating'      => $validated['rating'] ?? $attraction->rating,
        ];

        if ($request->hasFile('image')) {
            $filename  = Str::replace(' ', '_', $validated['name']) . '.' . $request->file('image')->getClientOriginalExtension();
            $request->file('image')->storeAs('attraction-images', $filename, 'public');
            $updateData['image'] = 'attraction-images/' . $filename;
        }

        $attraction->update($updateData);
        return redirect()->route('admin.attractions.index')->with('success', 'Attraction updated.');
    }

    public function destroy(Attraction $attraction)
    {
        $attraction->delete();
        return redirect()->route('admin.attractions.index')->with('success', 'Attraction deleted.');
    }

    public function fetchImage(Attraction $attraction, PlaceImageService $images)
    {
        if (!$images->fetchForAttraction($attraction)) {
            return back()->with('error', 'Could not find or download a photo for this attraction right now.');
        }
        return redirect()->route('admin.attractions.index')->with('success', 'Photo fetched for ' . $attraction->name . '.');
    }

    // Runs the same batching + daily-quota-reserve logic as the scheduled
    // `app:fill-attraction-images` command, just triggered on demand.
    public function fillMissingImages()
    {
        set_time_limit(120);
        Artisan::call('app:fill-attraction-images', ['--limit' => 15]);
        return redirect()->route('admin.attractions.index')->with('success', trim(Artisan::output()));
    }
}
