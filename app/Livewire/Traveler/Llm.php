<?php
namespace App\Livewire\Traveler;

use App\Models\AiConversationDraft;
use App\Models\AiConversationHistory;
use App\Services\CerebrasService;
use App\Services\GeminiService;
use App\Services\GroqService;
use App\Services\MistralService;
use App\Services\OpenRouterService;
use App\Services\SerpApiService;
use App\Services\SerperService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

#[Layout('layouts.app', ['active' => 'trips'])]
class Llm extends Component
{
    // ── AI Planner state ───────────────────────────────────
    public string $aiPrompt      = '';
    public string $aiStep        = '';   // '' | 'loading' | 'results'
    public string $aiFrom        = '';
    public string $aiTo          = '';
    public int    $aiBudgetMin   = 0;
    public int    $aiBudgetMax   = 0;
    public string $aiDateFrom    = '';
    public string $aiDateTo      = '';
    public int    $aiDays        = 0;
    public array  $aiPackage     = [];
    public int    $aiGenCount    = 0;   // 0 = first gen (expensive), 1+ = cheaper options

    // ── Conversation transcript ─────────────────────────────
    // Each entry: ['role' => 'user'|'assistant', 'text' => string]
    public array $messages = [];

    // Which slot the last assistant question was about — '' | 'destination'
    // | 'budget' | 'dates'. Lets us treat a plain reply ("Cebu City", with
    // no "to"/"from" keyword at all) as the direct answer to that question,
    // instead of requiring the same trigger words every time.
    public string $awaitingSlot = '';

    // How many times in a row awaitingSlot has been missed — 0 the first
    // time we ask, then incremented each time a reply fails to resolve it.
    // Drives which phrasing tier questionFor() uses, so three-in-a-row
    // misses don't just alternate between the same two sentences forever.
    public int $missCount = 0;

    // Set by parseAiPrompt() within the current request only (never synced —
    // it's private, so Livewire doesn't persist it between turns) when a
    // message names two candidate places joined by "or" for the same slot
    // ("Cebu or Boracay"). automateTrip() checks this right after parsing
    // and, if set, asks for clarification instead of silently locking in
    // whichever place the regex happened to capture first.
    private ?string $ambiguityNotice = null;

    // Set inside parseAiPrompt()'s budget step when the traveler's message
    // names a non-peso currency ("$1,500", "1500 USD") — never synced, same
    // reasoning as $ambiguityNotice above.
    private ?string $currencyNotice = null;

    // ── Conversation history (view-only browsing of past chats) ────────
    public bool $showHistory       = false;
    public ?int $viewingHistoryId  = null;
    public ?int $historyEntryToDelete = null;

    // Read-only summary of the traveler's saved interests (from onboarding),
    // shown as a quick reminder on the landing screen and folded into trip
    // generation below — editing happens on the actual profile screen, not
    // here, so this is never written back to.
    public function getProfileInterestsProperty(): array
    {
        return auth()->user()->userProfile?->interests ?? [];
    }

    // Every past conversation that's been handed off to the wizard so far
    // (see proceedToWizardItinerary(), the only place one gets archived),
    // newest first.
    public function getConversationHistoryProperty()
    {
        return AiConversationHistory::where('user_id', auth()->id())
            ->latest()
            ->get();
    }

    // The specific past conversation currently being read, or null while
    // just browsing the list. Re-fetched fresh each render rather than
    // cached — this is a rarely-opened panel, not a hot path.
    public function getViewingHistoryEntryProperty()
    {
        if ($this->viewingHistoryId === null) return null;

        // Scoped to the current user the same way every other lookup in
        // this component is — a tampered id in the request must never be
        // able to read another traveler's conversation.
        return AiConversationHistory::where('user_id', auth()->id())
            ->find($this->viewingHistoryId);
    }

    public function openHistory(): void
    {
        $this->showHistory = true;
    }

    public function closeHistory(): void
    {
        $this->showHistory      = false;
        $this->viewingHistoryId = null;
    }

    public function viewHistoryEntry(int $id): void
    {
        // Kept independent of openHistory() rather than assumed to always
        // run after it — this way the method is correct on its own no
        // matter what ends up calling it.
        $this->showHistory      = true;
        $this->viewingHistoryId = $id;
    }

    public function backToHistoryList(): void
    {
        $this->viewingHistoryId = null;
    }

    public function confirmDeleteHistoryEntry(int $id): void
    {
        $this->historyEntryToDelete = $id;
    }

    public function cancelDeleteHistoryEntry(): void
    {
        $this->historyEntryToDelete = null;
    }

    public function deleteHistoryEntry(): void
    {
        if (!$this->historyEntryToDelete) return;

        // Scoped to the current user the same way viewHistoryEntry()'s own
        // lookup is — a tampered id must never be able to delete another
        // traveler's history.
        AiConversationHistory::where('user_id', auth()->id())
            ->where('id', $this->historyEntryToDelete)
            ->delete();

        // Deleting the entry currently open falls back to the list instead
        // of leaving the transcript view pointed at a now-missing record.
        if ($this->viewingHistoryId === $this->historyEntryToDelete) {
            $this->viewingHistoryId = null;
        }

        $this->historyEntryToDelete = null;
    }

    // Restores an in-progress conversation from the last visit — a page
    // refresh, closing the tab, or navigating to another sidebar tab and
    // back would otherwise lose everything, since this component isn't
    // wrapped in Livewire's @persist the way the sidebar is. One draft per
    // user (see the migration's unique constraint on user_id); nothing to
    // restore for a first-time visit or once a trip's actually been saved
    // (proceedToWizardItinerary() deletes the draft row once handed off).
    public function mount(): void
    {
        $draft = AiConversationDraft::where('user_id', auth()->id())->first();
        if (!$draft) return;

        $this->messages     = $draft->messages ?? [];
        $this->aiFrom        = $draft->ai_from;
        $this->aiTo          = $draft->ai_to;
        $this->aiBudgetMin   = $draft->ai_budget_min;
        $this->aiBudgetMax   = $draft->ai_budget_max;
        $this->aiDateFrom    = $draft->ai_date_from;
        $this->aiDateTo      = $draft->ai_date_to;
        $this->aiDays        = $draft->ai_days;
        $this->awaitingSlot  = $draft->awaiting_slot;
        $this->missCount     = $draft->miss_count;
        $this->aiStep        = $draft->ai_step;
        $this->aiPackage     = $draft->ai_package ?? [];
        $this->aiGenCount    = $draft->ai_gen_count;

        // A draft saved mid-"loading" means the traveler left (or the
        // connection dropped) after automateTrip() dispatched the
        // generation event but before processAiTrip() ever ran — nothing
        // on a fresh page load would otherwise re-fire that event, so
        // without this the traveler would be stuck on the loading screen
        // forever. Re-run it directly using the same already-resolved trip
        // details; it's the same operation that got interrupted, not a
        // guess.
        if ($this->aiStep === 'loading') {
            $this->processAiTrip();
        }
    }

    // Livewire lifecycle hook — runs at the end of every request for this
    // component (initial mount included), so the draft is always kept in
    // sync without needing a save call sprinkled into every action that
    // touches conversation state.
    public function dehydrate(): void
    {
        // Nothing worth saving yet for a brand-new, untouched visit — skip
        // creating an empty draft row just from loading the page.
        if (empty($this->messages)) return;

        AiConversationDraft::updateOrCreate(
            ['user_id' => auth()->id()],
            [
                'messages'      => $this->messages,
                'ai_from'       => $this->aiFrom,
                'ai_to'         => $this->aiTo,
                'ai_budget_min' => $this->aiBudgetMin,
                'ai_budget_max' => $this->aiBudgetMax,
                'ai_date_from'  => $this->aiDateFrom,
                'ai_date_to'    => $this->aiDateTo,
                'ai_days'       => $this->aiDays,
                'awaiting_slot' => $this->awaitingSlot,
                'miss_count'    => $this->missCount,
                'ai_step'       => $this->aiStep,
                'ai_package'    => $this->aiPackage,
                'ai_gen_count'  => $this->aiGenCount,
            ]
        );
    }

    public function automateTrip(): void
    {
        $userText = trim($this->aiPrompt);
        if ($userText === '') return;

        // What we were already waiting on before this reply — lets us tell
        // "still missing the same thing" apart from "just started asking
        // about something new", so a self-check can catch the one case that
        // actually reads as ignoring the user: repeating an identical
        // question when nothing new came in.
        $previouslyAwaiting = $this->awaitingSlot;

        $this->messages[] = ['role' => 'user', 'text' => $userText];

        // Fast, zero-cost check — a bare "Hi"/"Hello" carries no travel info
        // to extract, so answer it warmly right away instead of silently
        // falling through to the next missing-info question (or waiting on
        // an AI call just to recognize a greeting).
        if ($this->isGreetingOnly($userText)) {
            $this->aiPrompt = '';
            $this->messages[] = ['role' => 'assistant', 'text' => self::GREETING_REPLY];
            $this->dispatch('message-added');
            return;
        }

        // Same fast, zero-cost reasoning as the greeting check above — run
        // BEFORE parsing, independent of which slot (if any) is still
        // missing. A single message can resolve every remaining slot at
        // once (a real destination, origin, budget, and dates all in one
        // reply); once that happens missingSlotKey() goes empty and
        // extractWithAi() below is never called, so its own is_inappropriate
        // check never gets a chance to run. Checking here first closes that
        // gap instead of relying solely on the AI classification.
        if ($this->containsProfanity($userText)) {
            $this->aiPrompt = '';
            $this->messages[] = ['role' => 'assistant', 'text' => self::PROFANITY_REPLY];
            $this->dispatch('message-added');
            return;
        }

        $this->parseAiPrompt();

        // A message naming two candidate places for the same slot ("Cebu or
        // Boracay") is never something the direct-answer fallback or AI
        // extraction below should try to resolve on its own — ask which one
        // right away instead.
        if ($this->ambiguityNotice !== null) {
            $notice = $this->ambiguityNotice;
            $this->ambiguityNotice = null;
            $this->aiPrompt = '';
            $this->messages[] = ['role' => 'assistant', 'text' => $notice];
            $this->dispatch('message-added');
            return;
        }

        // A dollar/euro/etc. amount was just auto-converted to pesos inside
        // parseAiPrompt() (see detectAndConvertCurrency()) — budget is
        // already genuinely resolved at this point, not just flagged, so
        // this shows the conversion confirmation and lets the turn
        // continue normally (asking for whatever's still missing next, or
        // proceeding straight to generation) instead of stopping and
        // waiting the way the ambiguity check above needs to.
        if ($this->currencyNotice !== null) {
            $notice = $this->currencyNotice;
            $this->currencyNotice = null;
            $this->messages[] = ['role' => 'assistant', 'text' => $notice];
            $this->dispatch('message-added');
        }

        $this->applyDirectAnswerFallback($userText);

        // The traveler doesn't always have a destination in mind — regex and
        // the direct-answer fallback above only ever try to EXTRACT a place
        // the traveler names; neither handles "I don't know, can you
        // recommend one?". Only reached when nothing above already resolved
        // a destination, so a message that both asks for a recommendation
        // AND names a place ("not sure, maybe Cebu?") still keeps the named
        // place instead of overriding it with a suggestion.
        if ($this->aiTo === '' && $this->isRecommendationRequest($userText)) {
            $suggestion = $this->suggestDestination();
            if ($suggestion !== '') {
                $this->aiTo = $suggestion;
                $this->aiPrompt = '';
                $nextMissing = $this->missingSlotKey();
                $reply = "No worries! Based on your interests, how about {$suggestion}?";

                if ($nextMissing !== '') {
                    $this->missCount    = 0;
                    $this->awaitingSlot = $nextMissing;
                    $reply .= ' ' . $this->questionFor($nextMissing, 0);
                    $this->messages[] = ['role' => 'assistant', 'text' => $reply];
                    $this->dispatch('message-added');
                    return;
                }

                $this->missCount    = 0;
                $this->awaitingSlot = '';
                $this->messages[] = ['role' => 'assistant', 'text' => $reply . " Let's put together your trip there!"];
                $this->dispatch('message-added');
                $this->aiGenCount = 0;
                $this->aiStep = 'loading';
                $this->dispatch('ai-process-trip');
                return;
            }
            // Suggestion generation failed (AI unavailable) — fall through
            // to the normal flow below, which will just re-ask for the
            // destination as it always has. No worse than before.
        }

        // Regex only understands a fixed set of phrasings — anything it
        // couldn't figure out gets a smarter (but slower, costs an API
        // call) pass through Gemini before we give up and ask again. This
        // same pass also catches messages that aren't about travel at all
        // ("give me code", "who won the NBA finals") — TARA stays a travel
        // assistant and redirects instead of quietly asking for a destination
        // — and less common greeting phrasings ("how's it going?") the fast
        // check above didn't recognize.
        $classification = $this->missingSlotKey() !== '' ? $this->extractWithAi($userText) : '';
        $this->aiPrompt = '';

        if ($classification === 'off_topic') {
            $this->messages[] = ['role' => 'assistant', 'text' =>
                "I'm a Travel Assistant, so I can only help with travel-related questions. If you need help planning a trip, finding destinations, estimating costs, or creating an itinerary, I'd be happy to assist!"];
            $this->dispatch('message-added');
            return;
        }

        if ($classification === 'greeting') {
            $this->messages[] = ['role' => 'assistant', 'text' => self::GREETING_REPLY];
            $this->dispatch('message-added');
            return;
        }

        if ($classification === 'inappropriate') {
            $this->messages[] = ['role' => 'assistant', 'text' => self::PROFANITY_REPLY];
            $this->dispatch('message-added');
            return;
        }

        // ── Self-check before replying ──────────────────────────────────
        // 1. Never ask about a slot that's already resolved (missingSlotKey()
        //    is computed fresh from live state, so this is structurally true
        //    by construction — asserted here as a safety net, not a fix).
        // 2. If still missing the exact same thing as last turn, don't repeat
        //    the identical question — acknowledge the miss and rephrase.
        $stillMissing = $this->missingSlotKey();

        if ($stillMissing !== '') {
            $this->missCount = $stillMissing === $previouslyAwaiting ? $this->missCount + 1 : 0;
            $this->awaitingSlot = $stillMissing;
            $this->messages[] = ['role' => 'assistant', 'text' => $this->questionFor($stillMissing, $this->missCount)];
            $this->dispatch('message-added');
            return;
        }

        // Everything looks resolved on paper — but origin and destination
        // being the same place is never a valid trip. Catch it here, right
        // before generation, regardless of which step resolved the values
        // (regex, direct-answer fallback, or AI), instead of silently
        // building a "Manila to Manila" itinerary.
        if ($this->sameOriginAndDestination()) {
            $conflictCity = $this->aiTo;
            $this->aiTo = '';
            $this->awaitingSlot = 'destination';
            $this->missCount    = 0;
            $this->messages[] = ['role' => 'assistant', 'text' =>
                "Looks like your origin and destination are both {$conflictCity} — could you tell me a different destination to travel to?"];
            $this->dispatch('message-added');
            return;
        }

        // Same reasoning as the same-origin/destination check above — a
        // budget too small to realistically plan any trip around (e.g. a
        // stray "1" that slipped past applyDirectAnswerFallback()'s
        // no-minimum-digit budget check) must never sail through to
        // generation, regardless of which of the several budget-setting
        // paths let it through. Reset rather than just warn, so the next
        // reply is treated as a fresh answer to "what's your budget?"
        // instead of being blocked by the "only fill if still empty"
        // guards those paths all share.
        $budgetFloor = $this->budgetFloor();
        if ($this->aiBudgetMax > 0 && $this->aiBudgetMax < $budgetFloor) {
            $this->aiBudgetMin  = 0;
            $this->aiBudgetMax  = 0;
            $this->awaitingSlot = 'budget';
            $this->missCount    = 0;
            $this->messages[] = ['role' => 'assistant', 'text' =>
                'That budget looks too low to plan a real trip — could you give me a more realistic number (at least ₱' . number_format($budgetFloor) . ')?'];
            $this->dispatch('message-added');
            return;
        }

        $this->missCount    = 0;
        $this->awaitingSlot = '';
        $this->messages[] = ['role' => 'assistant', 'text' => "Got it! Let me put together your trip to {$this->aiTo}…"];
        $this->dispatch('message-added');
        $this->aiGenCount = 0;
        $this->aiStep = 'loading';
        $this->dispatch('ai-process-trip');
    }

    // Greetings, acknowledgments, and other filler replies that are NOT an
    // answer to anything — a bare "Hi" or "yes" typed while we're waiting on
    // a destination/origin must never be accepted as a place name.
    private const NON_ANSWER_FILLERS = [
        'hi', 'hello', 'hey', 'yo', 'sup', 'hiya',
        'good morning', 'good afternoon', 'good evening', 'good day',
        'ok', 'okay', 'k', 'yes', 'yeah', 'yep', 'yup', 'sure', 'alright', 'fine',
        'no', 'nope', 'nah', 'not sure', 'idk', "i don't know", 'dunno', 'maybe',
        'cool', 'great', 'nice', 'awesome', 'thanks', 'thank you', 'please',
        'what', 'why', 'how', 'um', 'uh', 'hmm', 'huh',
    ];

    // The subset of the above that's specifically a greeting (not just any
    // filler) — these get a warm hello back instead of silently being
    // ignored. Checked with no punctuation, case-insensitive, so "Hi!",
    // "hello.", "HOW ARE YOU?" etc. all match.
    private const GREETINGS = [
        'hi', 'hello', 'hey', 'yo', 'sup', 'hiya',
        'good morning', 'good afternoon', 'good evening', 'good day',
        'how are you', 'how are you doing', "what's up", 'whats up', 'howdy',
    ];

    private const GREETING_REPLY = "Hello! 😊 How can I help you with your travel plans today?";

    // Small, curated list of unambiguous profanity/abuse — checked with
    // word boundaries (never a bare substring match) so a legitimate word
    // or place name that merely contains one of these as a substring is
    // never caught (e.g. nothing here can match mid-word inside "Shitake").
    // Deliberately narrow: only clear profanity/insults, not mild
    // frustration words ("crap", "stupid"), to keep false positives low.
    private const PROFANITY_WORDS = [
        'fuck', 'fucking', 'fucked', 'fucker', 'motherfucker',
        'shit', 'shitty', 'bullshit',
        'bitch', 'bitches',
        'asshole', 'assholes',
        'bastard', 'cunt', 'dumbass', 'douchebag',
    ];

    private const PROFANITY_REPLY = "Let's keep things friendly here 🙂 — I'm happy to help plan your trip, just let me know your destination, budget, and dates without the language.";

    // Phrases that mean "I don't have a destination in mind, you pick one"
    // rather than an attempt to name a place — checked as substrings (not
    // exact-match like GREETINGS/NON_ANSWER_FILLERS) since these naturally
    // show up inside a longer sentence ("I don't really know where to go").
    private const RECOMMEND_TRIGGERS = [
        'recommend', 'suggest', 'you decide', 'you choose', 'surprise me',
        'anywhere', 'no idea', "don't know where", 'dont know where',
        'not sure where', 'not sure', 'pick for me', 'up to you',
        'whatever you think', 'idk', "i don't know", 'dunno',
    ];

    private function isRecommendationRequest(string $text): bool
    {
        $normalized = strtolower(trim($text, " \t\n\r\0\x0B.!?,"));
        foreach (self::RECOMMEND_TRIGGERS as $trigger) {
            if (str_contains($normalized, $trigger)) return true;
        }
        return false;
    }

    // Asks the AI for ONE specific, real destination matching the
    // traveler's saved interests. Same fallback chain and the same
    // knownPlaceName() plausibility gate used everywhere else in this file —
    // a recommendation is exactly as untrustworthy as any other AI output
    // until it's checked against an actual known place (see [[the "Code"
    // bug]] this session was built around avoiding).
    private function suggestDestination(): string
    {
        // Gemini → Groq → OpenRouter, each up to 18s (see generate()'s
        // default) — a fatal "Maximum execution time exceeded" isn't
        // catchable, so this needs its own bump past PHP's default 30s
        // the same way processAiTrip() already does for its own chain.
        set_time_limit(90);

        $interests = $this->profileInterests;
        $interestText = !empty($interests)
            ? implode(', ', $interests)
            : 'general sightseeing, popular beaches, and well-rounded trips';

        $prompt = <<<PROMPT
        You are a Philippine travel assistant. A traveler doesn't know where to go and wants a recommendation.

        Traveler's interests: {$interestText}

        Suggest exactly ONE real, specific travel destination (a city or island, not a country) that best matches these interests. It can be in the Philippines or an international destination.

        Return JSON only, no markdown:
        {"destination": "city name"}
        PROMPT;

        try {
            $raw = (new GeminiService())->generate($prompt);
            if (!$raw) { $raw = (new GroqService())->generate($prompt); }
            if (!$raw) { $raw = (new OpenRouterService())->generate($prompt); }
            if (!$raw) return '';

            $raw = trim(preg_replace('/```json\s*|```\s*/i', '', $raw));
            $data = json_decode($raw, true);
            if (!is_array($data) || empty($data['destination'])) return '';
        } catch (\Throwable) {
            return '';
        }

        return $this->knownPlaceName($this->cleanCityName($data['destination']));
    }

    private function isGreetingOnly(string $text): bool
    {
        $normalized = strtolower(trim($text, " \t\n\r\0\x0B.!?,"));
        return in_array($normalized, self::GREETINGS, true);
    }

    // Word-boundary match against PROFANITY_WORDS — deliberately NOT a bare
    // str_contains() substring check, which would false-positive on any
    // real word or place name that merely contains one of these as a
    // substring. This only catches the exact listed words/forms; creative
    // misspellings, spacing tricks, or other-language profanity can still
    // slip through — that residual gap is covered, for messages that still
    // leave a slot missing, by the is_inappropriate check inside
    // extractWithAi() further down, which judges intent rather than
    // matching a fixed list.
    private function containsProfanity(string $text): bool
    {
        $pattern = '/\b(?:' . implode('|', array_map(fn ($w) => preg_quote($w, '/'), self::PROFANITY_WORDS)) . ')\b/iu';
        return (bool) preg_match($pattern, $text);
    }

    private function isNonAnswerFiller(string $text): bool
    {
        $normalized = strtolower(trim($text, " \t\n\r\0\x0B.!?,"));
        return in_array($normalized, self::NON_ANSWER_FILLERS, true);
    }

    // If the keyword-based parser above found nothing for the exact slot we
    // just asked about, fall back to treating the whole reply as the direct
    // answer to that question — the most natural way anyone actually
    // answers "Where would you like to go?" is just to name the place.
    // Only applies to short, simple replies (a handful of words) that don't
    // look like a filler word — a longer sentence packed with multiple
    // details isn't "just the answer", and guessing there would misfire, so
    // that case (and any filler) is left to Gemini/re-asking instead.
    private function applyDirectAnswerFallback(string $userText): void
    {
        if (str_word_count($userText) > 6) return;
        if ($this->isNonAnswerFiller($userText)) return;

        // For destination/origin, a blocklist of "known bad" words can never
        // cover every non-place reply someone might type (we've already
        // caught "Hi" and "yes" this way — "Code" slipped through the exact
        // same hole). Flip the check around instead: only accept the direct
        // answer if it resolves to an ACTUAL known place via the same
        // lookup used for airport codes. Anything that doesn't match falls
        // through to the AI classification below, which is far better
        // suited to judging "is this really a place?" than a fixed list.
        if ($this->awaitingSlot === 'destination' && $this->aiTo === '') {
            // knownPlaceName() (not just an iataCode() !== '' gate) matters
            // here: a reply padded with extra words ("Actually I want Cebu")
            // must resolve to just "Cebu", not the whole raw sentence.
            $resolved = $this->knownPlaceName($this->cleanCityName($userText));
            if ($resolved !== '') {
                $this->aiTo = $resolved;
            }
        } elseif ($this->awaitingSlot === 'origin' && $this->aiFrom === '') {
            $resolved = $this->knownPlaceName($this->cleanCityName($userText));
            if ($resolved !== '') {
                $this->aiFrom = $resolved;
            }
        } elseif ($this->awaitingSlot === 'budget' && $this->aiBudgetMin === 0 && $this->aiBudgetMax === 0) {
            $money = '\d+(?:,\d{3})*(?:\s*[kK])?';
            if (preg_match('/(' . $money . ')\s*(?:to|[-–])\s*(' . $money . ')/', $userText, $m)) {
                $a = $this->parseMoneyToken($m[1]);
                $b = $this->parseMoneyToken($m[2]);
                $this->aiBudgetMin = min($a, $b);
                $this->aiBudgetMax = max($a, $b);
            } elseif (preg_match('/(\d+(?:,\d{3})*\s*[kK])\b/', $userText, $m)) {
                $v = $this->parseMoneyToken($m[1]);
                if ($v > 0) $this->aiBudgetMin = $this->aiBudgetMax = $v;
            } elseif (preg_match('/(\d[\d,]*)/', $userText, $m)) {
                $v = $this->parseMoneyToken($m[1]);
                if ($v > 0) $this->aiBudgetMin = $this->aiBudgetMax = $v;
            }
        }
        // 'dates' has no generic fallback — parseAiPrompt() already tried
        // every date pattern we understand on this same text; fabricating a
        // date instead of recognizing one would be worse than asking again.
    }

    // Smarter last-resort extraction via Gemini (or Groq/OpenRouter if
    // Gemini's not available), only reached when the free regex pass above
    // couldn't fill in something we still need — handles phrasings regex
    // was never taught (missing prepositions, typos, dense sentences with
    // several details at once), and also classifies whether the message is
    // off-topic or just a greeting the fast keyword check didn't recognize.
    // Returns 'off_topic', 'greeting', or '' (normal — proceed with
    // extraction). Silently treats any failure (bad key, quota, network) as
    // '' so a flaky API call never crashes the conversation or blocks it —
    // it just falls through to asking again normally.
    private function extractWithAi(string $userText): string
    {
        // Same reasoning as suggestDestination() above: Gemini → Groq →
        // OpenRouter, each up to 18s, called synchronously from
        // automateTrip() on every chat message while any slot is still
        // missing — without this bump, a slow provider or two pushes the
        // total past PHP's default 30s and fatals with an uncatchable
        // "Maximum execution time exceeded" instead of a clean fallback.
        set_time_limit(90);

        $known = sprintf(
            "- Origin: %s\n- Destination: %s\n- Budget: %s\n- Travel dates: %s",
            $this->aiFrom !== '' ? $this->aiFrom : 'unknown',
            $this->aiTo !== '' ? $this->aiTo : 'unknown',
            ($this->aiBudgetMin || $this->aiBudgetMax) ? "₱{$this->aiBudgetMin}-₱{$this->aiBudgetMax}" : 'unknown',
            ($this->aiDateFrom && $this->aiDateTo) ? "{$this->aiDateFrom} to {$this->aiDateTo}" : 'unknown',
        );

        $today = date('l, M j, Y'); // e.g. "Wednesday, Jul 29, 2026"

        $prompt = <<<PROMPT
You are a travel planning assistant extracting structured information from a traveler's message.

Today's date is {$today}.

Known information so far:
{$known}

Traveler's new message: "{$userText}"

First, decide:
1. Is this message COMPLETELY UNRELATED to travel planning — e.g. asking you to write code, general trivia (sports scores, history, etc.), or any other task that has nothing to do with planning a trip? A message that just doesn't mention a specific field yet (like "not sure" or a vague reply) is NOT off-topic — only mark it off-topic if it's asking for something entirely outside travel.
2. Is this message JUST a greeting or small-talk pleasantry (e.g. "hey there", "how's it going?", "good to see you") with no actual travel information and no off-topic request either?
3. Is this message abusive, insulting, or vulgar — profanity, harassment, or hate speech directed at the assistant or in general — regardless of whether it also contains real travel details?

Return JSON only, no markdown:
{
  "off_topic": true or false,
  "is_greeting": true or false,
  "is_inappropriate": true or false,
  "origin": "city name or null",
  "destination": "city name or null",
  "budget_min": number or null,
  "budget_max": number or null,
  "date_from": "abbreviated MONTH name + day, e.g. 'Jul 28' (NOT a weekday name) — or null",
  "date_to": "abbreviated MONTH name + day + year, e.g. 'Jul 30, 2026' (NOT a weekday name) — or null"
}

Rules:
- If "off_topic", "is_greeting", or "is_inappropriate" is true, set every other field to null.
- Only one of "off_topic", "is_greeting", "is_inappropriate" can be true at once — pick whichever fits best, or leave all three false if it contains real travel info.
- Only include a field if this message actually mentions or changes it.
- If a field isn't mentioned in this message, return null for it — do not guess or repeat the known values above.
- If information is ambiguous, return null for that field rather than assuming.
- Dates: if the traveler gives a RELATIVE time frame instead of a calendar date ("next week", "this weekend", "tomorrow", "in 3 days") and/or a DURATION instead of an end date ("for 5 days", "for a week"), compute the actual calendar dates yourself using today's date above as the reference point, and return real dates in the "Jul 28" / "Jul 30, 2026" style shown above — never return the relative phrase itself, and never return a day-of-the-week name.
PROMPT;

        try {
            $raw = (new GeminiService())->generate($prompt);
            if (!$raw) {
                $raw = (new GroqService())->generate($prompt);
            }
            if (!$raw) {
                $raw = (new OpenRouterService())->generate($prompt);
            }
            if (!$raw) return '';

            $raw = trim(preg_replace('/```json\s*|```\s*/i', '', $raw));
            $data = json_decode($raw, true);
            if (!is_array($data)) return '';
        } catch (\Throwable) {
            return '';
        }

        if (!empty($data['off_topic']))        return 'off_topic';
        if (!empty($data['is_greeting']))      return 'greeting';
        if (!empty($data['is_inappropriate'])) return 'inappropriate';

        // Only fill in slots that are still genuinely empty — this call runs
        // whenever ANYTHING is missing, not necessarily dates/budget/etc.,
        // so it must never clobber a field the regex pass already resolved
        // correctly earlier in this same turn (the AI re-derives every
        // field from scratch each time, and a different — possibly worse —
        // interpretation of the same text should never overwrite a good one).
        // knownPlaceName(), not just an isNonAnswerFiller() check — the AI
        // can hallucinate or (since the traveler's own raw message is
        // embedded straight into the prompt above) be steered into
        // returning something that isn't a real place at all. Requiring it
        // to resolve to an actual known place applies the exact same gate
        // the regex/direct-answer paths already use, so this path can't be
        // used to slip an arbitrary string past the "is this a place?"
        // check the way "Code" once did on the other paths.
        if ($this->aiFrom === '' && !empty($data['origin'])) {
            $resolved = $this->knownPlaceName($this->cleanCityName($data['origin']));
            if ($resolved !== '') {
                $this->aiFrom = $resolved;
            }
        }
        if ($this->aiTo === '' && !empty($data['destination'])) {
            $resolved = $this->knownPlaceName($this->cleanCityName($data['destination']));
            if ($resolved !== '') {
                $this->aiTo = $resolved;
            }
        }

        if ($this->aiBudgetMin === 0 && $this->aiBudgetMax === 0
            && (!empty($data['budget_min']) || !empty($data['budget_max']))) {
            $this->aiBudgetMin = (int) ($data['budget_min'] ?? $data['budget_max']);
            $this->aiBudgetMax = (int) ($data['budget_max'] ?? $data['budget_min']);
        }

        if (($this->aiDateFrom === '' || $this->aiDateTo === '')
            && !empty($data['date_from']) && !empty($data['date_to'])) {
            $tsFrom = strtotime($data['date_from'] . ' ' . date('Y'));
            $tsTo   = strtotime($data['date_to']);
            // Sanity check: reject anything landing in the past. The AI's
            // own date arithmetic for relative phrases ("this weekend") is
            // occasionally wrong — a travel date before today is never a
            // valid resolution of that, so treat it as if nothing came back
            // rather than silently accepting a hallucinated past date.
            if ($tsFrom && $tsTo && $tsFrom >= strtotime('today')) {
                $this->aiDateFrom = $data['date_from'];
                $this->aiDateTo   = $data['date_to'];
                $this->aiDays     = (int) ceil(abs($tsTo - $tsFrom) / 86400) + 1;
            }
        }

        return '';
    }

    // The slot key ('destination'|'origin'|'budget'|'dates') that
    // questionFor() would ask about next, or '' once everything is known.
    private function missingSlotKey(): string
    {
        if ($this->aiTo === '') return 'destination';
        if ($this->aiFrom === '') return 'origin';
        if ($this->aiBudgetMin === 0 && $this->aiBudgetMax === 0) return 'budget';
        if ($this->aiDateFrom === '' || $this->aiDateTo === '') return 'dates';
        return '';
    }

    // Returns a friendly follow-up question for the given slot — asked one
    // at a time to feel like an actual conversation instead of a form dump.
    // $missCount is how many times in a row this exact slot has gone
    // unresolved: 0 = first time asking, 1 = missed once, 2+ = missed
    // repeatedly. Each tier has its own phrasing so three-plus misses in a
    // row don't just alternate between the same two sentences forever —
    // the final tier is deliberately stable (not endlessly novel) since at
    // that point a clear, concrete example is more useful than variety.
    private function questionFor(string $slot, int $missCount): string
    {
        if ($missCount <= 0) {
            return match ($slot) {
                'destination' => "Sure! Where would you like to go?",
                'origin'      => "Nice choice! Where will you be traveling from?",
                'budget'      => "Got it. What's your budget for this trip?",
                'dates'       => "Got it. When are you planning to travel? (e.g. \"August 3 to 10\")",
                default       => '',
            };
        }

        if ($missCount === 1) {
            return match ($slot) {
                'destination' => "Sorry, I didn't quite catch a destination there — which place would you like to go to?",
                'origin'      => "Hmm, I still need a starting point — what city will you be flying from?",
                'budget'      => "I didn't catch a number — roughly how much do you want to spend, e.g. \"20000\" or \"20k\"?",
                'dates'       => "I still need actual travel dates — something like \"August 3 to 10\" or \"8/3/2026\" works best.",
                default       => '',
            };
        }

        // Missed 2+ times in a row — stop rephrasing and just give the
        // most concrete, copy-pasteable example possible.
        return match ($slot) {
            'destination' => "Let's try just the place name by itself — for example: Boracay",
            'origin'      => "Just the city name works — for example: Manila",
            'budget'      => "Just a plain number works — for example: 20000",
            'dates'       => "Just the dates works — for example: August 3 to 10, 2026",
            default       => '',
        };
    }

    // The full user-side conversation so far, joined into one string —
    // used wherever the old single-shot prompt text was needed (the Gemini
    // call and the saved trip's notes), since aiPrompt itself is cleared
    // after every turn now.
    private function conversationSummary(): string
    {
        $summary = collect($this->messages)
            ->where('role', 'user')
            ->pluck('text')
            ->implode('. ');

        if (!empty($this->profileInterests)) {
            $summary .= '. Traveler interests: ' . implode(', ', $this->profileInterests) . '.';
        }

        return $summary;
    }

    // Called by the loading screen's 3-second frontend timer (a fallback
    // meant to nudge the UI forward if something delays the normal
    // transition) — guarded so it can never do that BEFORE the package
    // actually exists. processAiTrip() itself already sets aiStep to
    // 'results' the moment it finishes; without this guard, a slow AI/
    // SerpAPI chain (which can legitimately now take minutes, not seconds,
    // since the execution-time fixes above) would let this fire first and
    // flip to a "results" step with no data — a blank page, since neither
    // the loading nor the results view's @if condition would match.
    public function showResults(): void
    {
        if (!empty($this->aiPackage)) {
            $this->aiStep = 'results';
        }
    }

    #[On('ai-process-trip')]
    public function processAiTrip(): void
    {
        // Worst case, this one method can chain through: Layer 1's 3 AI
        // providers (Gemini 18s + Groq up to ~38s with its retry-on-429 +
        // OpenRouter up to ~38s with its own retry ≈ 94s), THEN Layer 2's 4
        // sequential SerpAPI searches (each up to ~61s with its own
        // retry(2) ≈ 244s), THEN Layer 2's own Gemini/Groq/OpenRouter
        // enrichment pass (≈ 94s again) — around 430s total. Comfortably
        // past PHP's default 30s max_execution_time, which is a hard fatal
        // error (not a catchable exception), so no try/catch can protect
        // against it the way the ones below do for individual provider
        // failures. Extend the budget for this one action to cover the full
        // chain, not just the first layer.
        set_time_limit(450);

        // Layer 1: Gemini — parse natural language + full package.
        // A network-level failure (timeout, DNS, connection refused) throws
        // a ConnectionException rather than just returning an unsuccessful
        // response — unlike an HTTP error status, that's never caught by a
        // "did the call come back with a package?" check alone, so each
        // provider attempt is wrapped here the same way extractWithAi() and
        // buildSerpApiPackage()'s enrichment step already are: any failure,
        // of any kind, just means "try the next provider" instead of
        // crashing the whole request.
        $summary = $this->conversationSummary();
        $package = null;
        try {
            $package = (new GeminiService())->planTrip($summary);
        } catch (\Throwable) {
            // fall through to Groq
        }

        // Layer 1.5: Groq — same job, tried only when Gemini didn't come back
        // with a usable package (e.g. quota exhausted, key issue, timeout).
        if (!$package || empty($package['to'])) {
            try {
                $package = (new GroqService())->planTrip($summary);
            } catch (\Throwable) {
                $package = null;
            }
        }

        // Layer 1.7: OpenRouter — third AI provider, tried only if both of
        // the above came up empty (both down, both out of quota, etc.).
        if (!$package || empty($package['to'])) {
            try {
                $package = (new OpenRouterService())->planTrip($summary);
            } catch (\Throwable) {
                $package = null;
            }
        }

        if ($package && !empty($package['to'])) {
            $this->aiFrom      = $this->cleanCityName($package['from']      ?? $this->aiFrom);
            $this->aiTo        = $this->cleanCityName($package['to']        ?? $this->aiTo);
            // The AI free-generates transport.from_code/to_code as part of
            // the same JSON response, independently of the from/to fields
            // just resolved above — it can (and does) end up inconsistent
            // with them, e.g. correctly naming "Cebu City" as the origin
            // but still defaulting the flight route to Manila (MNL), since
            // the prompt's own "default Manila if not mentioned" instruction
            // bleeds into the transport leg even when a different origin
            // was clearly given. Overwrite with codes derived from the
            // already-resolved cities instead of trusting the AI's own,
            // the same way buildSerpApiPackage() (Layer 2) already does.
            if (isset($package['transport']) && is_array($package['transport'])) {
                $package['transport']['from_code'] = $this->resolveCode($this->aiFrom ?: 'Manila');
                $package['transport']['to_code']   = $this->resolveCode($this->aiTo);
            }
            // Only accept the AI's budget if nothing was already resolved
            // earlier in the conversation. This call re-reads the WHOLE
            // transcript from scratch and has no reason to be more
            // trustworthy than a value already parsed correctly turn-by-
            // turn — unconditionally overwriting here previously let it
            // replace a correctly-parsed budget with a wrong one (confirmed
            // live: it mixed up a traveler's "15000" budget with the year
            // "2026" sitting right next to their travel dates in the same
            // transcript). Same "only fill if still empty" rule
            // extractWithAi() already applies to its own budget fields,
            // just missing here until now.
            if ($this->aiBudgetMin === 0 && $this->aiBudgetMax === 0
                && (!empty($package['budget_min']) || !empty($package['budget_max']))) {
                $this->aiBudgetMin = (int) ($package['budget_min'] ?? $package['budget_max']);
                $this->aiBudgetMax = (int) ($package['budget_max'] ?? $package['budget_min']);
            }
            // Same "only fill if still empty" guard as above, applied to
            // dates for the same reason — this call re-deriving dates from
            // the whole transcript and overwriting already-correct ones is
            // exactly what produced the budget bug above; dates were never
            // protected against the same failure mode.
            // Also carries the sanity checks extractWithAi() already
            // applies before accepting an AI-supplied date:
            // 1. A non-date placeholder ("TBD", "Flexible") when the
            //    conversation never actually pinned down real dates —
            //    strtotime() on that silently returns false rather than
            //    throwing, and every later date('Y-m-d', false) then casts
            //    to 0 and produces "1970-01-01" with no error to catch.
            // 2. A syntactically valid but wrong date the AI's own date
            //    arithmetic hallucinated for a relative phrase ("1 week") —
            //    date_from has no year in this format, so strtotime()
            //    assumes the current one; reject anything landing before
            //    today rather than silently building a trip in the past.
            if ($this->aiDateFrom === ''
                && !empty($package['date_from'])
                && ($tsFrom = strtotime($package['date_from'])) !== false
                && $tsFrom >= strtotime('today')) {
                $this->aiDateFrom = $package['date_from'];
            }
            if ($this->aiDateTo === '' && !empty($package['date_to']) && strtotime($package['date_to']) !== false) {
                $this->aiDateTo = $package['date_to'];
            }
            if ($this->aiDays === 0 && !empty($package['days'])) {
                $this->aiDays = (int) $package['days'];
            }

            $transportCost     = (int)($package['transport']['cost']     ?? 0);
            $accommodationCost = (int)($package['accommodation']['cost'] ?? 0);
            $foodCost          = (int)($package['food']['cost']          ?? 0);
            $attractionsCost   = (int)($package['attractions']['cost']   ?? 0);

            // Recomputed here rather than trusting the AI's own self-reported
            // "total"/"pct" — its arithmetic (over line items it just made
            // up from a prompt built from the traveler's own raw message)
            // can't be trusted to actually add up. buildSerpApiPackage()
            // below already does its own sum for exactly this reason; this
            // path previously didn't, and a wrong self-reported total would
            // sail straight into a real, misleading budget total.
            $budget = $this->aiBudgetMax ?: $this->aiBudgetMin ?: 30000;
            $total  = $transportCost + $accommodationCost + $foodCost + $attractionsCost;

            $this->aiPackage   = [
                'transport'     => $package['transport']     ?? [],
                'accommodation' => $package['accommodation'] ?? [],
                'food'          => $package['food']          ?? [],
                'attractions'   => $package['attractions']   ?? ['items'=>[],'cost'=>0],
                'total'         => $total,
                'budget'        => $budget,
                'pct'           => min(100, (int)round($total / $budget * 100)),
            ];
            $this->aiStep = 'results';
            return;
        }

        // Layer 2: SerpAPI — real live data per category
        $this->parseAiPrompt();
        $serpPackage = $this->buildSerpApiPackage();
        if ($serpPackage) {
            $this->aiPackage = $serpPackage;
            $this->aiStep    = 'results';
            return;
        }

        // Layer 3: Static lookup + generic fallback
        $this->generateAiPackage();
        $this->aiStep = 'results';
    }

    private function buildSerpApiPackage(): ?array
    {
        if (empty($this->aiTo)) return null;

        $serp   = new SerpApiService();
        $days   = max(1, $this->aiDays);
        $budget = $this->aiBudgetMax ?: $this->aiBudgetMin ?: 30000;

        // Convert parsed display dates back to Y-m-d for API params
        // aiDateFrom: "Aug 3"  aiDateTo: "Aug 10, 2026"
        $year = date('Y');
        if ($this->aiDateTo && preg_match('/(\d{4})$/', $this->aiDateTo, $ym)) {
            $year = $ym[1];
        }
        $checkIn  = $this->aiDateFrom
            ? date('Y-m-d', strtotime($this->aiDateFrom . ', ' . $year))
            : date('Y-m-d');
        $checkOut = $this->aiDateTo
            ? date('Y-m-d', strtotime($this->aiDateTo))
            : date('Y-m-d', strtotime("+{$days} days"));

        // Determine origin IATA code
        $fromCode = $this->resolveCode($this->aiFrom ?: 'Manila');
        $toCode   = $this->resolveCode($this->aiTo);

        // Budget split targets
        $transportBudget     = (int)round($budget * 0.18);
        $accommodationBudget = (int)round($budget * 0.50);
        $foodBudget          = (int)round($budget * 0.28);
        $attractionBudget    = (int)round($budget * 0.04);

        // Fetch all 4 from SerpAPI (each call independent with its own timeout)
        $gen = $this->aiGenCount; // 0 = first (best within budget), 1+ = cheaper
        $flightData  = $serp->searchFlights($fromCode, $toCode, $checkIn, $checkOut, $gen, $transportBudget);
        $hotelData   = $serp->searchHotels($this->aiTo, $checkIn, $checkOut, $days, $gen, $accommodationBudget);
        $restaurData = $serp->searchRestaurants($this->aiTo, $days, $foodBudget, $gen);
        $attrItems   = $serp->searchAttractions($this->aiTo, $gen);

        // SerpAPI's daily quota (or the real account's own limit) can be
        // exhausted independently of Serper's — every other place in the
        // app that calls SerpApiService already retries via Serper when it
        // comes back empty (see TripPlannerWizard), but this method never
        // did, so hitting the SerpAPI limit here always fell straight to
        // the fully static Layer 3 lookup table instead. Serper's own
        // methods return a LIST of raw options (the same "raw" shape used
        // for manual selection elsewhere), not the single pre-picked
        // {detail, cost} summary this method needs, so each fallback below
        // picks one item and reshapes it into the exact summary shap
        // SerpApiService's own methods already produce — everything
        // downstream stays unchanged either way. Picking still honors
        // $gen via pickFromPool() using the same "gen=0 best, gen>0 slides
        // to a different pick" contract SerpApiService's methods already
        // follow, so Regenerate keeps working regardless of which service
        // actually answered.
        if (!$flightData) {
            $list = (new SerperService())->searchFlights($fromCode, $toCode, $checkIn, $checkOut);
            if ($list) {
                usort($list, fn($a, $b) => ($a['price'] ?? 0) <=> ($b['price'] ?? 0));
                $pick = $this->pickFromPool($list, $gen);
                $flightData = [
                    'detail' => trim(($pick['airline'] ?? 'Airline') . ' ' . ($pick['number'] ?? '')) . ' · ' . ($pick['type'] ?? 'Round Trip'),
                    'cost'   => (int) ($pick['price'] ?? 0),
                ];
            }
        }
        if (!$hotelData) {
            $list = (new SerperService())->searchHotels($this->aiTo, $checkIn, $checkOut, $days);
            if ($list) {
                usort($list, fn($a, $b) => ($a['total'] ?? 0) <=> ($b['total'] ?? 0));
                $pick = $this->pickFromPool($list, $gen);
                $hotelData = [
                    'name'   => $pick['name'] ?? ('Hotel in ' . $this->aiTo),
                    'stars'  => $pick['stars'] ?? 3,
                    'detail' => $days . ' Nights · Standard Room · ' . $this->aiTo,
                    'cost'   => (int) ($pick['total'] ?? 0),
                ];
            }
        }
        if (!$restaurData) {
            $list = (new SerperService())->searchRestaurants($this->aiTo);
            if ($list) {
                $pick = $this->pickFromPool($list, $gen);
                $restaurData = [
                    'name'   => $pick['name'] ?? ('Dining in ' . $this->aiTo),
                    'detail' => $days . ' Days · Breakfast, Lunch, & Dinner · ' . $this->aiTo,
                    'cost'   => (int) round((float) ($pick['priceMax'] ?? $pick['priceMin'] ?? 500) * $days),
                ];
            }
        }
        if (!$attrItems) {
            $list = (new SerperService())->searchAttractions($this->aiTo);
            if ($list) {
                $offset    = $gen === 0 ? 0 : min($gen * 3, max(0, count($list) - 3));
                $attrItems = array_map(
                    fn($a) => [$a['name'] ?? 'Attraction', ($a['isFree'] ?? false) ? 'Free' : '₱' . number_format((int) ($a['price'] ?? 300))],
                    array_slice($list, $offset, 3)
                );
            }
        }

        // Fall through to static lookup only if every call failed
        if (!$flightData && !$hotelData && !$restaurData && !$attrItems) return null;

        // ── Transport ────────────────────────────────────────────────────
        $transport = $flightData
            ? array_merge(['label'=>'TRANSPORTATION','icon'=>'fa-solid fa-plane','from_code'=>$fromCode,'to_code'=>$toCode], $flightData)
            : ['label'=>'TRANSPORTATION','icon'=>'fa-solid fa-plane','from_code'=>$fromCode,'to_code'=>$toCode,'detail'=>'Direct Flight · Round Trip','cost'=>$transportBudget];

        // ── Accommodation (15km radius via ll param) ──────────────────────
        $accommodation = $hotelData
            ? array_merge(['label'=>'ACCOMMODATION','icon'=>'fa-solid fa-bed'], $hotelData)
            : ['label'=>'ACCOMMODATION','icon'=>'fa-solid fa-bed','name'=>'Hotel in '.$this->aiTo,'stars'=>3,'detail'=>$days.' Nights · Standard Room · '.$this->aiTo,'cost'=>$accommodationBudget];

        // ── Food & Dining (15km radius via ll param) ──────────────────────
        $food = $restaurData
            ? array_merge(['label'=>'FOOD & DINING','icon'=>'fa-solid fa-utensils'], $restaurData)
            : ['label'=>'FOOD & DINING','icon'=>'fa-solid fa-utensils','name'=>'Dining in '.$this->aiTo,'detail'=>$days.' Days · Breakfast, Lunch, & Dinner · '.$this->aiTo,'cost'=>$foodBudget];

        // ── Attractions (google_travel_explore → google_maps fallback) ────
        $items    = $attrItems ?? [[$this->aiTo . ' City Tour', '₱300'],['Local Market Visit','Free']];
        $attrCost = array_sum(array_map(
            fn($a) => is_numeric(str_replace(['₱',','], '', $a[1])) ? (int)str_replace(['₱',','], '', $a[1]) : 0,
            $items
        )); // 0 is valid — don't fallback to budget when all are free
        $attractions = ['label'=>'ATTRACTIONS','icon'=>'fa-solid fa-landmark','items'=>$items,'cost'=>$attrCost];

        $rawPackage = [
            'transport'     => $transport,
            'accommodation' => $accommodation,
            'food'          => $food,
            'attractions'   => $attractions,
        ];

        // ── Gemini enrichment — fix generic names / missing prices ────────
        // (Groq, then OpenRouter, as backups when Gemini's unavailable —
        // same job, same rules.)
        try {
            $enriched = (new GeminiService())->enrichPackage($rawPackage, $this->aiTo, $days, $budget);
            if (!$enriched) {
                $enriched = (new GroqService())->enrichPackage($rawPackage, $this->aiTo, $days, $budget);
            }
            if (!$enriched) {
                $enriched = (new OpenRouterService())->enrichPackage($rawPackage, $this->aiTo, $days, $budget);
            }
            if ($enriched && is_array($enriched)) {
                // Merge enriched strings back but preserve all numeric costs
                foreach (['transport','accommodation','food','attractions'] as $key) {
                    if (!isset($enriched[$key])) continue;
                    foreach ($enriched[$key] as $field => $val) {
                        if (!is_numeric($val)) {
                            $rawPackage[$key][$field] = $val;
                        }
                    }
                }
                // Re-sum attraction cost in case Gemini added real prices
                if (!empty($rawPackage['attractions']['items'])) {
                    $rawPackage['attractions']['cost'] = array_sum(array_map(
                        fn($a) => is_numeric(str_replace(['₱',','], '', $a[1] ?? '')) ? (int)str_replace(['₱',','], '', $a[1]) : 0,
                        $rawPackage['attractions']['items']
                    ));
                }
            }
        } catch (\Throwable) {
            // Gemini failure is non-fatal; proceed with SerpAPI data as-is
        }

        $total = $rawPackage['transport']['cost']
               + $rawPackage['accommodation']['cost']
               + $rawPackage['food']['cost']
               + $rawPackage['attractions']['cost'];

        return array_merge($rawPackage, [
            'total'  => $total,
            'budget' => $budget,
            'pct'    => min(100, (int)round($total / $budget * 100)),
        ]);
    }

    // Returns the matched known-place name (map key) and its IATA code for
    // the best hit within free text, or null if nothing resolves. Both
    // iataCode() (just the code) and knownPlaceName() (just the canonical
    // name) are thin wrappers around this, so free-text matching logic
    // lives in exactly one place.
    private function matchKnownPlace(string $city): ?array
    {
        $map = [
            // Philippines
            'manila' => 'MNL', 'pasay' => 'MNL', 'makati' => 'MNL', 'quezon city' => 'MNL',
            'paranaque' => 'MNL', 'pasig' => 'MNL', 'taguig' => 'MNL', 'las pinas' => 'MNL',
            'ncr' => 'MNL', 'metro manila' => 'MNL', 'tagaytay' => 'MNL',
            'cebu' => 'CEB', 'cebu city' => 'CEB', 'mactan' => 'CEB',
            'davao' => 'DVO', 'davao city' => 'DVO',
            'boracay' => 'MPH', 'kalibo' => 'KLO', 'malay' => 'MPH',
            'bohol' => 'TAG', 'tagbilaran' => 'TAG', 'tagbilaran (bohol)' => 'TAG',
            'palawan' => 'PPS', 'puerto princesa' => 'PPS',
            'el nido' => 'ENI',
            'coron' => 'USU', 'busuanga' => 'USU',
            'siargao' => 'IAO', 'del carmen' => 'IAO',
            'bacolod' => 'BCD',
            'iloilo' => 'ILO', 'iloilo city' => 'ILO',
            'zamboanga' => 'ZAM',
            'cagayan de oro' => 'CGY', 'cagayan' => 'CGY',
            'general santos' => 'GES', 'gensan' => 'GES',
            'tacloban' => 'TAC', 'leyte' => 'TAC',
            'dumaguete' => 'DGT', 'siquijor' => 'DGT',
            'surigao' => 'SUG',
            'cotabato' => 'CBO',
            'batanes' => 'BSO', 'basco' => 'BSO',
            'camiguin' => 'CGM',
            'laoag' => 'LAO',
            'vigan' => 'VIG',
            'baguio' => 'BAG',
            'legazpi' => 'LGP', 'legazpi city' => 'LGP',
            'naga' => 'WNP', 'naga city' => 'WNP',
            'roxas' => 'RXS', 'roxas city' => 'RXS',
            'san jose' => 'SJI',
            'ozamiz' => 'OZC',
            'dipolog' => 'DPL',
            'butuan' => 'BXU',
            'pagadian' => 'PAG',
            'virac' => 'VRC',
            'tuguegarao' => 'TUG',
            'cauayan' => 'CYZ',
            'puerto galera' => 'MNL',
            // Southeast Asia
            'singapore' => 'SIN',
            'bangkok' => 'BKK', 'thailand' => 'BKK', 'suvarnabhumi' => 'BKK',
            'phuket' => 'HKT', 'krabi' => 'KBV', 'chiang mai' => 'CNX',
            'bali' => 'DPS', 'denpasar' => 'DPS',
            'jakarta' => 'CGK', 'indonesia' => 'CGK',
            'kuala lumpur' => 'KUL', 'malaysia' => 'KUL', 'kl' => 'KUL',
            'penang' => 'PEN', 'langkawi' => 'LGK', 'kota kinabalu' => 'BKI',
            'hong kong' => 'HKG',
            'macau' => 'MFM',
            'ho chi minh city' => 'SGN', 'ho chi minh' => 'SGN', 'hcmc' => 'SGN', 'saigon' => 'SGN',
            'hanoi' => 'HAN', 'vietnam' => 'SGN',
            'da nang' => 'DAD',
            'yangon' => 'RGN', 'myanmar' => 'RGN',
            'phnom penh' => 'PNH', 'cambodia' => 'PNH',
            'siem reap' => 'REP',
            'vientiane' => 'VTE', 'laos' => 'VTE',
            'colombo' => 'CMB', 'sri lanka' => 'CMB',
            'dhaka' => 'DAC', 'bangladesh' => 'DAC',
            'kathmandu' => 'KTM', 'nepal' => 'KTM',
            // East Asia
            'tokyo' => 'NRT', 'japan' => 'NRT',
            'osaka' => 'KIX',
            'nagoya' => 'NGO',
            'fukuoka' => 'FUK',
            'sapporo' => 'CTS',
            'okinawa' => 'OKA',
            'seoul' => 'ICN', 'korea' => 'ICN', 'incheon' => 'ICN',
            'busan' => 'PUS',
            'taipei' => 'TPE', 'taiwan' => 'TPE',
            'kaohsiung' => 'KHH',
            'beijing' => 'PEK', 'china' => 'PEK',
            'shanghai' => 'PVG',
            'guangzhou' => 'CAN',
            'shenzhen' => 'SZX',
            // South Asia
            'delhi' => 'DEL', 'new delhi' => 'DEL', 'india' => 'DEL',
            'mumbai' => 'BOM', 'bombay' => 'BOM',
            'bangalore' => 'BLR', 'bengaluru' => 'BLR',
            'chennai' => 'MAA', 'madras' => 'MAA',
            'kolkata' => 'CCU',
            'hyderabad' => 'HYD',
            // Middle East
            'dubai' => 'DXB', 'uae' => 'DXB',
            'abu dhabi' => 'AUH',
            'doha' => 'DOH', 'qatar' => 'DOH',
            'riyadh' => 'RUH', 'saudi arabia' => 'RUH',
            'jeddah' => 'JED',
            'kuwait' => 'KWI', 'kuwait city' => 'KWI',
            'bahrain' => 'BAH',
            'muscat' => 'MCT', 'oman' => 'MCT',
            'amman' => 'AMM', 'jordan' => 'AMM',
            'tel aviv' => 'TLV', 'israel' => 'TLV',
            'istanbul' => 'IST', 'turkey' => 'IST',
            // Europe
            'london' => 'LHR',
            'paris' => 'CDG',
            'amsterdam' => 'AMS',
            'frankfurt' => 'FRA', 'germany' => 'FRA',
            'rome' => 'FCO', 'italy' => 'FCO',
            'madrid' => 'MAD', 'spain' => 'MAD',
            'barcelona' => 'BCN',
            'vienna' => 'VIE', 'austria' => 'VIE',
            'zurich' => 'ZRH', 'switzerland' => 'ZRH',
            'brussels' => 'BRU', 'belgium' => 'BRU',
            'lisbon' => 'LIS', 'portugal' => 'LIS',
            'athens' => 'ATH', 'greece' => 'ATH',
            'prague' => 'PRG', 'czech republic' => 'PRG',
            'budapest' => 'BUD', 'hungary' => 'BUD',
            'warsaw' => 'WAW', 'poland' => 'WAW',
            'stockholm' => 'ARN', 'sweden' => 'ARN',
            'oslo' => 'OSL', 'norway' => 'OSL',
            'copenhagen' => 'CPH', 'denmark' => 'CPH',
            'helsinki' => 'HEL', 'finland' => 'HEL',
            'moscow' => 'SVO', 'russia' => 'SVO',
            // Oceania
            'sydney' => 'SYD', 'australia' => 'SYD',
            'melbourne' => 'MEL',
            'brisbane' => 'BNE',
            'perth' => 'PER',
            'auckland' => 'AKL', 'new zealand' => 'AKL',
            // Americas
            'new york' => 'JFK', 'new york city' => 'JFK', 'nyc' => 'JFK',
            'los angeles' => 'LAX', 'la' => 'LAX',
            'san francisco' => 'SFO',
            'chicago' => 'ORD',
            'miami' => 'MIA',
            'toronto' => 'YYZ', 'canada' => 'YYZ',
            'vancouver' => 'YVR',
            'sao paulo' => 'GRU', 'brazil' => 'GRU',
            // Africa
            'nairobi' => 'NBO', 'kenya' => 'NBO',
            'johannesburg' => 'JNB', 'south africa' => 'JNB',
            'cairo' => 'CAI', 'egypt' => 'CAI',
            'casablanca' => 'CMN', 'morocco' => 'CMN',
            // Maldives
            'maldives' => 'MLE', 'male' => 'MLE',
        ];

        $key = strtolower(trim($city));
        if (isset($map[$key])) return ['name' => $key, 'code' => $map[$key]];

        // No exact match — try every contiguous run of words, longest first,
        // at every starting position. Free-text answers often tack extra
        // words onto EITHER side of the actual place name: a country/region
        // after it ("Tokyo Japan", "Cebu City, Philippines" — both "tokyo"
        // and "japan" are their own entries above), or filler words before
        // it from a natural sentence reply ("Actually I want Cebu"). Scanning
        // every window catches both instead of only ever trimming the end.
        // Split on sentence punctuation too, not just whitespace/commas — a
        // trailing "?" or "!" ("maybe Cebu?") would otherwise stay glued to
        // the word and never match the map's plain "cebu" key.
        $words = preg_split('/[\s,!?.;:]+/', $key, -1, PREG_SPLIT_NO_EMPTY);
        $count = count($words);
        for ($len = $count - 1; $len >= 1; $len--) {
            for ($start = 0; $start + $len <= $count; $start++) {
                $candidate = implode(' ', array_slice($words, $start, $len));
                if (isset($map[$candidate])) return ['name' => $candidate, 'code' => $map[$candidate]];
            }
        }

        return null;
    }

    // Public lookup for wherever only the IATA code matters (SerpAPI calls,
    // etc.) — thin wrapper so the free-text matching logic isn't duplicated.
    public function iataCode(string $city): string
    {
        return $this->matchKnownPlace($city)['code'] ?? '';
    }

    // Returns the canonical, properly-cased NAME of whichever known place was
    // found within $text — NOT the raw input. "Actually I want Cebu"
    // resolves to "Cebu", not the whole padded sentence. Empty string if
    // nothing in $text matches a known place.
    private function knownPlaceName(string $text): string
    {
        $match = $this->matchKnownPlace($text);
        return $match !== null ? ucwords($match['name']) : '';
    }

    private function resolveCode(string $city): string
    {
        $code = $this->iataCode($city);
        return $code !== '' ? $code : trim($city);
    }

    // True when origin and destination are the same place — either the
    // exact same name, or two names that resolve to the same airport (e.g.
    // "Makati" and "Manila" both serve MNL). Checked right before a trip
    // would be generated so "Manila to Manila" never sails through silently.
    private function sameOriginAndDestination(): bool
    {
        if ($this->aiFrom === '' || $this->aiTo === '') return false;
        if (strtolower($this->aiFrom) === strtolower($this->aiTo)) return true;

        $fromCode = $this->iataCode($this->aiFrom);
        return $fromCode !== '' && $fromCode === $this->iataCode($this->aiTo);
    }

    // The minimum realistic trip budget to accept. Prefers the traveler's
    // own daily_budget preference (set during profile onboarding) when
    // they have one, since that's a real personal signal rather than a
    // guess on our part — falls back to a flat ₱500 safety net otherwise.
    // Mirrors the same daily_budget floor
    // TripPlannerWizard::runItineraryGeneration() already applies, for
    // consistency between the two planning paths.
    private function budgetFloor(): int
    {
        $profileDailyBudget = (int) (auth()->user()?->userProfile?->daily_budget ?? 0);
        return $profileDailyBudget > 0 ? $profileDailyBudget : 500;
    }

    // Every non-peso currency symbol/code offered on the Settings →
    // Preferences display-currency picker — deliberately excludes PHP/₱
    // itself, since that's already handled correctly everywhere.
    // Several symbols here have a visually-identical "fullwidth" lookalike
    // in Unicode's Fullwidth Forms block (U+FF00–FFEF) — a different code
    // point some keyboards/IMEs produce instead of the standard one.
    // Confirmed live: a traveler's ¥ (typed as the fullwidth U+FFE5, not
    // the standard U+00A5) went completely undetected and silently fell
    // through to the plain-peso bare-number branch instead of converting.
    // Rather than fix that one symbol reactively, every other currency
    // symbol used here with a known fullwidth counterpart ($, £, ₩) is
    // mapped alongside its standard form too, closing the same class of
    // bug proactively instead of waiting to hit each one individually.
    private const NON_PESO_CURRENCY_NAMES = [
        '$' => 'US dollars', '＄' => 'US dollars', 'usd' => 'US dollars',
        '€' => 'euros', 'eur' => 'euros',
        '£' => 'British pounds', '￡' => 'British pounds', 'gbp' => 'British pounds',
        '¥' => 'Japanese yen', '￥' => 'Japanese yen', 'jpy' => 'Japanese yen',
        '₩' => 'Korean won', '￦' => 'Korean won', 'krw' => 'Korean won',
        'sgd' => 'Singapore dollars', 'aud' => 'Australian dollars',
        'hkd' => 'Hong Kong dollars', 'thb' => 'Thai baht',
        'myr' => 'Malaysian ringgit', 'aed' => 'UAE dirhams',
    ];

    // Approximate PHP conversion rates, keyed by the same currency names
    // above — good enough for a rough trip-budget estimate, deliberately
    // not a live/financial-grade rate (no external rate API dependency).
    private const NON_PESO_CURRENCY_RATES = [
        'US dollars'         => 56,
        'euros'              => 61,
        'British pounds'     => 71,
        'Japanese yen'       => 0.38,
        'Korean won'         => 0.041,
        'Singapore dollars'  => 42,
        'Australian dollars' => 37,
        'Hong Kong dollars'  => 7.2,
        'Thai baht'          => 1.6,
        'Malaysian ringgit'  => 12.5,
        'UAE dirhams'        => 15.3,
    ];

    private const CURRENCY_DISPLAY_SYMBOLS = [
        'US dollars' => '$', 'euros' => '€', 'British pounds' => '£',
        'Japanese yen' => '¥', 'Korean won' => '₩', 'Singapore dollars' => 'S$',
        'Australian dollars' => 'A$', 'Hong Kong dollars' => 'HK$',
        'Thai baht' => '฿', 'Malaysian ringgit' => 'RM', 'UAE dirhams' => 'AED ',
    ];

    // Finds a non-peso currency marker together with its amount ("$1,500",
    // "1500 USD" — either order) and converts it to pesos. Returns null if
    // no currency marker is found, or one is found with no adjacent number
    // (a bare "$" with no amount isn't a budget mention at all).
    private function detectAndConvertCurrency(string $text): ?array
    {
        $symbolOrCode = '(?:\$|＄|€|£|￡|¥|￥|₩|￦|USD|EUR|GBP|JPY|SGD|AUD|KRW|HKD|THB|MYR|AED)';
        // Comma-grouped ("1,500") OR a bare digit run of any length
        // ("1500", "2000", "10000") — \d{1,3} alone (with no bare-digit
        // fallback) can only ever capture the first 1-3 digits of a plain
        // 4+ digit number with no comma, silently truncating "1500" to
        // "150" and producing a wrong conversion. Comma-grouped is tried
        // first so "$1,500" still captures the full "1,500" as one token
        // rather than stopping at the comma.
        $number = '(?:\d{1,3}(?:,\d{3})+|\d+(?:\.\d+)?)';

        if (preg_match('/(' . $symbolOrCode . ')\s*(' . $number . ')/iu', $text, $m)) {
            [$marker, $amountRaw] = [$m[1], $m[2]];
        } elseif (preg_match('/(' . $number . ')\s*(' . $symbolOrCode . ')/iu', $text, $m)) {
            [$amountRaw, $marker] = [$m[1], $m[2]];
        } else {
            return null;
        }

        $currencyName = self::NON_PESO_CURRENCY_NAMES[strtolower($marker)] ?? null;
        $rate         = $currencyName !== null ? (self::NON_PESO_CURRENCY_RATES[$currencyName] ?? null) : null;
        if ($currencyName === null || $rate === null) return null;

        $foreignAmount = (float) str_replace(',', '', $amountRaw);
        if ($foreignAmount <= 0) return null;

        $symbol = self::CURRENCY_DISPLAY_SYMBOLS[$currencyName] ?? '';

        return [
            'currencyName' => $currencyName,
            'pesoAmount'   => (int) round($foreignAmount * $rate),
            'displayLabel' => $symbol . number_format($foreignAmount),
        ];
    }

    // Picks one item from a Serper results list (already sorted by the
    // caller), honoring the same "gen=0 best, gen>0 slides into a
    // different pool position" contract SerpApiService's own summary
    // methods already use — so Regenerate still produces a different pick
    // regardless of which service actually answered.
    private function pickFromPool(array $pool, int $gen, int $poolSize = 3): array
    {
        $offset = $gen === 0 ? 0 : min($gen, max(0, count($pool) - $poolSize));
        $slice  = array_slice($pool, $offset, $poolSize) ?: $pool;
        return $slice[array_rand($slice)];
    }

    public function regeneratePackage(): void
    {
        $this->aiGenCount++;

        // Same fallback order processAiTrip() already uses for the initial
        // generation (Layer 2 live/Serper data, then Layer 3 static table)
        // — minus Layer 1's AI re-ask, since regenerating is meant to slide
        // to a different/cheaper pick within the same already-established
        // route/budget/dates, not re-interpret the traveler's message
        // again. Previously this jumped straight to the static table,
        // which never varies with $aiGenCount at all — every click after
        // the first landed on the exact same result.
        $package = $this->buildSerpApiPackage();
        if ($package) {
            $this->aiPackage = $package;
            return;
        }

        $this->generateAiPackage();
    }

    // Moves from the package-review results screen straight into
    // TripPlannerWizard's own Step 6 (Emergency Fund) → Step 7 (Generate
    // Itinerary) → save flow, instead of maintaining a second, parallel
    // implementation of those same screens here — same idea as
    // editWithWizard() below, just landing further in since the AI
    // package already stands in for everything steps 2-5 would otherwise
    // have collected manually. The wizard reads these back out of the
    // session in its own mount() (one-time, via session()->pull()) and
    // synthesizes selectedFlight/selectedHotel/selectedVenue/
    // selectedAttraction from them before jumping straight to step 6.
    public function proceedToWizardItinerary(): mixed
    {
        if (empty($this->aiPackage)) return null;
        $pkg = $this->aiPackage;

        $year = date('Y');
        if ($this->aiDateTo && preg_match('/(\d{4})$/', $this->aiDateTo, $ym)) {
            $year = $ym[1];
        }
        // strtotime() returns false (not an exception) for anything it can't
        // parse — date('Y-m-d', false) then silently casts to 0 and produces
        // "1970-01-01" instead of erroring, so every result is checked
        // before use rather than trusted blindly.
        $startTs = $this->aiDateFrom ? strtotime($this->aiDateFrom . ', ' . $year) : false;
        $endTs   = $this->aiDateTo   ? strtotime($this->aiDateTo) : false;
        $start   = $startTs !== false ? date('Y-m-d', $startTs) : now()->toDateString();
        $end     = $endTs   !== false ? date('Y-m-d', $endTs)   : now()->addDays(max(1, $this->aiDays))->toDateString();
        $nights  = max(1, (int) \Carbon\Carbon::parse($start)->diffInDays(\Carbon\Carbon::parse($end)));

        $transport      = $pkg['transport'] ?? [];
        $transportDetail = $transport['detail'] ?? '';
        $selectedFlight = [
            'airline' => $transportDetail !== '' ? $transportDetail : 'Flight',
            'number'  => null,
            'price'   => (int) ($transport['cost'] ?? 0),
            'dep_id'  => $transport['from_code'] ?? $this->resolveCode($this->aiFrom),
            'arr_id'  => $transport['to_code']   ?? $this->resolveCode($this->aiTo),
            'type'    => stripos($transportDetail, 'one way') !== false ? 'One Way' : 'Round Trip',
            'depart'  => $start,
        ];

        $accommodation = $pkg['accommodation'] ?? [];
        $selectedHotel = !empty($accommodation['name']) ? [
            'name'   => $accommodation['name'],
            'total'  => (int) ($accommodation['cost'] ?? 0),
            'nights' => $nights,
            'image'  => null,
        ] : null;

        $food = $pkg['food'] ?? [];
        $selectedVenue = !empty($food['name']) ? [
            'name'     => $food['name'],
            'priceMax' => (int) ($food['cost'] ?? 0),
            'priceMin' => (int) ($food['cost'] ?? 0),
            'cuisine'  => null,
        ] : null;

        // The wizard only carries a single selected attraction while the AI
        // package can suggest several — combined into one entry (names
        // joined, cost summed) so every pick still counts toward the total
        // instead of silently dropping all but the first.
        $attrItems = $pkg['attractions']['items'] ?? [];
        $attrCost  = (int) ($pkg['attractions']['cost'] ?? 0);
        $selectedAttraction = !empty($attrItems) ? [
            'name'   => implode(' & ', array_column($attrItems, 0)),
            'price'  => (string) $attrCost,
            'isFree' => $attrCost === 0,
            'image'  => null,
        ] : null;

        session(['wizard_ai_handoff' => [
            'from'       => $this->aiFrom,
            'to'         => $this->aiTo,
            'budget_min' => $this->aiBudgetMin,
            'budget_max' => $this->aiBudgetMax,
            'start'      => $start,
            'end'        => $end,
            'flight'     => $selectedFlight,
            'hotel'      => $selectedHotel,
            'venue'      => $selectedVenue,
            'attraction' => $selectedAttraction,
        ]]);

        // Snapshot the finished conversation into history before it's
        // cleared below — this is the only point a conversation ever
        // actually ends (there's no "start a new chat" reset elsewhere), so
        // it's the one reliable place to archive it.
        AiConversationHistory::create([
            'user_id'       => auth()->id(),
            'messages'      => $this->messages,
            'ai_from'       => $this->aiFrom,
            'ai_to'         => $this->aiTo,
            'ai_budget_min' => $this->aiBudgetMin,
            'ai_budget_max' => $this->aiBudgetMax,
            'ai_date_from'  => $this->aiDateFrom,
            'ai_date_to'    => $this->aiDateTo,
            'ai_days'       => $this->aiDays,
            'ai_package'    => $this->aiPackage,
        ]);

        // Same reasoning as saveAiTrip() previously had: the conversation's
        // being handed off to become a real trip via the wizard now, so
        // clear the draft instead of leaving a stale one to restore later.
        AiConversationDraft::where('user_id', auth()->id())->delete();
        $this->messages = [];

        return $this->redirect(route('trips.plan'), navigate: true);
    }

    // Returns to the chat from the results screen without losing anything —
    // the conversation transcript and the generated aiPackage are both left
    // untouched, only the visible step changes. Lets the traveler type a
    // follow-up ("actually make it Cebu instead") and pick up right where
    // they left off, rather than the only other ways off this screen
    // (Edit → hands off to the manual wizard, Regenerate → discards this
    // package for a new one, Next → saves and moves on).
    public function backToConversation(): void
    {
        $this->aiStep = '';
    }

    // Lets the traveler swap the AI's auto-picked flight/hotel/food/
    // attraction for a specific one of their own choosing, instead of
    // being stuck with whatever the package generated. Hands off to the
    // manual TripPlannerWizard (the existing Select Flight → Accommodation
    // → Food → Attractions flow) with this trip's route/budget/dates
    // pre-filled via query params, landing straight on flight selection —
    // the wizard then proceeds through its own normal steps from there.
    // Only ever engaged from this explicit action, so every other way of
    // reaching the wizard (bare /trips, /trips/plan) is unaffected.
    public function editWithWizard(): mixed
    {
        // Same "Aug 3" / "Aug 10, 2026" → Y-m-d conversion buildSerpApiPackage()
        // already does above, reused here for the same reason: the wizard's
        // date fields expect Y-m-d, not this display format.
        $year = date('Y');
        if ($this->aiDateTo && preg_match('/(\d{4})$/', $this->aiDateTo, $ym)) {
            $year = $ym[1];
        }
        $start = $this->aiDateFrom ? date('Y-m-d', strtotime($this->aiDateFrom . ', ' . $year)) : '';
        $end   = $this->aiDateTo   ? date('Y-m-d', strtotime($this->aiDateTo)) : '';

        return $this->redirect(route('trips.plan', array_filter([
            'from'       => $this->aiFrom,
            'to'         => $this->aiTo,
            'budget_min' => $this->aiBudgetMin ?: null,
            'budget_max' => $this->aiBudgetMax ?: null,
            'start'      => $start ?: null,
            'end'        => $end ?: null,
        ])), navigate: true);
    }

    // Converts a matched money token ("20,000", "30000", "20k") to a plain
    // integer peso amount — one place for the "k means thousand" rule so
    // every budget-parsing branch (single value, range, direct-answer
    // fallback) agrees on how to read it instead of each reimplementing it.
    private function parseMoneyToken(string $token): int
    {
        $token = trim($token);
        if (preg_match('/^(\d+(?:,\d{3})*)\s*[kK]$/', $token, $m)) {
            return (int) str_replace(',', '', $m[1]) * 1000;
        }
        return (int) str_replace(',', '', $token);
    }

    private function cleanCityName(string $name): string
    {
        $name = trim($name);
        // Strip leading travel verbs
        $name = preg_replace('/^\s*(go(?:ing)?|travel(?:ling)?|fly(?:ing)?|visit(?:ing)?|head(?:ing)?|trip)\s+(?:to\s+)?/i', '', $name);
        // Strip trailing month names that bled in (e.g. "Cebu City August 3")
        $name = preg_replace('/\s+(january|february|march|april|may|june|july|august|september|october|november|december|jan|feb|mar|apr|jun|jul|aug|sep|oct|nov|dec)\b.*/i', '', $name);
        // Strip trailing bare numbers
        $name = preg_replace('/\s+\d+.*$/', '', $name);
        return ucwords(strtolower(trim($name)));
    }

    private function parseAiPrompt(): void
    {
        $raw = $this->aiPrompt;

        $monthMap = [
            'january'=>1,'february'=>2,'march'=>3,'april'=>4,'may'=>5,'june'=>6,
            'july'=>7,'august'=>8,'september'=>9,'october'=>10,'november'=>11,'december'=>12,
            'jan'=>1,'feb'=>2,'mar'=>3,'apr'=>4,'jun'=>6,
            'jul'=>7,'aug'=>8,'sep'=>9,'oct'=>10,'nov'=>11,'dec'=>12,
        ];
        $mp = implode('|', array_keys($monthMap));

        // ── Step 1: extract & erase the date span from a working copy ─────
        // This prevents day-numbers (3, 10) from being grabbed as budget amounts.
        $withoutDate = $raw;

        // Cross-month: "August 3 to September 10, 2026"
        if (preg_match('/\b(' . $mp . ')\.?\s+(\d{1,2})\s*(?:[-–]|to)\s+(' . $mp . ')\.?\s+(\d{1,2})(?:,?\s*(\d{4}))?\b/i', $raw, $m)) {
            $mon1  = $monthMap[strtolower($m[1])];
            $mon2  = $monthMap[strtolower($m[3])];
            $year  = !empty($m[5]) ? (int)$m[5] : (int)date('Y');
            $d1    = (int)$m[2]; $d2 = (int)$m[4];
            // checkdate() rejects anything mktime() would otherwise silently
            // roll over into a different day/month/year (e.g. a typo day of
            // 32) — treat a match that fails this as "no date found" rather
            // than accepting a wrong calendar date.
            if (checkdate($mon1, $d1, $year) && checkdate($mon2, $d2, $year)) {
                $ts1   = mktime(0,0,0,$mon1,$d1,$year);
                $ts2   = mktime(0,0,0,$mon2,$d2,$year);
                $this->aiDateFrom = date('M j', $ts1);
                $this->aiDateTo   = date('M j, Y', $ts2);
                $this->aiDays     = (int)ceil(abs($ts2-$ts1)/86400)+1;
                $withoutDate      = str_replace($m[0], '', $raw);
            }

        // Same-month: "August 3 to 10, 2026" | "Aug 3-10" | "Aug 3 - 10, 2026"
        } elseif (preg_match('/\b(' . $mp . ')\.?\s+(\d{1,2})\s*(?:[-–]|to)\s*(\d{1,2})(?:,?\s*(\d{4}))?\b/i', $raw, $m)) {
            $mon  = $monthMap[strtolower($m[1])];
            $year = !empty($m[4]) ? (int)$m[4] : (int)date('Y');
            $d1   = (int)$m[2]; $d2 = (int)$m[3];
            if (checkdate($mon, $d1, $year) && checkdate($mon, $d2, $year)) {
                $this->aiDateFrom = date('M j', mktime(0,0,0,$mon,$d1,$year));
                $this->aiDateTo   = date('M j, Y', mktime(0,0,0,$mon,$d2,$year));
                $this->aiDays     = abs($d2-$d1)+1;
                $withoutDate      = str_replace($m[0], '', $raw);
            }

        // Single date: "August 3, 2026" — treat as start, add aiDays
        } elseif (preg_match('/\b(' . $mp . ')\.?\s+(\d{1,2})(?:,?\s*(\d{4}))?\b/i', $raw, $m)) {
            $mon  = $monthMap[strtolower($m[1])];
            $year = !empty($m[3]) ? (int)$m[3] : (int)date('Y');
            $day  = (int)$m[2];
            if (checkdate($mon, $day, $year)) {
                $ts1  = mktime(0,0,0,$mon,$day,$year);
                $this->aiDateFrom = date('M j', $ts1);
                $this->aiDateTo   = date('M j, Y', $ts1 + 5*86400);
                $this->aiDays     = 6;
                $withoutDate      = str_replace($m[0], '', $raw);
            }

        // Numeric range: "7/28/2026 to 7/30/2026" | "07/28/2026-07/30/2026"
        } elseif (preg_match('/\b(\d{1,2})\/(\d{1,2})\/(\d{2,4})\s*(?:to|[-–])\s*(\d{1,2})\/(\d{1,2})\/(\d{2,4})\b/', $raw, $m)) {
            $y1  = strlen($m[3]) === 2 ? (int)('20'.$m[3]) : (int)$m[3];
            $y2  = strlen($m[6]) === 2 ? (int)('20'.$m[6]) : (int)$m[6];
            $mo1 = (int)$m[1]; $da1 = (int)$m[2];
            $mo2 = (int)$m[4]; $da2 = (int)$m[5];
            if (checkdate($mo1, $da1, $y1) && checkdate($mo2, $da2, $y2)) {
                $ts1 = mktime(0,0,0,$mo1,$da1,$y1);
                $ts2 = mktime(0,0,0,$mo2,$da2,$y2);
                $this->aiDateFrom = date('M j', $ts1);
                $this->aiDateTo   = date('M j, Y', $ts2);
                $this->aiDays     = (int)ceil(abs($ts2-$ts1)/86400)+1;
                $withoutDate      = str_replace($m[0], '', $raw);
            }

        // Numeric single: "7/28/2026"
        } elseif (preg_match('/\b(\d{1,2})\/(\d{1,2})\/(\d{2,4})\b/', $raw, $m)) {
            $y   = strlen($m[3]) === 2 ? (int)('20'.$m[3]) : (int)$m[3];
            $mo  = (int)$m[1]; $da = (int)$m[2];
            if (checkdate($mo, $da, $y)) {
                $ts1 = mktime(0,0,0,$mo,$da,$y);
                $this->aiDateFrom = date('M j', $ts1);
                $this->aiDateTo   = date('M j, Y', $ts1 + 5*86400);
                $this->aiDays     = 6;
                $withoutDate      = str_replace($m[0], '', $raw);
            }

        }
        // else: no date found in this message (or what looked like a date
        // failed calendar validation) — leave whatever was already known
        // from an earlier turn untouched, so we can ask for it instead of
        // silently guessing or accepting an impossible date.

        // ── Step 2: budget — work on date-free copy ───────────────────────
        // "large number" = 4+ digits, comma-grouped thousands (e.g. 30,000),
        // or a "k"-shorthand amount (e.g. 20k, 20,000k though the latter's
        // unrealistic — the comma-group form already covers it).
        $big = '(?:\d{1,3}(?:,\d{3})+|\d{4,}|\d+\s*[kK]\b)';

        // A dollar/euro/etc. amount must never be silently read as pesos —
        // every branch below only ever looks at bare digits, with no
        // concept of currency at all, so "$1,500" and "₱1,500" would
        // otherwise both just become 1500. Converts and fills the budget
        // in directly (using an approximate rate — good enough for a rough
        // trip estimate, not a financial-grade or live-updated rate)
        // rather than asking the traveler to redo the math themselves, who
        // may not even know the peso conversion in the first place. Checked
        // first and, if it fires, none of the branches below run at all
        // this turn — only while budget is still unresolved, so a "$"
        // mentioned in passing after budget was already correctly answered
        // doesn't re-trigger this.
        if ($this->aiBudgetMin === 0 && $this->aiBudgetMax === 0
            && ($conversion = $this->detectAndConvertCurrency($withoutDate)) !== null) {
            $this->aiBudgetMin = $this->aiBudgetMax = $conversion['pesoAmount'];
            $this->currencyNotice = "Got it — {$conversion['displayLabel']} is about ₱" . number_format($conversion['pesoAmount']) . ", I'll plan around that.";

        // Range: "30,000 to 35,000" | "₱30,000-₱35,000" | "30000-35000" | "20k to 30k"
        } elseif (preg_match('/[₱P]?\s*(' . $big . ')\s*(?:[-–]|to)\s*[₱P]?\s*(' . $big . ')/ui', $withoutDate, $m)) {
            $a = $this->parseMoneyToken($m[1]);
            $b = $this->parseMoneyToken($m[2]);
            $this->aiBudgetMin = min($a,$b);
            $this->aiBudgetMax = max($a,$b);

        // Keyword: "budget is/of 30,000" | "budget: 30000" | "budget is 20k"
        } elseif (preg_match('/budget\s*(?:is|of|:)?\s*[₱P]?\s*(' . $big . ')/ui', $withoutDate, $m)) {
            $this->aiBudgetMin = $this->aiBudgetMax = $this->parseMoneyToken($m[1]);

        // Peso sign: ₱30,000
        } elseif (preg_match('/[₱]\s*(' . $big . ')/u', $withoutDate, $m)) {
            $this->aiBudgetMin = $this->aiBudgetMax = $this->parseMoneyToken($m[1]);

        // Trailing keyword: "30,000 pesos" | "30000 php"
        } elseif (preg_match('/(' . $big . ')\s*(?:pesos?|php)\b/ui', $withoutDate, $m)) {
            $this->aiBudgetMin = $this->aiBudgetMax = $this->parseMoneyToken($m[1]);

        // Bare large standalone number
        } elseif (preg_match('/\b(' . $big . ')\b/', $withoutDate, $m)) {
            $this->aiBudgetMin = $this->aiBudgetMax = $this->parseMoneyToken($m[1]);

        }
        // else: no budget found in this message — leave prior turns' value
        // (or 0, meaning still unknown) untouched instead of guessing.

        // ── Step 3: cities — fully order-independent ──────────────────────
        // Use date-free copy so month names (August, July…) can't match as cities.
        $months = 'january|february|march|april|may|june|july|august|september|october|november|december|jan|feb|mar|apr|jun|jul|aug|sep|oct|nov|dec';
        $city   = '(?!(?:' . $months . ')\b)[A-Z][a-z]+(?: [A-Z][a-z]+){0,2}';

        // "...to Cebu or Boracay" / "from Manila or Cebu" — the traveler is
        // visibly still deciding between two options for that slot. Matching
        // just the first one and moving on would silently commit to a place
        // they never actually chose, so each slot's normal pattern below is
        // suppressed when this fires for it, and a clarifying question is
        // asked instead (checked in automateTrip() right after parsing).
        $ambiguousTo = preg_match(
            '/\b(?:travel(?:l?ing)?\s+(?:to|in)|go(?:ing)?\s+(?:to|in)|visit(?:ing)?|fly(?:ing)?\s+to|heading\s+to|stay(?:ing)?\s+(?:in|at)|to|in|at)\s+(' . $city . ')\s+or\s+(' . $city . ')\b/u',
            $withoutDate
        );
        $ambiguousFrom = preg_match('/\bfrom\s+(' . $city . ')\s+or\s+(' . $city . ')\b/u', $withoutDate);

        // No /i flag here — the capitalized-word CITY pattern is the whole
        // heuristic for "this looks like a proper noun, not a regular word",
        // and case-insensitivity would let it match lowercase filler words
        // like "travel" or "from" (e.g. "I want to travel from Manila...").
        $hasFrom = !$ambiguousFrom && preg_match('/\bfrom\s+(' . $city . ')\b/u', $withoutDate, $mf);
        $hasTo   = !$ambiguousTo && preg_match('/\b(?:travel(?:l?ing)?\s+(?:to|in)|go(?:ing)?\s+(?:to|in)|visit(?:ing)?|fly(?:ing)?\s+to|heading\s+to|stay(?:ing)?\s+(?:in|at)|to|in|at)\s+(' . $city . ')\b/u', $withoutDate, $mt);

        // Two cities joined directly by "to" ("in Cebu to Manila", "visit
        // Cebu to Manila") must be checked BEFORE the single-city hasFrom/
        // hasTo branches below, not after. hasTo alone already matches
        // successfully on phrasing like "in Cebu to Manila" (via the bare
        // "in" trigger), capturing only "Cebu" — which used to short-circuit
        // this two-city check entirely (it used to live in the last
        // "elseif" of this chain) and silently drop "Manila" with no trace
        // of it anywhere. Promoting it above the single-city branches means
        // a full route always wins over a partial one.
        $hasTwoCities = !$ambiguousFrom && !$ambiguousTo
            && preg_match('/(' . $city . ')\s+to\s+(' . $city . ')/u', $withoutDate, $m2);

        if ($hasFrom && $hasTo) {
            $this->aiFrom = trim($mf[1]);
            $this->aiTo   = trim($mt[1]);
        } elseif ($hasTwoCities) {
            $this->aiFrom = trim($m2[1]);
            $this->aiTo   = trim($m2[2]);
        } elseif ($hasFrom) {
            $this->aiFrom = trim($mf[1]);
            // Destination not mentioned this turn — leave it unset so we ask.
        } elseif ($hasTo) {
            // Origin not mentioned this turn — leave it unset so we ask,
            // instead of silently assuming Manila.
            $this->aiTo = trim($mt[1]);
        }
        // else: no city mentioned this turn — leave whatever was already
        // known untouched instead of guessing Manila/Cebu.

        if ($ambiguousTo || $ambiguousFrom) {
            $this->ambiguityNotice = "Looks like you named more than one option there — could you tell me just the one you'd like to go with?";
        }

        $this->aiFrom = $this->cleanCityName($this->aiFrom);
        $this->aiTo   = $this->cleanCityName($this->aiTo);

        // ── Step 3b: case-insensitive fallback for KNOWN places only ──────
        // The patterns above require a capitalized word to tell "this looks
        // like a proper noun" from an ordinary word — casual typing often
        // skips capitalization entirely ("i want to go to tokyo"). Only
        // recover from that when the candidate resolves to an ACTUAL known
        // place (the same plausibility gate applyDirectAnswerFallback()
        // uses), so a lowercase non-place word after a trigger word never
        // gets mistaken for a destination.
        $anyCase = '[A-Za-z]+(?: [A-Za-z]+){0,2}';

        // Same two-city priority fix as Step 3 above, just for lowercase
        // input ("in cebu to manila"). Only engages when NEITHER slot is
        // resolved yet, so it can never override a value the capitalized
        // pass above already got right. Each side is resolved independently
        // through knownPlaceName() rather than trusted as typed — the raw
        // capture can grab extra surrounding words on either side ("travel
        // in cebu", "manila please"), and knownPlaceName()'s own known-place
        // window search already tolerates that, same as the single-city
        // fallbacks below rely on it. Guards against both sides resolving
        // to the same place (e.g. a false match spanning unrelated text)
        // by requiring them to differ before accepting either.
        if ($this->aiFrom === '' && $this->aiTo === '' && !$ambiguousFrom && !$ambiguousTo
            && preg_match('/\b(' . $anyCase . ')\s+to\s+(' . $anyCase . ')\b/iu', $withoutDate, $m2l)) {
            $fromCandidate = $this->knownPlaceName($this->cleanCityName($m2l[1]));
            $toCandidate   = $this->knownPlaceName($this->cleanCityName($m2l[2]));
            if ($fromCandidate !== '' && $toCandidate !== '' && strtolower($fromCandidate) !== strtolower($toCandidate)) {
                $this->aiFrom = $fromCandidate;
                $this->aiTo   = $toCandidate;
            }
        }

        if ($this->aiTo === '' && !$ambiguousTo
            && preg_match('/\b(?:travel(?:l?ing)?\s+(?:to|in)|go(?:ing)?\s+(?:to|in)|visit(?:ing)?|fly(?:ing)?\s+to|heading\s+to|stay(?:ing)?\s+(?:in|at)|to|in|at)\s+(' . $anyCase . ')\b/iu', $withoutDate, $mtl)) {
            // knownPlaceName(), not a raw assignment — the capture can grab
            // an extra trailing word along with the real place ("tokyo now"),
            // so resolve to just the matched place, not the whole capture.
            $resolved = $this->knownPlaceName($this->cleanCityName($mtl[1]));
            if ($resolved !== '') {
                $this->aiTo = $resolved;
            }
        }
        if ($this->aiFrom === '' && !$ambiguousFrom
            && preg_match('/\bfrom\s+(' . $anyCase . ')\b/iu', $withoutDate, $mfl)) {
            $resolved = $this->knownPlaceName($this->cleanCityName($mfl[1]));
            if ($resolved !== '') {
                $this->aiFrom = $resolved;
            }
        }
    }

    private function generateAiPackage(): void
    {
        $dest    = strtolower($this->aiTo);
        $days    = max(1, $this->aiDays);
        $budget  = $this->aiBudgetMax ?: $this->aiBudgetMin ?: 30000;

        // Destination lookup table — PH local + international
        $lookup = [
            // ── Philippine Destinations ──────────────────────────────────────
            'manila'          => ['code'=>'MNL','airline'=>'N/A – Origin City','hotel'=>'New World Manila Bay Hotel','hotel_stars'=>5,'hotel_type'=>'Deluxe Room','hotel_city'=>'Manila','restaurant'=>'Ilustrado Restaurant (₱1,200)','meal_plan'=>'Breakfast, Lunch, & Dinner','meal_city'=>'Intramuros, Manila','meal_day'=>1200,'attractions'=>[['Intramuros Walls','₱75'],['Rizal Park','Free'],['National Museum of Fine Arts','Free']]],
            'cebu'            => ['code'=>'CEB','airline'=>'Cebu Pacific 5J 567','hotel'=>'Crown Regency Hotel and Towers','hotel_stars'=>4,'hotel_type'=>'Deluxe Room','hotel_city'=>'Cebu City','restaurant'=>'Scape Skydeck Lapu-Lapu (₱1,200)','meal_plan'=>'Breakfast, Lunch, & Dinner','meal_city'=>'Lapu-Lapu City','meal_day'=>1200,'attractions'=>[["Magellan's Cross",'Free'],['Fort San Pedro','₱30'],['Temple of Leah','₱150']]],
            'boracay'         => ['code'=>'KLO','airline'=>'Philippine Airlines PR 201','hotel'=>'Discovery Shores Boracay','hotel_stars'=>5,'hotel_type'=>'Garden View Room','hotel_city'=>'Boracay Island','restaurant'=>'Aria at Discovery Shores (₱1,500)','meal_plan'=>'Breakfast, Lunch, & Dinner','meal_city'=>'Station 1, Boracay','meal_day'=>1500,'attractions'=>[['White Beach Walk','Free'],['Paraw Sailing','₱800'],["Willy's Rock",'Free']]],
            'bohol'           => ['code'=>'TAG','airline'=>'Cebu Pacific 5J 311','hotel'=>'Bohol Beach Club','hotel_stars'=>4,'hotel_type'=>'Standard Room','hotel_city'=>'Panglao, Bohol','restaurant'=>'Bohol Bee Farm Restaurant (₱900)','meal_plan'=>'Breakfast, Lunch, & Dinner','meal_city'=>'Panglao, Bohol','meal_day'=>900,'attractions'=>[['Chocolate Hills','₱50'],['Tarsier Sanctuary','₱100'],['Loboc River Cruise','₱500']]],
            'palawan'         => ['code'=>'PPS','airline'=>'Philippine Airlines PR 2673','hotel'=>'Sheridan Beach Resort','hotel_stars'=>4,'hotel_type'=>'Deluxe Sea View','hotel_city'=>'Puerto Princesa','restaurant'=>'Halong Restaurant (₱1,100)','meal_plan'=>'Breakfast, Lunch, & Dinner','meal_city'=>'Puerto Princesa','meal_day'=>1100,'attractions'=>[['Underground River','₱150'],['Honda Bay Tour','₱500'],['Iwahig Firefly Watching','₱300']]],
            'el nido'         => ['code'=>'ENI','airline'=>'AirSWIFT T6 461','hotel'=>'El Nido Resorts Miniloc Island','hotel_stars'=>5,'hotel_type'=>'Water Cottage','hotel_city'=>'El Nido, Palawan','restaurant'=>'Trattoria Altrove (₱1,300)','meal_plan'=>'Breakfast, Lunch, & Dinner','meal_city'=>'El Nido','meal_day'=>1300,'attractions'=>[['Big Lagoon Tour A','₱1,200'],['Small Lagoon Kayaking','₱200'],['Nacpan Beach','₱100']]],
            'coron'           => ['code'=>'USU','airline'=>'Cebu Pacific 5J 819','hotel'=>'Two Seasons Coron Island Resort','hotel_stars'=>4,'hotel_type'=>'Deluxe Room','hotel_city'=>'Coron, Palawan','restaurant'=>'Sea Horse Restaurant (₱900)','meal_plan'=>'Breakfast, Lunch, & Dinner','meal_city'=>'Coron Town','meal_day'=>900,'attractions'=>[['Kayangan Lake','₱200'],['Twin Lagoon','₱100'],['Maquinit Hot Spring','₱200']]],
            'davao'           => ['code'=>'DVO','airline'=>'Cebu Pacific 5J 481','hotel'=>'Marco Polo Davao','hotel_stars'=>5,'hotel_type'=>'Superior Room','hotel_city'=>'Davao City','restaurant'=>"Claude's Le Coq d'Or (₱1,000)",'meal_plan'=>'Breakfast, Lunch, & Dinner','meal_city'=>'Davao City','meal_day'=>1000,'attractions'=>[['Philippine Eagle Center','₱200'],['Eden Nature Park','₱150'],['Crocodile Park','₱250']]],
            'siargao'         => ['code'=>'IAO','airline'=>'Cebu Pacific 5J 711','hotel'=>'Siargao Bleu Resort','hotel_stars'=>3,'hotel_type'=>'Deluxe Room','hotel_city'=>'General Luna, Siargao','restaurant'=>'Bravo Beach Resort Restaurant (₱850)','meal_plan'=>'Breakfast, Lunch, & Dinner','meal_city'=>'General Luna','meal_day'=>850,'attractions'=>[['Cloud 9 Surfing','₱500'],['Sugba Lagoon','₱150'],['Magpupungko Tidal Pools','₱50']]],
            'bacolod'         => ['code'=>'BCD','airline'=>'Cebu Pacific 5J 461','hotel'=>"L'Fisher Hotel Bacolod",'hotel_stars'=>4,'hotel_type'=>'Superior Room','hotel_city'=>'Bacolod City','restaurant'=>'Calea Pastries & Coffee (₱600)','meal_plan'=>'Breakfast, Lunch, & Dinner','meal_city'=>'Bacolod City','meal_day'=>800,'attractions'=>[['The Ruins','₱100'],['Panaad Park','Free'],['Masskara Festival Site','Free']]],
            'iloilo'          => ['code'=>'ILO','airline'=>'Philippine Airlines PR 2031','hotel'=>'Richmonde Hotel Iloilo','hotel_stars'=>4,'hotel_type'=>'Deluxe Room','hotel_city'=>'Iloilo City','restaurant'=>"Tatoy's Manokan & Seafoods (₱800)",'meal_plan'=>'Breakfast, Lunch, & Dinner','meal_city'=>'Iloilo City','meal_day'=>800,'attractions'=>[['Miagao Church','Free'],['Garin Farm','₱200'],['Islas de Gigantes','₱500']]],
            'zamboanga'       => ['code'=>'ZAM','airline'=>'Cebu Pacific 5J 921','hotel'=>'Grand Astoria Hotel','hotel_stars'=>3,'hotel_type'=>'Standard Room','hotel_city'=>'Zamboanga City','restaurant'=>'Alavar Seafood Restaurant (₱900)','meal_plan'=>'Breakfast, Lunch, & Dinner','meal_city'=>'Zamboanga City','meal_day'=>900,'attractions'=>[['Santa Cruz Island','₱200'],['Fort Pilar','Free'],['Yakan Weaving Village','Free']]],
            'cagayan de oro'  => ['code'=>'CGY','airline'=>'Cebu Pacific 5J 831','hotel'=>'Seda Centrio Cagayan de Oro','hotel_stars'=>4,'hotel_type'=>'Deluxe Room','hotel_city'=>'Cagayan de Oro','restaurant'=>'Kagay-anon Restaurant (₱700)','meal_plan'=>'Breakfast, Lunch, & Dinner','meal_city'=>'Cagayan de Oro','meal_day'=>700,'attractions'=>[['Mapawa Nature Park','₱200'],['Macahambus Cave','₱50'],['7107 Beach Resort','₱150']]],
            'cagayan'         => ['code'=>'CGY','airline'=>'Cebu Pacific 5J 831','hotel'=>'Seda Centrio Cagayan de Oro','hotel_stars'=>4,'hotel_type'=>'Deluxe Room','hotel_city'=>'Cagayan de Oro','restaurant'=>'Kagay-anon Restaurant (₱700)','meal_plan'=>'Breakfast, Lunch, & Dinner','meal_city'=>'Cagayan de Oro','meal_day'=>700,'attractions'=>[['Mapawa Nature Park','₱200'],['Macahambus Cave','₱50'],['7107 Beach Resort','₱150']]],
            'general santos'  => ['code'=>'GES','airline'=>'Cebu Pacific 5J 951','hotel'=>'Phela Grande Hotel','hotel_stars'=>3,'hotel_type'=>'Standard Room','hotel_city'=>'General Santos City','restaurant'=>'Greenfield Restaurant (₱700)','meal_plan'=>'Breakfast, Lunch, & Dinner','meal_city'=>'General Santos','meal_day'=>700,'attractions'=>[['SOCSKSARGEN Museum','Free'],['Sarangani Bay','Free'],['Libi Lake','₱100']]],
            'tagaytay'        => ['code'=>'MNL','airline'=>'Bus from Cubao (₱180)','hotel'=>'Taal Vista Hotel','hotel_stars'=>4,'hotel_type'=>'Deluxe Room','hotel_city'=>'Tagaytay City','restaurant'=>'Café Voila (₱900)','meal_plan'=>'Breakfast, Lunch, & Dinner','meal_city'=>'Tagaytay City','meal_day'=>900,'attractions'=>[['Taal Volcano Island','₱1,000'],['Sky Ranch Tagaytay','₱200'],['Picnic Grove','₱50']]],
            'baguio'          => ['code'=>'MNL','airline'=>'Bus from Pasay (₱500)','hotel'=>'Manor at Camp John Hay','hotel_stars'=>4,'hotel_type'=>'Standard Room','hotel_city'=>'Baguio City','restaurant'=>'Forest House (₱700)','meal_plan'=>'Breakfast, Lunch, & Dinner','meal_city'=>'Baguio City','meal_day'=>700,'attractions'=>[['Burnham Park','Free'],['Mines View Park','Free'],['Strawberry Farm La Trinidad','₱50']]],
            'vigan'           => ['code'=>'VIG','airline'=>'Bus from Pasay (₱600)','hotel'=>'Villa Angela Heritage House','hotel_stars'=>3,'hotel_type'=>'Heritage Room','hotel_city'=>'Vigan City','restaurant'=>'Cafe Leona (₱600)','meal_plan'=>'Breakfast, Lunch, & Dinner','meal_city'=>'Vigan City','meal_day'=>600,'attractions'=>[['Calle Crisologo','Free'],['Bantay Bell Tower','Free'],['Pagburnayan Jar Factory','Free']]],
            'batangas'        => ['code'=>'MNL','airline'=>'Bus from Cubao (₱300)','hotel'=>'Maya Maya Reef Club','hotel_stars'=>3,'hotel_type'=>'Standard Room','hotel_city'=>'Batangas','restaurant'=>"D'Talipapa (₱800)",'meal_plan'=>'Breakfast, Lunch, & Dinner','meal_city'=>'Batangas City','meal_day'=>800,'attractions'=>[['Anilao Dive Sites','₱500'],['Matabungkay Beach','₱100'],['Fortune Island','₱300']]],
            'leyte'           => ['code'=>'TAC','airline'=>'Cebu Pacific 5J 141','hotel'=>'Leyte Park Resort Hotel','hotel_stars'=>3,'hotel_type'=>'Deluxe Room','hotel_city'=>'Tacloban City','restaurant'=>'Kusina Leyte (₱700)','meal_plan'=>'Breakfast, Lunch, & Dinner','meal_city'=>'Tacloban City','meal_day'=>700,'attractions'=>[['MacArthur Landing Memorial','Free'],['San Juanico Bridge','Free'],['Kalanggaman Island','₱300']]],
            'tacloban'        => ['code'=>'TAC','airline'=>'Cebu Pacific 5J 141','hotel'=>'Leyte Park Resort Hotel','hotel_stars'=>3,'hotel_type'=>'Deluxe Room','hotel_city'=>'Tacloban City','restaurant'=>'Kusina Leyte (₱700)','meal_plan'=>'Breakfast, Lunch, & Dinner','meal_city'=>'Tacloban City','meal_day'=>700,'attractions'=>[['MacArthur Landing Memorial','Free'],['San Juanico Bridge','Free'],['Kalanggaman Island','₱300']]],
            'dumaguete'       => ['code'=>'DGT','airline'=>'Cebu Pacific 5J 241','hotel'=>'The Florentina Homes','hotel_stars'=>3,'hotel_type'=>'Standard Room','hotel_city'=>'Dumaguete City','restaurant'=>'Lab-as Seafood Restaurant (₱700)','meal_plan'=>'Breakfast, Lunch, & Dinner','meal_city'=>'Dumaguete City','meal_day'=>700,'attractions'=>[['Apo Island','₱500'],['Twin Lakes Balinsasayao','₱50'],['Casaroro Falls','₱100']]],
            'surigao'         => ['code'=>'SUG','airline'=>'Cebu Pacific 5J 121','hotel'=>'Tavern Hotel Surigao','hotel_stars'=>3,'hotel_type'=>'Standard Room','hotel_city'=>'Surigao City','restaurant'=>'Bay View Restaurant (₱600)','meal_plan'=>'Breakfast, Lunch, & Dinner','meal_city'=>'Surigao City','meal_day'=>600,'attractions'=>[['Sohoton Cave','₱200'],['Bucas Grande Island','₱300'],['Britania Islands','₱400']]],
            'cotabato'        => ['code'=>'CBO','airline'=>'Cebu Pacific 5J 981','hotel'=>'Estosan Garden Hotel','hotel_stars'=>3,'hotel_type'=>'Standard Room','hotel_city'=>'Cotabato City','restaurant'=>'Hadji Murad Restaurant (₱500)','meal_plan'=>'Breakfast, Lunch, & Dinner','meal_city'=>'Cotabato City','meal_day'=>500,'attractions'=>[['Kutawato Caves','₱50'],['Tamontaka Church','Free'],['Lake Lanao (Day Trip)','₱300']]],
            'puerto galera'   => ['code'=>'MNL','airline'=>'Bus + Ferry from Manila (₱400)','hotel'=>'Coco Beach Island Resort','hotel_stars'=>3,'hotel_type'=>'Beachfront Cottage','hotel_city'=>'Puerto Galera, Mindoro','restaurant'=>'La Laguna Beach Restaurant (₱800)','meal_plan'=>'Breakfast, Lunch, & Dinner','meal_city'=>'Puerto Galera','meal_day'=>800,'attractions'=>[['Sabang Beach','Free'],['White Beach','Free'],['Coral Garden Diving','₱800']]],
            'sagada'          => ['code'=>'MNL','airline'=>'Bus from Pasay (₱700)','hotel'=>'Misty Lodge and Cafe','hotel_stars'=>2,'hotel_type'=>'Standard Room','hotel_city'=>'Sagada, Mountain Province','restaurant'=>'Log Cabin Cafe (₱500)','meal_plan'=>'Breakfast, Lunch, & Dinner','meal_city'=>'Sagada','meal_day'=>500,'attractions'=>[['Sumaguing Cave','₱500'],['Hanging Coffins','₱50'],['Kiltepan Peak Sunrise','₱30']]],
            'batanes'         => ['code'=>'BSO','airline'=>'Philippine Airlines PR 241','hotel'=>'Fundacion Pacita','hotel_stars'=>3,'hotel_type'=>'Deluxe Room','hotel_city'=>'Batan Island, Batanes','restaurant'=>'Pension Ivatan (₱600)','meal_plan'=>'Breakfast, Lunch, & Dinner','meal_city'=>'Basco, Batanes','meal_day'=>600,'attractions'=>[['Vayang Rolling Hills','Free'],['Marlboro Country','Free'],['Valugan Boulder Beach','Free']]],
            'camiguin'        => ['code'=>'CGM','airline'=>'Cebu Pacific 5J 851','hotel'=>'Enigmata Treehouse Eco-Retreat','hotel_stars'=>3,'hotel_type'=>'Treehouse Room','hotel_city'=>'Camiguin Island','restaurant'=>'Volcan Restaurant (₱700)','meal_plan'=>'Breakfast, Lunch, & Dinner','meal_city'=>'Mambajao, Camiguin','meal_day'=>700,'attractions'=>[['White Island Sandbar','₱100'],['Sunken Cemetery','₱50'],['Katibawasan Falls','₱30']]],
            'siquijor'        => ['code'=>'DGT','airline'=>'Ferry from Dumaguete (₱200)','hotel'=>'Coco Grove Beach Resort','hotel_stars'=>3,'hotel_type'=>'Garden Room','hotel_city'=>'Siquijor Island','restaurant'=>'Islander Restaurant (₱600)','meal_plan'=>'Breakfast, Lunch, & Dinner','meal_city'=>'San Juan, Siquijor','meal_day'=>600,'attractions'=>[['Cambugahay Falls','Free'],['Lazi Church','Free'],['Salagdoong Beach','₱50']]],
            'pagudpud'        => ['code'=>'MNL','airline'=>'Bus from Pasay (₱900)','hotel'=>'Kapuluan Vista Resort','hotel_stars'=>3,'hotel_type'=>'Ocean View Room','hotel_city'=>'Pagudpud, Ilocos Norte','restaurant'=>'Kapuluan Beach Bar (₱600)','meal_plan'=>'Breakfast, Lunch, & Dinner','meal_city'=>'Pagudpud','meal_day'=>600,'attractions'=>[['Saud Beach','Free'],['Blue Lagoon Beach','Free'],['Bangui Windmills','Free']]],
            'laoag'           => ['code'=>'LAO','airline'=>'Philippine Airlines PR 223','hotel'=>'Fort Ilocandia Resort Hotel','hotel_stars'=>4,'hotel_type'=>'Deluxe Room','hotel_city'=>'Laoag City, Ilocos Norte','restaurant'=>'Saramsam Ylocano Restaurant (₱600)','meal_plan'=>'Breakfast, Lunch, & Dinner','meal_city'=>'Laoag City','meal_day'=>600,'attractions'=>[['Paoay Church','Free'],['La Paz Sand Dunes','₱300'],["Marcos Museum & Mausoleum",'₱20']]],
            'caramoan'        => ['code'=>'MNL','airline'=>'Bus + Jeep from Naga (₱500)','hotel'=>'Tugawe Cove Resort','hotel_stars'=>3,'hotel_type'=>'Beachfront Room','hotel_city'=>'Caramoan, Camarines Sur','restaurant'=>'Local Eatery (₱400)','meal_plan'=>'Breakfast, Lunch, & Dinner','meal_city'=>'Caramoan','meal_day'=>400,'attractions'=>[['Lahos Island','₱300'],['Matukad Island','₱200'],['Gota Beach','₱100']]],

            // ── International Destinations ────────────────────────────────────
            'singapore'       => ['code'=>'SIN','airline'=>'Singapore Airlines SQ 921','hotel'=>'Marina Bay Sands','hotel_stars'=>5,'hotel_type'=>'Deluxe Room','hotel_city'=>'Marina Bay, Singapore','restaurant'=>'Lau Pa Sat Hawker Centre (SGD 15)','meal_plan'=>'Breakfast, Lunch, & Dinner','meal_city'=>'Singapore CBD','meal_day'=>2500,'attractions'=>[['Gardens by the Bay','₱900'],['Universal Studios Singapore','₱2,500'],['Sentosa Island','₱500']]],
            'bangkok'         => ['code'=>'BKK','airline'=>'Thai Airways TG 621','hotel'=>'Mandarin Oriental Bangkok','hotel_stars'=>5,'hotel_type'=>'Superior Room','hotel_city'=>'Bangkok, Thailand','restaurant'=>'Sirocco Sky Bar (₱1,500)','meal_plan'=>'Breakfast, Lunch, & Dinner','meal_city'=>'Silom, Bangkok','meal_day'=>1500,'attractions'=>[['Grand Palace','₱500'],['Wat Pho','₱200'],['Chatuchak Weekend Market','Free']]],
            'thailand'        => ['code'=>'BKK','airline'=>'Thai Airways TG 621','hotel'=>'Mandarin Oriental Bangkok','hotel_stars'=>5,'hotel_type'=>'Superior Room','hotel_city'=>'Bangkok, Thailand','restaurant'=>'Sirocco Sky Bar (₱1,500)','meal_plan'=>'Breakfast, Lunch, & Dinner','meal_city'=>'Silom, Bangkok','meal_day'=>1500,'attractions'=>[['Grand Palace','₱500'],['Wat Pho','₱200'],['Chatuchak Weekend Market','Free']]],
            'phuket'          => ['code'=>'HKT','airline'=>'AirAsia Z2 791','hotel'=>'Banyan Tree Phuket','hotel_stars'=>5,'hotel_type'=>'Pool Villa','hotel_city'=>'Phuket, Thailand','restaurant'=>'Kan Eang@Pier (THB 400)','meal_plan'=>'Breakfast, Lunch, & Dinner','meal_city'=>'Rawai, Phuket','meal_day'=>1800,'attractions'=>[['Phang Nga Bay Tour','₱2,000'],['Big Buddha Phuket','Free'],['Patong Beach','Free']]],
            'bali'            => ['code'=>'DPS','airline'=>'Garuda Indonesia GA 862','hotel'=>'Four Seasons Resort Bali at Sayan','hotel_stars'=>5,'hotel_type'=>'Suite','hotel_city'=>'Ubud, Bali','restaurant'=>'Locavore Restaurant (₱1,800)','meal_plan'=>'Breakfast, Lunch, & Dinner','meal_city'=>'Ubud, Bali','meal_day'=>1800,'attractions'=>[['Tegallalang Rice Terraces','₱100'],['Tanah Lot Temple','₱300'],['Seminyak Beach','Free']]],
            'indonesia'       => ['code'=>'DPS','airline'=>'Garuda Indonesia GA 862','hotel'=>'Four Seasons Resort Bali at Sayan','hotel_stars'=>5,'hotel_type'=>'Suite','hotel_city'=>'Ubud, Bali','restaurant'=>'Locavore Restaurant (₱1,800)','meal_plan'=>'Breakfast, Lunch, & Dinner','meal_city'=>'Ubud, Bali','meal_day'=>1800,'attractions'=>[['Tegallalang Rice Terraces','₱100'],['Tanah Lot Temple','₱300'],['Seminyak Beach','Free']]],
            'kuala lumpur'    => ['code'=>'KUL','airline'=>'AirAsia Z2 511','hotel'=>'The Ritz-Carlton Kuala Lumpur','hotel_stars'=>5,'hotel_type'=>'Deluxe Room','hotel_city'=>'Kuala Lumpur, Malaysia','restaurant'=>'Jalan Alor Food Street (MYR 30)','meal_plan'=>'Breakfast, Lunch, & Dinner','meal_city'=>'Bukit Bintang, KL','meal_day'=>1200,'attractions'=>[['Petronas Twin Towers','₱300'],['Batu Caves','Free'],['KL Bird Park','₱500']]],
            'malaysia'        => ['code'=>'KUL','airline'=>'AirAsia Z2 511','hotel'=>'The Ritz-Carlton Kuala Lumpur','hotel_stars'=>5,'hotel_type'=>'Deluxe Room','hotel_city'=>'Kuala Lumpur, Malaysia','restaurant'=>'Jalan Alor Food Street (MYR 30)','meal_plan'=>'Breakfast, Lunch, & Dinner','meal_city'=>'Bukit Bintang, KL','meal_day'=>1200,'attractions'=>[['Petronas Twin Towers','₱300'],['Batu Caves','Free'],['KL Bird Park','₱500']]],
            'hong kong'       => ['code'=>'HKG','airline'=>'Cathay Pacific CX 911','hotel'=>'The Peninsula Hong Kong','hotel_stars'=>5,'hotel_type'=>'Deluxe Room','hotel_city'=>'Tsim Sha Tsui, Hong Kong','restaurant'=>'Tim Ho Wan Dim Sum (HKD 100)','meal_plan'=>'Breakfast, Lunch, & Dinner','meal_city'=>'Kowloon, Hong Kong','meal_day'=>2500,'attractions'=>[['Victoria Peak','₱800'],['Disneyland Hong Kong','₱3,500'],['Tian Tan Buddha','₱500']]],
            'tokyo'           => ['code'=>'NRT','airline'=>'Philippine Airlines PR 432','hotel'=>'Park Hyatt Tokyo','hotel_stars'=>5,'hotel_type'=>'Park Room','hotel_city'=>'Shinjuku, Tokyo','restaurant'=>'Ichiran Ramen (JPY 1,000)','meal_plan'=>'Breakfast, Lunch, & Dinner','meal_city'=>'Shinjuku, Tokyo','meal_day'=>3500,'attractions'=>[['Senso-ji Temple','Free'],['teamLab Borderless','₱2,000'],['Mt. Fuji Day Tour','₱3,000']]],
            'japan'           => ['code'=>'NRT','airline'=>'Philippine Airlines PR 432','hotel'=>'Park Hyatt Tokyo','hotel_stars'=>5,'hotel_type'=>'Park Room','hotel_city'=>'Shinjuku, Tokyo','restaurant'=>'Ichiran Ramen (JPY 1,000)','meal_plan'=>'Breakfast, Lunch, & Dinner','meal_city'=>'Shinjuku, Tokyo','meal_day'=>3500,'attractions'=>[['Senso-ji Temple','Free'],['teamLab Borderless','₱2,000'],['Mt. Fuji Day Tour','₱3,000']]],
            'osaka'           => ['code'=>'KIX','airline'=>'Cebu Pacific 5J 117','hotel'=>'The St. Regis Osaka','hotel_stars'=>5,'hotel_type'=>'Superior Room','hotel_city'=>'Chuo-ku, Osaka','restaurant'=>'Dotonbori Street Food (JPY 800)','meal_plan'=>'Breakfast, Lunch, & Dinner','meal_city'=>'Dotonbori, Osaka','meal_day'=>3000,'attractions'=>[['Osaka Castle','₱500'],['Universal Studios Japan','₱4,500'],['Namba Yasaka Shrine','Free']]],
            'seoul'           => ['code'=>'ICN','airline'=>'Korean Air KE 621','hotel'=>'Signiel Seoul','hotel_stars'=>5,'hotel_type'=>'Deluxe Room','hotel_city'=>'Jamsil, Seoul','restaurant'=>'Gwangjang Market Street Food (KRW 10,000)','meal_plan'=>'Breakfast, Lunch, & Dinner','meal_city'=>'Myeongdong, Seoul','meal_day'=>2500,'attractions'=>[['Gyeongbokgung Palace','₱500'],['N Seoul Tower','₱600'],['Myeongdong Shopping Street','Free']]],
            'korea'           => ['code'=>'ICN','airline'=>'Korean Air KE 621','hotel'=>'Signiel Seoul','hotel_stars'=>5,'hotel_type'=>'Deluxe Room','hotel_city'=>'Jamsil, Seoul','restaurant'=>'Gwangjang Market Street Food (KRW 10,000)','meal_plan'=>'Breakfast, Lunch, & Dinner','meal_city'=>'Myeongdong, Seoul','meal_day'=>2500,'attractions'=>[['Gyeongbokgung Palace','₱500'],['N Seoul Tower','₱600'],['Myeongdong Shopping Street','Free']]],
            'taipei'          => ['code'=>'TPE','airline'=>'EVA Air BR 261','hotel'=>'Grand Hyatt Taipei','hotel_stars'=>5,'hotel_type'=>'Deluxe Room','hotel_city'=>'Xinyi District, Taipei','restaurant'=>'Din Tai Fung (TWD 400)','meal_plan'=>'Breakfast, Lunch, & Dinner','meal_city'=>'Da\'an District, Taipei','meal_day'=>2000,'attractions'=>[['Taipei 101 Observatory','₱800'],['Jiufen Old Street','₱200'],['Taroko Gorge Day Tour','₱1,500']]],
            'taiwan'          => ['code'=>'TPE','airline'=>'EVA Air BR 261','hotel'=>'Grand Hyatt Taipei','hotel_stars'=>5,'hotel_type'=>'Deluxe Room','hotel_city'=>'Xinyi District, Taipei','restaurant'=>'Din Tai Fung (TWD 400)','meal_plan'=>'Breakfast, Lunch, & Dinner','meal_city'=>'Da\'an District, Taipei','meal_day'=>2000,'attractions'=>[['Taipei 101 Observatory','₱800'],['Jiufen Old Street','₱200'],['Taroko Gorge Day Tour','₱1,500']]],
            'dubai'           => ['code'=>'DXB','airline'=>'Emirates EK 332','hotel'=>'Burj Al Arab Jumeirah','hotel_stars'=>5,'hotel_type'=>'Deluxe Suite','hotel_city'=>'Jumeirah, Dubai','restaurant'=>'At.mosphere Burj Khalifa (AED 200)','meal_plan'=>'Breakfast, Lunch, & Dinner','meal_city'=>'Downtown Dubai','meal_day'=>5000,'attractions'=>[['Burj Khalifa Top Deck','₱2,500'],['Dubai Mall & Fountain','Free'],['Desert Safari','₱3,000']]],
            'uae'             => ['code'=>'DXB','airline'=>'Emirates EK 332','hotel'=>'Burj Al Arab Jumeirah','hotel_stars'=>5,'hotel_type'=>'Deluxe Suite','hotel_city'=>'Jumeirah, Dubai','restaurant'=>'At.mosphere Burj Khalifa (AED 200)','meal_plan'=>'Breakfast, Lunch, & Dinner','meal_city'=>'Downtown Dubai','meal_day'=>5000,'attractions'=>[['Burj Khalifa Top Deck','₱2,500'],['Dubai Mall & Fountain','Free'],['Desert Safari','₱3,000']]],
            'london'          => ['code'=>'LHR','airline'=>'British Airways BA 11','hotel'=>'The Savoy London','hotel_stars'=>5,'hotel_type'=>'Classic Room','hotel_city'=>'City of Westminster, London','restaurant'=>'The Ivy (GBP 50)','meal_plan'=>'Breakfast, Lunch, & Dinner','meal_city'=>'Covent Garden, London','meal_day'=>8000,'attractions'=>[['British Museum','Free'],['Tower of London','₱2,500'],['Buckingham Palace Gardens','₱600']]],
            'paris'           => ['code'=>'CDG','airline'=>'Air France AF 171','hotel'=>'Hotel Le Meurice','hotel_stars'=>5,'hotel_type'=>'Classic Room','hotel_city'=>'1st Arrondissement, Paris','restaurant'=>'Café de Flore (EUR 30)','meal_plan'=>'Breakfast, Lunch, & Dinner','meal_city'=>'Saint-Germain-des-Prés, Paris','meal_day'=>8000,'attractions'=>[['Eiffel Tower','₱1,500'],['Louvre Museum','₱1,000'],['Versailles Palace','₱2,000']]],
            'new york'        => ['code'=>'JFK','airline'=>'Philippine Airlines PR 126','hotel'=>'The Plaza Hotel New York','hotel_stars'=>5,'hotel_type'=>'Classic Room','hotel_city'=>'Midtown Manhattan, New York','restaurant'=>'Katz\'s Delicatessen (USD 25)','meal_plan'=>'Breakfast, Lunch, & Dinner','meal_city'=>'Lower East Side, NYC','meal_day'=>9000,'attractions'=>[['Statue of Liberty','₱1,500'],['Times Square','Free'],['Central Park','Free']]],
            'new york city'   => ['code'=>'JFK','airline'=>'Philippine Airlines PR 126','hotel'=>'The Plaza Hotel New York','hotel_stars'=>5,'hotel_type'=>'Classic Room','hotel_city'=>'Midtown Manhattan, New York','restaurant'=>'Katz\'s Delicatessen (USD 25)','meal_plan'=>'Breakfast, Lunch, & Dinner','meal_city'=>'Lower East Side, NYC','meal_day'=>9000,'attractions'=>[['Statue of Liberty','₱1,500'],['Times Square','Free'],['Central Park','Free']]],
            'sydney'          => ['code'=>'SYD','airline'=>'Qantas QF 21','hotel'=>'Park Hyatt Sydney','hotel_stars'=>5,'hotel_type'=>'Opera House View Room','hotel_city'=>'The Rocks, Sydney','restaurant'=>'Quay Restaurant (AUD 80)','meal_plan'=>'Breakfast, Lunch, & Dinner','meal_city'=>'Circular Quay, Sydney','meal_day'=>7000,'attractions'=>[['Sydney Opera House','₱1,500'],['Sydney Harbour Bridge Climb','₱6,000'],['Bondi Beach','Free']]],
            'australia'       => ['code'=>'SYD','airline'=>'Qantas QF 21','hotel'=>'Park Hyatt Sydney','hotel_stars'=>5,'hotel_type'=>'Opera House View Room','hotel_city'=>'The Rocks, Sydney','restaurant'=>'Quay Restaurant (AUD 80)','meal_plan'=>'Breakfast, Lunch, & Dinner','meal_city'=>'Circular Quay, Sydney','meal_day'=>7000,'attractions'=>[['Sydney Opera House','₱1,500'],['Sydney Harbour Bridge Climb','₱6,000'],['Bondi Beach','Free']]],
            'rome'            => ['code'=>'FCO','airline'=>'Qatar Airways QR 131','hotel'=>'Hotel Eden Rome','hotel_stars'=>5,'hotel_type'=>'Deluxe Room','hotel_city'=>'Via Veneto, Rome','restaurant'=>'Osteria dell\'Enoteca (EUR 35)','meal_plan'=>'Breakfast, Lunch, & Dinner','meal_city'=>'Trastevere, Rome','meal_day'=>7000,'attractions'=>[['Colosseum','₱1,500'],['Vatican Museums','₱2,000'],['Trevi Fountain','Free']]],
            'barcelona'       => ['code'=>'BCN','airline'=>'Qatar Airways QR 141','hotel'=>'W Barcelona','hotel_stars'=>5,'hotel_type'=>'Wonderful Sea View Room','hotel_city'=>'Barceloneta, Barcelona','restaurant'=>'La Boqueria Market (EUR 20)','meal_plan'=>'Breakfast, Lunch, & Dinner','meal_city'=>'Las Ramblas, Barcelona','meal_day'=>7000,'attractions'=>[['Sagrada Família','₱2,000'],['Park Güell','₱800'],['Camp Nou Tour','₱1,500']]],
            'amsterdam'       => ['code'=>'AMS','airline'=>'KLM KL 808','hotel'=>'Waldorf Astoria Amsterdam','hotel_stars'=>5,'hotel_type'=>'Classic Canal View Room','hotel_city'=>'Herengracht, Amsterdam','restaurant'=>'Restaurant Breitner (EUR 40)','meal_plan'=>'Breakfast, Lunch, & Dinner','meal_city'=>'Amsterdam Centre','meal_day'=>7000,'attractions'=>[['Rijksmuseum','₱1,500'],['Anne Frank House','₱1,000'],['Canal Boat Tour','₱800']]],
            'maldives'        => ['code'=>'MLE','airline'=>'Singapore Airlines SQ 471 + Transfer','hotel'=>'Soneva Jani Maldives','hotel_stars'=>5,'hotel_type'=>'Water Villa','hotel_city'=>'Noonu Atoll, Maldives','restaurant'=>'Fresh in the Garden (USD 50)','meal_plan'=>'Breakfast, Lunch, & Dinner','meal_city'=>'Noonu Atoll','meal_day'=>12000,'attractions'=>[['Snorkeling & Diving','₱2,000'],['Sunset Dolphin Cruise','₱1,500'],['Sandbank Picnic','₱3,000']]],
        ];

        // Match destination — check both aiTo and aiFrom
        $data = null;
        foreach ($lookup as $key => $d) {
            if (str_contains($dest, $key)) { $data = $d; break; }
        }

        // Generic fallback for any unlisted destination
        if (!$data) {
            $cityName = ucwords($this->aiTo ?: 'Destination');
            $isIntl   = $budget >= 50000;
            $mealDay  = $isIntl ? 3000 : 700;
            $data = [
                'code'        => $isIntl ? 'INTL' : 'DOM',
                'airline'     => $isIntl ? 'International Carrier · Direct Flight' : 'Cebu Pacific · Direct Flight',
                'hotel'       => "Grand Hotel {$cityName}",
                'hotel_stars' => $isIntl ? 4 : 3,
                'hotel_type'  => 'Deluxe Room',
                'hotel_city'  => $cityName,
                'restaurant'  => "Local Dining at {$cityName}",
                'meal_plan'   => 'Breakfast, Lunch, & Dinner',
                'meal_city'   => $cityName,
                'meal_day'    => $mealDay,
                'attractions' => [
                    ["{$cityName} City Tour", '₱300'],
                    ['Local Market Visit', 'Free'],
                    ['Heritage & Culture Walk', 'Free'],
                ],
            ];
        }

        // Budget allocation: transport 18%, accommodation 50%, food 28%, attractions 4%
        $transport     = (int) round($budget * 0.18);
        $accommodation = (int) round(($budget * 0.50 / $days)) * $days;
        $foodTotal     = $data['meal_day'] * $days;
        $attrTotal     = array_sum(array_map(fn($a) => is_numeric(str_replace(['₱',','], '', $a[1])) ? (int)str_replace(['₱',','], '', $a[1]) : 0, $data['attractions']));
        $totalEst      = $transport + $accommodation + $foodTotal + $attrTotal;

        $this->aiPackage = [
            'transport'     => ['label'=>'TRANSPORTATION','icon'=>'fa-solid fa-plane','from_code'=>$this->resolveCode($this->aiFrom ?: 'Manila'),'to_code'=>$data['code'],'detail'=>$data['airline'].' · Direct Flight · Round Trip','cost'=>$transport],
            'accommodation' => ['label'=>'ACCOMMODATION','icon'=>'fa-solid fa-bed','name'=>$data['hotel'],'stars'=>$data['hotel_stars'],'detail'=>$days.' Nights · '.$data['hotel_type'].' · '.$data['hotel_city'],'cost'=>$accommodation],
            'food'          => ['label'=>'FOOD & DINING','icon'=>'fa-solid fa-utensils','name'=>$data['restaurant'],'detail'=>$days.' Days · '.$data['meal_plan'].' · '.$data['meal_city'],'cost'=>$foodTotal],
            'attractions'   => ['label'=>'ATTRACTIONS','icon'=>'fa-solid fa-landmark','items'=>$data['attractions'],'cost'=>$attrTotal],
            'total'         => $totalEst,
            'budget'        => $budget,
            'pct'           => min(100, (int)round($totalEst / $budget * 100)),
        ];
    }

    public function render()
    {
        return view('livewire.traveler.llm');
    }
}
