<?php
namespace Tests\Feature\Livewire;

use App\Livewire\Traveler\ItineraryManager;
use App\Models\Itinerary;
use App\Models\Moment;
use App\Models\MomentPhoto;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ItineraryManagerTest extends TestCase
{
    use RefreshDatabase;

    private function makeTrip(User $user): Trip
    {
        return Trip::factory()->create([
            'user_id'    => $user->id,
            'start_date' => now()->toDateString(),
            'end_date'   => now()->addDays(3)->toDateString(),
        ]);
    }

    public function test_itinerary_page_loads(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/itinerary')->assertStatus(200);
    }

    public function test_component_mounts_with_first_trip(): void
    {
        $user = User::factory()->create();
        $trip = $this->makeTrip($user);
        Livewire::actingAs($user)
            ->test(ItineraryManager::class)
            ->assertSet('selectedTripId', $trip->id);
    }

    public function test_selecting_trip_loads_days(): void
    {
        $user = User::factory()->create();
        $trip = $this->makeTrip($user);
        Livewire::actingAs($user)
            ->test(ItineraryManager::class)
            ->call('selectTrip', $trip->id)
            ->assertSet('selectedTripId', $trip->id);
    }

    public function test_selecting_a_day(): void
    {
        $user = User::factory()->create();
        $trip = $this->makeTrip($user);
        $date = now()->toDateString();
        Livewire::actingAs($user)
            ->test(ItineraryManager::class)
            ->call('selectDay', $date)
            ->assertSet('selectedDate', $date);
    }

    public function test_toggle_add_panel(): void
    {
        $user = User::factory()->create();
        Livewire::actingAs($user)
            ->test(ItineraryManager::class)
            ->assertSet('showPanel', false)
            ->call('togglePanel')
            ->assertSet('showPanel', true);
    }

    // ── Moments: travel pins ────────────────────────────────

    public function test_add_pin_creates_moment_with_coordinates(): void
    {
        $user = User::factory()->create();
        $trip = $this->makeTrip($user);

        Livewire::actingAs($user)
            ->test(ItineraryManager::class, ['tab' => 'moments'])
            ->call('selectTrip', $trip->id)
            ->call('openAddPinModal', 10.3157, 123.8854)
            ->assertSet('showPinModal', true)
            ->assertSet('pinModalMode', 'add')
            ->set('pinPlaceName', "Magellan's Cross")
            ->set('pinDescription', 'Historic landmark')
            ->set('pinVisitedDate', '2026-08-01')
            ->call('savePin')
            ->assertSet('showPinModal', false)
            ->assertDispatched('pin-saved');

        $this->assertDatabaseHas('moments', [
            'trip_id'    => $trip->id,
            'place_name' => "Magellan's Cross",
        ]);
        $moment = Moment::where('trip_id', $trip->id)->first();
        $this->assertEqualsWithDelta(10.3157, (float) $moment->lat, 0.0001);
        $this->assertEqualsWithDelta(123.8854, (float) $moment->lng, 0.0001);
    }

    public function test_add_pin_with_multiple_photos_stores_all_of_them(): void
    {
        Storage::fake('public');
        $user   = User::factory()->create();
        $trip   = $this->makeTrip($user);
        $photos = [UploadedFile::fake()->image('spot1.jpg'), UploadedFile::fake()->image('spot2.jpg')];

        Livewire::actingAs($user)
            ->test(ItineraryManager::class, ['tab' => 'moments'])
            ->call('selectTrip', $trip->id)
            ->call('openAddPinModal', 10.0, 123.0)
            ->set('pinPlaceName', 'Beach')
            ->set('pinVisitedDate', '2026-08-02')
            ->set('pinPhotos', $photos)
            ->call('savePin');

        $moment = Moment::where('trip_id', $trip->id)->first();
        $this->assertCount(2, $moment->photos);
        foreach ($moment->photos as $photo) {
            Storage::disk('public')->assertExists($photo->photo_path);
        }
    }

    public function test_remove_new_photo_before_saving_excludes_it(): void
    {
        Storage::fake('public');
        $user   = User::factory()->create();
        $trip   = $this->makeTrip($user);
        $photos = [UploadedFile::fake()->image('keep.jpg'), UploadedFile::fake()->image('drop.jpg')];

        Livewire::actingAs($user)
            ->test(ItineraryManager::class, ['tab' => 'moments'])
            ->call('selectTrip', $trip->id)
            ->call('openAddPinModal', 10.0, 123.0)
            ->set('pinPlaceName', 'Beach')
            ->set('pinVisitedDate', '2026-08-02')
            ->set('pinPhotos', $photos)
            ->call('removeNewPhoto', 1) // drop the second selection
            ->assertCount('pinPhotos', 1)
            ->call('savePin');

        $moment = Moment::where('trip_id', $trip->id)->first();
        $this->assertCount(1, $moment->photos);
    }

    public function test_remove_existing_photo_deletes_file_and_row(): void
    {
        Storage::fake('public');
        $user   = User::factory()->create();
        $trip   = $this->makeTrip($user);
        $moment = Moment::create([
            'trip_id' => $trip->id, 'place_name' => 'Spot',
            'visited_date' => '2026-08-01', 'lat' => 10.0, 'lng' => 123.0,
        ]);
        $path = 'moment-photos/existing.jpg';
        Storage::disk('public')->put($path, 'fake-content');
        $photo = MomentPhoto::create(['moment_id' => $moment->id, 'photo_path' => $path]);

        Livewire::actingAs($user)
            ->test(ItineraryManager::class, ['tab' => 'moments'])
            ->call('selectTrip', $trip->id)
            ->call('openEditPinModal', $moment->id)
            ->assertCount('pinExistingPhotos', 1)
            ->call('removeExistingPhoto', $photo->id)
            ->assertCount('pinExistingPhotos', 0);

        $this->assertDatabaseMissing('moment_photos', ['id' => $photo->id]);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_edit_pin_updates_existing_moment(): void
    {
        $user   = User::factory()->create();
        $trip   = $this->makeTrip($user);
        $moment = Moment::create([
            'trip_id' => $trip->id, 'place_name' => 'Old Name',
            'visited_date' => '2026-08-01', 'lat' => 10.0, 'lng' => 123.0,
        ]);

        Livewire::actingAs($user)
            ->test(ItineraryManager::class, ['tab' => 'moments'])
            ->call('selectTrip', $trip->id)
            ->call('openEditPinModal', $moment->id)
            ->assertSet('pinModalMode', 'edit')
            ->assertSet('pinPlaceName', 'Old Name')
            ->set('pinPlaceName', 'New Name')
            ->call('savePin');

        $this->assertDatabaseHas('moments', ['id' => $moment->id, 'place_name' => 'New Name']);
        $this->assertDatabaseCount('moments', 1); // edit, not a duplicate insert
    }

    public function test_delete_pin_removes_moment_and_its_photos(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $trip = $this->makeTrip($user);
        $path = 'moment-photos/test.jpg';
        Storage::disk('public')->put($path, 'fake-content');
        $moment = Moment::create([
            'trip_id' => $trip->id, 'place_name' => 'Spot', 'visited_date' => '2026-08-01',
            'lat' => 10.0, 'lng' => 123.0,
        ]);
        $photo = MomentPhoto::create(['moment_id' => $moment->id, 'photo_path' => $path]);

        Livewire::actingAs($user)
            ->test(ItineraryManager::class, ['tab' => 'moments'])
            ->call('selectTrip', $trip->id)
            ->call('confirmDeletePin', $moment->id)
            ->assertSet('pinToDelete', $moment->id)
            ->call('deletePin')
            ->assertDispatched('pin-deleted');

        $this->assertDatabaseMissing('moments', ['id' => $moment->id]);
        $this->assertDatabaseMissing('moment_photos', ['id' => $photo->id]);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_pins_passed_to_map_are_ordered_by_visited_date(): void
    {
        $user = User::factory()->create();
        $trip = $this->makeTrip($user);
        Moment::create(['trip_id' => $trip->id, 'place_name' => 'Second', 'visited_date' => '2026-08-05', 'lat' => 1, 'lng' => 1]);
        Moment::create(['trip_id' => $trip->id, 'place_name' => 'First',  'visited_date' => '2026-08-01', 'lat' => 2, 'lng' => 2]);

        $html = Livewire::actingAs($user)
            ->test(ItineraryManager::class, ['tab' => 'moments'])
            ->call('selectTrip', $trip->id)
            ->html();

        $firstPos  = strpos($html, 'First');
        $secondPos = strpos($html, 'Second');
        $this->assertNotFalse($firstPos);
        $this->assertNotFalse($secondPos);
        $this->assertLessThan($secondPos, $firstPos, 'Earlier visited_date should be serialized first for polyline ordering.');
    }

    public function test_cannot_edit_pin_belonging_to_another_users_trip(): void
    {
        $owner  = User::factory()->create();
        $other  = User::factory()->create();
        $trip   = $this->makeTrip($owner);
        $moment = Moment::create([
            'trip_id' => $trip->id, 'place_name' => 'Secret',
            'visited_date' => '2026-08-01', 'lat' => 1, 'lng' => 1,
        ]);

        // $other has no trip selected that resolves to $trip (selectedTrip is
        // scoped to auth()->id()), so editing must no-op, not leak the data.
        Livewire::actingAs($other)
            ->test(ItineraryManager::class, ['tab' => 'moments'])
            ->call('openEditPinModal', $moment->id)
            ->assertSet('showPinModal', false)
            ->assertSet('pinPlaceName', '');
    }
}
