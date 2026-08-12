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
            // OpenRouter/Groq are tried before Gemini in the real fallback
            // chain (see Llm::processAiTrip()) — without these, a real API
            // key present in .env makes this test hit the live internet
            // instead of the fake data below, since Http::fake() only
            // intercepts the specific URL patterns it's given. Empty 200s
            // here make both return null so the chain actually reaches
            // the Gemini fake this test is meant to exercise.
            'openrouter.ai/*'                     => Http::response([], 200),
            'api.groq.com/*'                      => Http::response([], 200),
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

    private function withAllSlotsFilled(\Livewire\Features\SupportTesting\Testable $component): \Livewire\Features\SupportTesting\Testable
    {
        return $component
            ->set('aiFrom', 'Manila')
            ->set('aiTo', 'Boracay')
            ->set('aiTravelers', 2)
            ->set('aiBudgetMin', 30000)
            ->set('aiBudgetMax', 30000)
            ->set('aiDateFrom', 'Aug 3')
            ->set('aiDateTo', 'Aug 10, 2026');
    }

    // The core of this feature: once every slot is filled, the very next
    // message must show a summary + confirmation question instead of
    // immediately kicking off generation.
    public function test_confirmation_summary_shown_once_all_slots_are_filled(): void
    {
        $user = User::factory()->create();

        $component = $this->withAllSlotsFilled(
            Livewire::actingAs($user)->test(Llm::class)
        )->set('aiPrompt', 'please continue')->call('automateTrip');

        $component->assertSet('awaitingSlot', 'confirmation');
        $component->assertSet('aiStep', ''); // NOT 'loading' — generation must not have started
        $lastMessage = collect($component->get('messages'))->last();
        $this->assertSame('assistant', $lastMessage['role']);
        $this->assertStringContainsString('Manila', $lastMessage['text']); // origin, not just the destination
        $this->assertStringContainsString('Boracay', $lastMessage['text']);
        $this->assertStringContainsString('Would you like me to proceed with this plan?', $lastMessage['text']);
    }

    public function test_confirming_the_plan_starts_generation(): void
    {
        $user = User::factory()->create();

        $component = $this->withAllSlotsFilled(
            Livewire::actingAs($user)->test(Llm::class)
        )->set('aiPrompt', 'please continue')->call('automateTrip');

        $component->set('aiPrompt', 'yes')->call('automateTrip');

        $component->assertSet('awaitingSlot', '');
        $component->assertSet('aiStep', 'loading');
    }

    // Declining (or anything that isn't a clear "yes") must never be
    // silently treated as approval — no plan should be generated, and the
    // conversation should still be waiting for an actual confirmation.
    public function test_declining_the_plan_does_not_start_generation(): void
    {
        $user = User::factory()->create();

        $component = $this->withAllSlotsFilled(
            Livewire::actingAs($user)->test(Llm::class)
        )->set('aiPrompt', 'please continue')->call('automateTrip');

        $component->set('aiPrompt', 'no')->call('automateTrip');

        $component->assertSet('awaitingSlot', 'confirmation');
        $component->assertSet('aiStep', '');
        $lastMessage = collect($component->get('messages'))->last();
        $this->assertStringContainsString('/reset', $lastMessage['text']);
    }

    public function test_editing_destination_during_confirmation_updates_just_that_slot(): void
    {
        $user = User::factory()->create();

        $component = $this->withAllSlotsFilled(
            Livewire::actingAs($user)->test(Llm::class)
        )->set('aiPrompt', 'please continue')->call('automateTrip');

        $component->set('aiPrompt', 'can you change my destination to bohol')->call('automateTrip');

        $component->assertSet('aiTo', 'Bohol');
        $component->assertSet('aiFrom', 'Manila');       // untouched
        $component->assertSet('aiBudgetMax', 30000);      // untouched
        $component->assertSet('awaitingSlot', 'confirmation'); // still reviewing, not generating
        $component->assertSet('aiStep', '');
        $lastMessage = collect($component->get('messages'))->last();
        $this->assertStringContainsString('Bohol', $lastMessage['text']);
    }

    public function test_editing_budget_during_confirmation_updates_just_that_slot(): void
    {
        $user = User::factory()->create();

        $component = $this->withAllSlotsFilled(
            Livewire::actingAs($user)->test(Llm::class)
        )->set('aiPrompt', 'please continue')->call('automateTrip');

        $component->set('aiPrompt', 'change my budget to 50000')->call('automateTrip');

        $component->assertSet('aiBudgetMin', 50000);
        $component->assertSet('aiBudgetMax', 50000);
        $component->assertSet('aiTo', 'Boracay'); // untouched
        $component->assertSet('awaitingSlot', 'confirmation');
    }

    public function test_editing_travelers_during_confirmation_updates_just_that_slot(): void
    {
        $user = User::factory()->create();

        $component = $this->withAllSlotsFilled(
            Livewire::actingAs($user)->test(Llm::class)
        )->set('aiPrompt', 'please continue')->call('automateTrip');

        $component->set('aiPrompt', 'change my travelers to 4')->call('automateTrip');

        $component->assertSet('aiTravelers', 4);
        $component->assertSet('awaitingSlot', 'confirmation');
    }

    // A reply that doesn't name any of the editable slots (and isn't a
    // "yes") must fall through to the plain decline message, not silently
    // do nothing or crash.
    public function test_unrecognized_reply_during_confirmation_falls_back_to_decline_message(): void
    {
        $user = User::factory()->create();

        $component = $this->withAllSlotsFilled(
            Livewire::actingAs($user)->test(Llm::class)
        )->set('aiPrompt', 'please continue')->call('automateTrip');

        $component->set('aiPrompt', 'hmm let me think about it')->call('automateTrip');

        $component->assertSet('aiTo', 'Boracay');      // untouched
        $component->assertSet('awaitingSlot', 'confirmation');
        $lastMessage = collect($component->get('messages'))->last();
        $this->assertStringContainsString('/reset', $lastMessage['text']);
    }

    // The exact bug reported live: naming a slot without a value ("also
    // the budget") was falling through to the generic decline message
    // instead of asking what the new value should be.
    public function test_naming_a_slot_without_a_value_asks_for_the_value_instead_of_declining(): void
    {
        $user = User::factory()->create();

        $component = $this->withAllSlotsFilled(
            Livewire::actingAs($user)->test(Llm::class)
        )->set('aiPrompt', 'please continue')->call('automateTrip');

        $component->set('aiPrompt', 'also the budget')->call('automateTrip');

        $component->assertSet('pendingEditSlot', 'budget');
        $component->assertSet('aiBudgetMax', 30000); // untouched so far
        $component->assertSet('awaitingSlot', 'confirmation');
        $lastMessage = collect($component->get('messages'))->last();
        $this->assertStringNotContainsString('/reset', $lastMessage['text']); // not the generic decline
    }

    public function test_answering_a_pending_edit_slot_applies_the_value(): void
    {
        $user = User::factory()->create();

        $component = $this->withAllSlotsFilled(
            Livewire::actingAs($user)->test(Llm::class)
        )->set('aiPrompt', 'please continue')->call('automateTrip');

        $component->set('aiPrompt', 'also the budget')->call('automateTrip');
        $component->set('aiPrompt', '50000')->call('automateTrip');

        $component->assertSet('aiBudgetMin', 50000);
        $component->assertSet('aiBudgetMax', 50000);
        $component->assertSet('pendingEditSlot', '');
        $component->assertSet('awaitingSlot', 'confirmation');
    }

    public function test_cancelling_a_pending_edit_leaves_the_original_value_untouched(): void
    {
        $user = User::factory()->create();

        $component = $this->withAllSlotsFilled(
            Livewire::actingAs($user)->test(Llm::class)
        )->set('aiPrompt', 'please continue')->call('automateTrip');

        $component->set('aiPrompt', 'also the budget')->call('automateTrip');
        $component->set('aiPrompt', 'same budget')->call('automateTrip');

        $component->assertSet('aiBudgetMax', 30000); // unchanged
        $component->assertSet('pendingEditSlot', '');
        $component->assertSet('awaitingSlot', 'confirmation');
        $lastMessage = collect($component->get('messages'))->last();
        $this->assertStringContainsString('No changes made', $lastMessage['text']);
    }

    // An unparseable reply while a value is pending must re-ask, not
    // silently drop the edit or crash.
    public function test_unparseable_reply_while_pending_edit_asks_again(): void
    {
        $user = User::factory()->create();

        $component = $this->withAllSlotsFilled(
            Livewire::actingAs($user)->test(Llm::class)
        )->set('aiPrompt', 'please continue')->call('automateTrip');

        $component->set('aiPrompt', 'also the budget')->call('automateTrip');
        $component->set('aiPrompt', 'banana')->call('automateTrip');

        $component->assertSet('aiBudgetMax', 30000); // unchanged
        $component->assertSet('pendingEditSlot', 'budget'); // still waiting
        $component->assertSet('awaitingSlot', 'confirmation');
    }

    // The exact bug reported live: editing the destination to the same
    // place as the (untouched) origin must be rejected the same way the
    // normal first-time flow already rejects it — not silently accepted
    // just because it came through the edit path instead.
    public function test_editing_destination_to_match_existing_origin_is_rejected(): void
    {
        $user = User::factory()->create();

        // withAllSlotsFilled() sets aiFrom = 'Manila'.
        $component = $this->withAllSlotsFilled(
            Livewire::actingAs($user)->test(Llm::class)
        )->set('aiPrompt', 'please continue')->call('automateTrip');

        $component->set('aiPrompt', 'change my destination to manila')->call('automateTrip');

        $component->assertSet('aiTo', 'Boracay'); // unchanged — edit rejected
        $component->assertSet('aiFrom', 'Manila');
        $component->assertSet('pendingEditSlot', 'destination'); // asked to try again
        $component->assertSet('awaitingSlot', 'confirmation');
    }

    public function test_editing_origin_to_match_existing_destination_is_rejected(): void
    {
        $user = User::factory()->create();

        // withAllSlotsFilled() sets aiTo = 'Boracay'.
        $component = $this->withAllSlotsFilled(
            Livewire::actingAs($user)->test(Llm::class)
        )->set('aiPrompt', 'please continue')->call('automateTrip');

        $component->set('aiPrompt', 'change my origin to boracay')->call('automateTrip');

        $component->assertSet('aiFrom', 'Manila'); // unchanged — edit rejected
        $component->assertSet('aiTo', 'Boracay');
        $component->assertSet('pendingEditSlot', 'origin');
    }

    // The other bug reported live: the retry question repeated the exact
    // same first-time phrasing forever instead of escalating, like every
    // other slot in this app already does after a second miss.
    public function test_retry_message_escalates_after_repeated_invalid_destination_edits(): void
    {
        $user = User::factory()->create();

        $component = $this->withAllSlotsFilled(
            Livewire::actingAs($user)->test(Llm::class)
        )->set('aiPrompt', 'please continue')->call('automateTrip');

        // "xxxxxxxx" is caught by the deterministic gibberish check (no AI
        // call needed), so this stays fast and fully offline.
        $component->set('aiPrompt', 'change my destination to xxxxxxxx')->call('automateTrip');
        $firstReply = collect($component->get('messages'))->last()['text'];
        $this->assertStringContainsString('Sure! Where would you like to go?', $firstReply);

        $component->set('aiPrompt', 'xxxxxxxx')->call('automateTrip');
        $secondReply = collect($component->get('messages'))->last()['text'];
        $this->assertStringContainsString("didn't quite catch", $secondReply);
        $this->assertNotSame($firstReply, $secondReply);

        $component->assertSet('pendingEditSlot', 'destination'); // still waiting
    }

    // The exact bug reported live: right after /reset, TARA's own message
    // directly asks for a destination, but awaitingSlot stayed blank —
    // so a bad first answer (e.g. "dota") fell through to the generic
    // off-topic classifier instead of the destination-specific retry.
    // Uses gibberish ("xxxxxxxx") rather than "dota" itself so the place
    // check is rejected by the deterministic gibberish filter with no AI
    // call — "dota" would additionally trigger a live place-verification
    // call this test isn't faking. Mistral (tried first in
    // extractWithAi()'s chain) is faked to return a definite "not
    // off-topic" so the classification step is deterministic too.
    public function test_reset_marks_awaiting_destination_so_a_bad_first_reply_gets_the_destination_retry(): void
    {
        Http::fake([
            'api.mistral.ai/*' => Http::response([
                'choices' => [['message' => ['content' => json_encode([
                    'off_topic' => false, 'is_greeting' => false, 'is_inappropriate' => false,
                    'origin' => null, 'destination' => null, 'travelers' => null,
                    'budget_min' => null, 'budget_max' => null, 'date_from' => null, 'date_to' => null,
                ])]]],
            ], 200),
        ]);
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)->test(Llm::class)
            ->set('aiPrompt', '/reset')->call('automateTrip');

        $component->assertSet('awaitingSlot', 'destination');

        $component->set('aiPrompt', 'xxxxxxxx')->call('automateTrip');

        $lastMessage = collect($component->get('messages'))->last()['text'];
        $this->assertStringNotContainsString("Travel Assistant", $lastMessage); // not the off-topic message
        $component->assertSet('aiTo', ''); // still correctly not accepted as a place
    }

    // The exact bug reported live: "I want to change my destination in
    // Bohol" has an unrelated "to" in "want to", and the real value comes
    // after "in", not "to". The old code only ever looked for "to", so it
    // grabbed the whole garbled tail ("change my destination in Bohol")
    // as the value instead of just "Bohol".
    public function test_editing_a_slot_using_in_as_the_connector_word(): void
    {
        $user = User::factory()->create();

        $component = $this->withAllSlotsFilled(
            Livewire::actingAs($user)->test(Llm::class)
        )->set('aiPrompt', 'please continue')->call('automateTrip');

        $component->set('aiPrompt', 'I want to change my destination in bohol')->call('automateTrip');

        $component->assertSet('aiTo', 'Bohol');
        $component->assertSet('aiFrom', 'Manila'); // untouched
        $component->assertSet('awaitingSlot', 'confirmation');
    }
}
