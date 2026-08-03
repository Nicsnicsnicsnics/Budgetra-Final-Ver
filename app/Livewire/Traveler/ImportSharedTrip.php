<?php
namespace App\Livewire\Traveler;

use App\Models\Itinerary;
use App\Models\Trip;
use Livewire\Component;

class ImportSharedTrip extends Component
{
    public string $error = '';
    public string $code  = '';
    public bool $showCodeInput = false;

    public ?int $previewTripId = null;
    public string $previewDestination = '';
    public string $previewDates = '';
    public array $previewItinerary = []; // [['id'=>,'title'=>,'type'=>,'when'=>,'location'=>]]
    public array $selectedItineraryIds = [];

    private function fetchGalleryTrips()
    {
        return Trip::where('is_shared', true)
            ->where('user_id', '!=', auth()->id())
            ->with('user')
            ->latest()
            ->limit(8)
            ->get();
    }

    private function loadPreview(Trip $trip): void
    {
        $items = $trip->itinerary()->orderBy('start_datetime')->get();

        $this->previewTripId = $trip->id;
        $this->previewDestination = $trip->destination;
        $this->previewDates = $trip->start_date->format('M j') . ' – ' . $trip->end_date->format('M j, Y');
        $this->previewItinerary = $items->map(fn (Itinerary $item) => [
            'id'       => $item->id,
            'title'    => $item->title,
            'type'     => $item->type,
            'when'     => $item->start_datetime->format('M j, g:i A'),
            'location' => $item->location,
        ])->toArray();
        $this->selectedItineraryIds = $items->pluck('id')->map(fn ($id) => (string) $id)->toArray();
    }

    public function previewTrip(int $tripId): void
    {
        $this->error = '';
        $trip = Trip::where('id', $tripId)->where('is_shared', true)->first();

        if (!$trip || $trip->user_id === auth()->id()) {
            $this->error = 'This shared trip is no longer available.';
            return;
        }

        $this->loadPreview($trip);
    }

    public function toggleCodeInput(): void
    {
        $this->showCodeInput = !$this->showCodeInput;
        $this->error = '';
        $this->code  = '';
    }

    public function lookupCode(): void
    {
        $this->error = '';
        $code = strtoupper(trim($this->code));

        if ($code === '') {
            $this->error = 'Enter a share code.';
            return;
        }

        $trip = Trip::where('share_code', $code)->first();

        if (!$trip) {
            $this->error = 'No trip found with that code.';
            return;
        }
        if ($trip->user_id === auth()->id()) {
            $this->error = "That's your own trip — you can't import it.";
            return;
        }

        $this->loadPreview($trip);
    }

    public function selectAll(): void
    {
        $this->selectedItineraryIds = collect($this->previewItinerary)->pluck('id')->map(fn ($id) => (string) $id)->toArray();
    }

    public function selectNone(): void
    {
        $this->selectedItineraryIds = [];
    }

    public function cancel(): void
    {
        $this->reset(['error', 'code', 'showCodeInput', 'previewTripId', 'previewDestination', 'previewDates', 'previewItinerary', 'selectedItineraryIds']);
    }

    public function confirmImport()
    {
        $sourceTrip = Trip::find($this->previewTripId);

        if (!$sourceTrip || $sourceTrip->user_id === auth()->id()) {
            $this->error = 'This shared trip is no longer available.';
            $this->cancel();
            return;
        }

        $newTrip = auth()->user()->trips()->create([
            'destination'       => $sourceTrip->destination,
            'trip_name'         => $sourceTrip->trip_name,
            'start_date'        => $sourceTrip->start_date,
            'end_date'          => $sourceTrip->end_date,
            'num_travelers'     => $sourceTrip->num_travelers,
            'budget_limit'      => $sourceTrip->budget_limit,
            'travel_type'       => $sourceTrip->travel_type,
            'notes'             => $sourceTrip->notes,
            'origin'            => $sourceTrip->origin,
            'origin_code'       => $sourceTrip->origin_code,
            'destination_code'  => $sourceTrip->destination_code,
            'cover_image'       => $sourceTrip->cover_image,
        ]);

        $selectedIds = array_map('intval', $this->selectedItineraryIds);

        $sourceTrip->itinerary()
            ->whereIn('id', $selectedIds)
            ->get()
            ->each(fn (Itinerary $item) => $newTrip->itinerary()->create([
                'title'          => $item->title,
                'type'           => $item->type,
                'start_datetime' => $item->start_datetime,
                'end_datetime'   => $item->end_datetime,
                'location'       => $item->location,
                'notes'          => $item->notes,
            ]));

        session()->flash('success', 'Trip to ' . $sourceTrip->destination . ' imported with ' . count($selectedIds) . ' itinerary item(s).');

        return redirect()->route('dashboard');
    }

    public function render()
    {
        return view('livewire.traveler.import-shared-trip', [
            'galleryTrips' => $this->previewTripId ? collect() : $this->fetchGalleryTrips(),
        ]);
    }
}
