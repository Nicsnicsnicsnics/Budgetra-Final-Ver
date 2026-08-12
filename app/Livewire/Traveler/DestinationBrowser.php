<?php
namespace App\Livewire\Traveler;

use App\Models\Destination;
use Livewire\Component;

class DestinationBrowser extends Component
{
    public string $search  = '';
    public string $country = '';

    // Only surfaces destinations the admin has actually curated (a
    // description or photo set) — of the ~260 pre-seeded reference cities,
    // most are still bare name/country rows with nothing worth showing a
    // traveler yet. This also makes the connection tangible: a destination
    // only appears here once an admin edit gives it real content.
    public function getDestinationsProperty()
    {
        $query = Destination::withCount('attractions')
            ->where(fn ($q) => $q->whereNotNull('description')->orWhereNotNull('image'));
        if ($this->search)  $query->where('name', 'like', "%{$this->search}%");
        if ($this->country) $query->where('country', $this->country);
        return $query->orderBy('name')->get();
    }

    public function getCountriesProperty()
    {
        return Destination::whereNotNull('country')
            ->where(fn ($q) => $q->whereNotNull('description')->orWhereNotNull('image'))
            ->orderBy('country')->pluck('country')->unique()->values();
    }

    public function render()
    {
        return view('livewire.traveler.destination-browser');
    }
}
