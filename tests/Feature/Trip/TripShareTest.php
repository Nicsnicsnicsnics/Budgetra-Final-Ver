<?php
namespace Tests\Feature\Trip;

use App\Livewire\Traveler\ImportSharedTrip;
use App\Livewire\Traveler\SavedTrips;
use App\Models\Itinerary;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TripShareTest extends TestCase
{
    use RefreshDatabase;

    private function makeSharedTrip(User $owner): Trip
    {
        $trip = Trip::factory()->create([
            'user_id'     => $owner->id,
            'destination' => 'Cebu City',
            'trip_name'   => 'Cebu Adventure',
            'is_shared'   => true,
        ]);

        Itinerary::create(['trip_id' => $trip->id, 'title' => 'Flight to Cebu', 'type' => 'Flight', 'start_datetime' => '2026-08-01 06:00:00', 'location' => 'MNL-CEB']);
        Itinerary::create(['trip_id' => $trip->id, 'title' => 'Check-in Hotel', 'type' => 'Hotel', 'start_datetime' => '2026-08-01 12:00:00', 'location' => 'Cebu Hotel']);

        return $trip;
    }

    public function test_toggling_share_flips_is_shared_and_only_the_owner_can_do_it(): void
    {
        $owner    = User::factory()->create();
        $intruder = User::factory()->create();
        $trip     = Trip::factory()->create(['user_id' => $owner->id, 'is_shared' => false]);

        $this->actingAs($intruder)->post(route('trips.share', $trip))->assertStatus(403);
        $this->assertFalse($trip->fresh()->is_shared);

        $this->actingAs($owner)->post(route('trips.share', $trip));
        $this->assertTrue($trip->fresh()->is_shared);

        $this->actingAs($owner)->post(route('trips.share', $trip));
        $this->assertFalse($trip->fresh()->is_shared);
    }

    public function test_gallery_only_shows_other_travelers_shared_trips(): void
    {
        $owner    = User::factory()->create();
        $viewer   = User::factory()->create();
        $shared   = $this->makeSharedTrip($owner);
        $private  = Trip::factory()->create(['user_id' => $owner->id, 'is_shared' => false]);
        $ownTrip  = Trip::factory()->create(['user_id' => $viewer->id, 'is_shared' => true]);

        $galleryIds = Livewire::actingAs($viewer)->test(ImportSharedTrip::class)
            ->viewData('galleryTrips')
            ->pluck('id');

        $this->assertTrue($galleryIds->contains($shared->id));
        $this->assertFalse($galleryIds->contains($private->id));
        $this->assertFalse($galleryIds->contains($ownTrip->id));
    }

    public function test_preview_trip_loads_itinerary_and_rejects_own_or_unshared_trip(): void
    {
        $owner    = User::factory()->create();
        $importer = User::factory()->create();
        $trip     = $this->makeSharedTrip($owner);

        Livewire::actingAs($importer)->test(ImportSharedTrip::class)
            ->call('previewTrip', $trip->id)
            ->assertSet('previewTripId', $trip->id)
            ->assertCount('previewItinerary', 2)
            ->assertSet('error', '');

        Livewire::actingAs($owner)->test(ImportSharedTrip::class)
            ->call('previewTrip', $trip->id)
            ->assertSet('previewTripId', null)
            ->assertSee('no longer available');

        $unshared = Trip::factory()->create(['user_id' => $owner->id, 'is_shared' => false]);
        Livewire::actingAs($importer)->test(ImportSharedTrip::class)
            ->call('previewTrip', $unshared->id)
            ->assertSet('previewTripId', null)
            ->assertSee('no longer available');
    }

    public function test_confirm_import_copies_only_selected_itinerary_items_to_a_new_trip(): void
    {
        $owner    = User::factory()->create();
        $importer = User::factory()->create();
        $trip     = $this->makeSharedTrip($owner);
        $flightId = $trip->itinerary()->where('title', 'Flight to Cebu')->first()->id;

        Livewire::actingAs($importer)->test(ImportSharedTrip::class)
            ->call('previewTrip', $trip->id)
            ->assertCount('previewItinerary', 2)
            ->set('selectedItineraryIds', [(string) $flightId])
            ->call('confirmImport')
            ->assertRedirect(route('dashboard'));

        $this->assertDatabaseCount('trips', 2);
        $newTrip = Trip::where('user_id', $importer->id)->first();
        $this->assertNotNull($newTrip);
        $this->assertSame('Cebu City', $newTrip->destination);
        $this->assertFalse($newTrip->is_shared);
        $this->assertSame(1, $newTrip->itinerary()->count());
        $this->assertSame('Flight to Cebu', $newTrip->itinerary()->first()->title);

        // Original trip and its itinerary are untouched.
        $this->assertSame(2, $trip->fresh()->itinerary()->count());
    }

    public function test_confirm_import_does_not_copy_budgets_expenses_or_group_members(): void
    {
        $owner    = User::factory()->create();
        $importer = User::factory()->create();
        $trip     = $this->makeSharedTrip($owner);
        $trip->budgets()->create(['category' => 'Food', 'estimated_cost' => 1000]);

        Livewire::actingAs($importer)->test(ImportSharedTrip::class)
            ->call('previewTrip', $trip->id)
            ->call('confirmImport');

        $newTrip = Trip::where('user_id', $importer->id)->first();
        $this->assertSame(0, $newTrip->budgets()->count());
        $this->assertSame(0, $newTrip->expenses()->count());
        $this->assertSame(0, $newTrip->groupMembers()->count());
    }

    public function test_saved_trips_generates_and_persists_a_share_code(): void
    {
        $owner = User::factory()->create();
        $trip  = Trip::factory()->create(['user_id' => $owner->id]);

        Livewire::actingAs($owner)->test(SavedTrips::class)
            ->call('showShareCode', $trip->id)
            ->assertSet('shareCodeTripId', $trip->id);

        $trip->refresh();
        $this->assertNotNull($trip->share_code);
        $this->assertSame(8, strlen($trip->share_code));

        $codeAfterFirstOpen = $trip->share_code;
        Livewire::actingAs($owner)->test(SavedTrips::class)->call('showShareCode', $trip->id);
        $this->assertSame($codeAfterFirstOpen, $trip->fresh()->share_code);
    }

    public function test_lookup_code_works_even_when_trip_is_not_publicly_shared(): void
    {
        $owner    = User::factory()->create();
        $importer = User::factory()->create();
        $trip     = $this->makeSharedTrip($owner);
        $trip->update(['is_shared' => false, 'share_code' => Trip::generateUniqueShareCode()]);

        // Doesn't show in the public gallery...
        $galleryIds = Livewire::actingAs($importer)->test(ImportSharedTrip::class)
            ->viewData('galleryTrips')->pluck('id');
        $this->assertFalse($galleryIds->contains($trip->id));

        // ...but a valid code still finds it (private sharing channel).
        Livewire::actingAs($importer)->test(ImportSharedTrip::class)
            ->set('code', $trip->share_code)
            ->call('lookupCode')
            ->assertSet('previewTripId', $trip->id)
            ->assertCount('previewItinerary', 2);
    }

    public function test_lookup_code_rejects_own_trip_and_unknown_code(): void
    {
        $owner    = User::factory()->create();
        $importer = User::factory()->create();
        $trip     = $this->makeSharedTrip($owner);
        $trip->update(['share_code' => Trip::generateUniqueShareCode()]);

        Livewire::actingAs($owner)->test(ImportSharedTrip::class)
            ->set('code', $trip->share_code)
            ->call('lookupCode')
            ->assertSet('previewTripId', null)
            ->assertSee("can't import it");

        Livewire::actingAs($importer)->test(ImportSharedTrip::class)
            ->set('code', 'NOTREAL1')
            ->call('lookupCode')
            ->assertSet('previewTripId', null)
            ->assertSee('No trip found');
    }
}
