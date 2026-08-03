<?php
namespace Tests\Feature\Livewire;

use App\Livewire\Traveler\TripPlannerWizard;
use App\Models\Destination;
use App\Models\Itinerary;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TripPlannerWizardTest extends TestCase
{
    use RefreshDatabase;

    public function test_wizard_page_loads(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/trips/plan')->assertStatus(200);
    }

    public function test_wizard_starts_at_step_one(): void
    {
        $user = User::factory()->create();
        Livewire::actingAs($user)
            ->test(TripPlannerWizard::class)
            ->assertSet('step', 1);
    }

    public function test_can_advance_to_step_two(): void
    {
        $user = User::factory()->create();
        Livewire::actingAs($user)
            ->test(TripPlannerWizard::class)
            ->set('tripScope', 'international')
            ->call('nextStep')
            ->assertSet('step', 2);
    }

    public function test_wizard_creates_trip_on_confirm(): void
    {
        $user = User::factory()->create();
        $dest = Destination::create(['name' => 'Boracay', 'country' => 'Philippines', 'description' => 'Beautiful island']);

        Livewire::actingAs($user)
            ->test(TripPlannerWizard::class)
            ->set('step', 5)
            ->set('tripScope', 'local')
            ->set('destinationId', $dest->id)
            ->set('destinationName', 'Boracay, Philippines')
            ->set('startDate', '2027-01-10')
            ->set('endDate', '2027-01-15')
            ->set('travelType', 'Solo')
            ->set('budgetTier', 'Mid-range')
            ->set('budgetLimit', 50000)
            ->call('confirm')
            ->assertRedirect();

        $this->assertDatabaseHas('trips', [
            'user_id'     => $user->id,
            'destination' => 'Boracay, Philippines',
            'travel_type' => 'Solo',
        ]);
    }

    public function test_wizard_requires_auth(): void
    {
        $this->get('/trips/plan')->assertRedirect(route('login'));
    }

    private function fakeVenue(string $name, int $priceMin = 200, int $priceMax = 400): array
    {
        return ['name' => $name, 'cuisine' => 'Filipino', 'city' => 'Cebu', 'priceMin' => $priceMin, 'priceMax' => $priceMax];
    }

    private function fakeAttraction(string $name, bool $isFree = false, string $price = '150'): array
    {
        return ['name' => $name, 'type' => 'Landmark', 'city' => 'Cebu', 'isFree' => $isFree, 'price' => $isFree ? 'FREE' : $price];
    }

    public function test_select_venue_replaces_previous_pick_and_advances_to_attractions(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(TripPlannerWizard::class)
            ->set('manualTo', 'Cebu')
            ->set('flightTripType', 'round_trip')
            ->set('venueResults', [$this->fakeVenue('Larsian BBQ'), $this->fakeVenue('Zubuchon')])
            ->call('selectVenue', 0)
            ->assertSet('selectedVenues.Larsian BBQ.name', 'Larsian BBQ')
            ->assertSet('step', 5);

        // Picking again from a fresh instance simulates going back and choosing differently —
        // selecting should replace, not add to, the previous pick.
        Livewire::actingAs($user)
            ->test(TripPlannerWizard::class)
            ->set('manualTo', 'Cebu')
            ->set('flightTripType', 'round_trip')
            ->set('venueResults', [$this->fakeVenue('Larsian BBQ'), $this->fakeVenue('Zubuchon')])
            ->call('selectVenue', 0)
            ->call('selectVenue', 1)
            ->assertSet('selectedVenues', ['Zubuchon' => $this->fakeVenue('Zubuchon')]);
    }

    public function test_select_attraction_replaces_previous_pick_and_advances_to_step_six(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(TripPlannerWizard::class)
            ->set('flightTripType', 'round_trip')
            ->set('attractionResults', [$this->fakeAttraction('Magellan\'s Cross'), $this->fakeAttraction('Fort San Pedro')])
            ->call('selectAttraction', 0)
            ->assertSet('selectedAttractions.Magellan\'s Cross.name', 'Magellan\'s Cross')
            ->assertSet('step', 6)
            ->call('selectAttraction', 1)
            ->assertSet('selectedAttractions', ['Fort San Pedro' => $this->fakeAttraction('Fort San Pedro')]);
    }

    public function test_save_itinerary_creates_one_row_for_the_selected_venue_and_attraction(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(TripPlannerWizard::class)
            ->set('manualFrom', 'Manila')
            ->set('manualTo', 'Cebu')
            ->set('startDate', '2027-02-01')
            ->set('endDate', '2027-02-08')
            ->set('manualBudgetMin', '20000')
            ->set('manualBudgetMax', '30000')
            ->set('venueResults', [$this->fakeVenue('Larsian BBQ')])
            ->call('selectVenue', 0)
            ->set('attractionResults', [$this->fakeAttraction('Fort San Pedro')])
            ->call('selectAttraction', 0)
            ->call('saveItinerary');

        $trip = Trip::where('user_id', $user->id)->first();
        $this->assertNotNull($trip);

        $this->assertSame(2, Itinerary::where('trip_id', $trip->id)->count());
        $this->assertTrue(str_contains($trip->summary_data['food']['detail'], 'Larsian BBQ'));
        $this->assertTrue(str_contains($trip->summary_data['attractions']['detail'], 'Fort San Pedro'));
        $this->assertSame(150, (int) $trip->summary_data['attractions']['cost']);
    }

    public function test_add_custom_activity_requires_a_title(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(TripPlannerWizard::class)
            ->set('customActivityTitle', '   ')
            ->call('addCustomActivity')
            ->assertSet('customActivities', [])
            ->assertSet('showCustomActivityModal', false);
    }

    public function test_add_custom_activity_adds_it_and_closes_modal(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(TripPlannerWizard::class)
            ->call('openCustomActivityModal')
            ->assertSet('showCustomActivityModal', true)
            ->set('customActivityTitle', 'Souvenir Shopping')
            ->set('customActivityDay', '2')
            ->set('customActivityCost', '500')
            ->set('customActivityType', 'Shopping')
            ->call('addCustomActivity')
            ->assertSet('showCustomActivityModal', false)
            ->assertSet('customActivities', [[
                'day'         => 2,
                'title'       => 'Souvenir Shopping',
                'time'        => '09:00',
                'cost'        => 500.0,
                'type'        => 'Shopping',
                'description' => '',
            ]]);
    }

    public function test_remove_custom_activity_deletes_it(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(TripPlannerWizard::class)
            ->set('customActivityTitle', 'Souvenir Shopping')
            ->call('addCustomActivity')
            ->call('removeCustomActivity', 0)
            ->assertSet('customActivities', []);
    }

    public function test_save_itinerary_persists_custom_activity_on_the_chosen_day(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(TripPlannerWizard::class)
            ->set('manualFrom', 'Manila')
            ->set('manualTo', 'Cebu')
            ->set('startDate', '2027-02-01')
            ->set('endDate', '2027-02-08')
            ->set('manualBudgetMin', '20000')
            ->set('manualBudgetMax', '30000')
            ->set('customActivities', [
                ['day' => 3, 'title' => 'Souvenir Shopping', 'time' => '16:00', 'cost' => 500.0, 'type' => 'Shopping', 'description' => 'At the market'],
            ])
            ->call('saveItinerary');

        $trip = Trip::where('user_id', $user->id)->first();
        $this->assertNotNull($trip);

        $item = Itinerary::where('trip_id', $trip->id)->where('title', 'Souvenir Shopping')->first();
        $this->assertNotNull($item);
        $this->assertSame('2027-02-03 16:00:00', $item->start_datetime->format('Y-m-d H:i:s'));
        $this->assertSame('Activity', $item->type);
        $this->assertSame('At the market', $item->notes);
        $this->assertSame(500, (int) $trip->summary_data['attractions']['cost']);
    }

    public function test_back_to_attractions_returns_to_step_five_without_clearing_selection(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(TripPlannerWizard::class)
            ->set('flightTripType', 'round_trip')
            ->set('attractionResults', [$this->fakeAttraction('Fort San Pedro')])
            ->call('selectAttraction', 0)
            ->set('step', 8)
            ->call('backToAttractions')
            ->assertSet('step', 5)
            ->assertSet('selectedAttractions.Fort San Pedro.name', 'Fort San Pedro');
    }
}
