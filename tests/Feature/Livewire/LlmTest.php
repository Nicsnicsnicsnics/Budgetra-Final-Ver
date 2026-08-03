<?php
namespace Tests\Feature\Livewire;

use App\Livewire\Traveler\Llm;
use App\Models\AiConversationHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class LlmTest extends TestCase
{
    use RefreshDatabase;

    private function fakeGeminiPackage(array $overrides = []): void
    {
        $package = array_merge([
            'from'           => 'Manila',
            'to'             => 'Cebu',
            'budget_min'     => 20000,
            'budget_max'     => 30000,
            'date_from'      => 'Jan 1',
            'date_to'        => 'Jan 8, 2024',
            'days'           => 7,
            'transport'      => ['from_code' => 'MNL', 'to_code' => 'CEB', 'detail' => 'Cebu Pacific', 'cost' => 3000],
            'accommodation'  => ['name' => 'Hotel', 'stars' => 4, 'detail' => '7 nights', 'cost' => 15000],
            'food'           => ['name' => 'Restaurant', 'detail' => '7 days', 'cost' => 8000],
            'attractions'    => ['items' => [], 'cost' => 0],
            'total'          => 26000,
            'budget'         => 30000,
            'pct'            => 86,
        ], $overrides);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => json_encode($package)]]]]],
            ], 200),
        ]);
    }

    // "1 week" (or any relative duration) has nothing to be relative TO
    // unless the AI is told what today is — without that anchor, it can
    // hallucinate a date from its own training-data era. Even with the
    // anchor now in place, this confirms the receiving code independently
    // rejects a past date rather than trusting the AI got it right.
    public function test_processAiTrip_rejects_a_past_date_from_the_ai_package(): void
    {
        $user = User::factory()->create();
        $this->fakeGeminiPackage(['date_from' => 'Jan 1', 'date_to' => 'Jan 8, 2024']);

        Livewire::actingAs($user)
            ->test(Llm::class)
            ->set('messages', [['role' => 'user', 'text' => 'Cebu, 1 week, 30000 budget']])
            ->call('processAiTrip')
            ->assertSet('aiDateFrom', '');
    }

    public function test_processAiTrip_accepts_a_valid_future_date(): void
    {
        $user   = User::factory()->create();
        $future = now()->addWeek();
        $this->fakeGeminiPackage([
            'date_from' => $future->format('M j'),
            'date_to'   => $future->copy()->addDays(6)->format('M j, Y'),
        ]);

        Livewire::actingAs($user)
            ->test(Llm::class)
            ->set('messages', [['role' => 'user', 'text' => 'Cebu, 1 week, 30000 budget']])
            ->call('processAiTrip')
            ->assertSet('aiDateFrom', $future->format('M j'));
    }

    // The AI free-generates transport.from_code independently of the "from"
    // field in the same JSON response — it can (and does) name the origin
    // correctly while still defaulting the flight route to Manila, since
    // the prompt's own "default Manila if not mentioned" instruction bleeds
    // into the transport leg even when a different origin was clearly given.
    public function test_processAiTrip_derives_transport_codes_from_resolved_cities_not_the_ais_own(): void
    {
        $user = User::factory()->create();
        $this->fakeGeminiPackage([
            'from'      => 'Cebu City',
            'to'        => 'Davao',
            // The AI hallucinated MNL here despite correctly naming Cebu
            // City as the origin above — this is the exact bug reported.
            'transport' => ['from_code' => 'MNL', 'to_code' => 'MNL', 'detail' => 'Cebu Pacific', 'cost' => 3000],
        ]);

        $component = Livewire::actingAs($user)
            ->test(Llm::class)
            ->set('messages', [['role' => 'user', 'text' => 'Cebu City to Davao, 30000 budget, Aug 3 to Aug 10']])
            ->call('processAiTrip');

        $component->assertSet('aiFrom', 'Cebu City');
        $this->assertSame('CEB', $component->get('aiPackage')['transport']['from_code']);
        $this->assertSame('DVO', $component->get('aiPackage')['transport']['to_code']);
    }

    // proceedToWizardItinerary() is the only point a conversation ever
    // actually ends (there's no separate "start a new chat" reset), so
    // it's the one place a finished conversation gets archived.
    public function test_proceeding_to_the_wizard_archives_the_conversation_into_history(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(Llm::class)
            ->set('messages', [
                ['role' => 'user', 'text' => 'Cebu to Davao, 30000 budget'],
                ['role' => 'assistant', 'text' => 'Great, when would you like to travel?'],
            ])
            ->set('aiFrom', 'Cebu')
            ->set('aiTo', 'Davao')
            ->set('aiBudgetMin', 30000)
            ->set('aiBudgetMax', 30000)
            ->set('aiDateFrom', 'Aug 3')
            ->set('aiDateTo', 'Aug 10, 2026')
            ->set('aiDays', 8)
            ->set('aiPackage', ['transport' => ['from_code' => 'CEB', 'to_code' => 'DVO', 'cost' => 3000]])
            ->call('proceedToWizardItinerary');

        $this->assertDatabaseHas('ai_conversation_histories', [
            'user_id' => $user->id,
            'ai_from' => 'Cebu',
            'ai_to'   => 'Davao',
        ]);
        $saved = AiConversationHistory::where('user_id', $user->id)->first();
        $this->assertCount(2, $saved->messages);
    }

    public function test_history_panel_lists_past_conversations_newest_first(): void
    {
        $user = User::factory()->create();
        // created_at isn't fillable (Eloquent manages timestamps itself),
        // so backdating it has to happen after creation, not via create().
        $older = AiConversationHistory::create(['user_id' => $user->id, 'messages' => [], 'ai_from' => 'Manila', 'ai_to' => 'Boracay']);
        $older->forceFill(['created_at' => now()->subDay()])->save();
        AiConversationHistory::create(['user_id' => $user->id, 'messages' => [], 'ai_from' => 'Manila', 'ai_to' => 'Palawan']);

        Livewire::actingAs($user)
            ->test(Llm::class)
            ->call('openHistory')
            ->assertSet('showHistory', true)
            ->assertSeeInOrder(['Palawan', 'Boracay']);
    }

    public function test_viewing_a_history_entry_shows_its_transcript(): void
    {
        $user  = User::factory()->create();
        $entry = AiConversationHistory::create([
            'user_id'  => $user->id,
            'messages' => [['role' => 'user', 'text' => 'Take me to Siargao next month']],
            'ai_from'  => 'Manila',
            'ai_to'    => 'Siargao',
        ]);

        Livewire::actingAs($user)
            ->test(Llm::class)
            ->call('viewHistoryEntry', $entry->id)
            ->assertSee('Take me to Siargao next month');
    }

    // The id is a plain public property, settable directly over the wire
    // protocol like any other — the computed property backing it must
    // independently re-check ownership rather than trusting the id alone.
    public function test_cannot_view_another_users_history_entry_by_tampering_with_the_id(): void
    {
        $owner    = User::factory()->create();
        $attacker = User::factory()->create();
        $entry    = AiConversationHistory::create([
            'user_id'  => $owner->id,
            'messages' => [['role' => 'user', 'text' => "Owner's private trip idea"]],
            'ai_from'  => 'Manila',
            'ai_to'    => 'Boracay',
        ]);

        Livewire::actingAs($attacker)
            ->test(Llm::class)
            ->set('showHistory', true)
            ->set('viewingHistoryId', $entry->id)
            ->assertDontSee("Owner's private trip idea");
    }
}
