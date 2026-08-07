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

    public string $aiPrompt      = '';
    public string $aiStep        = '';
    public string $aiFrom        = '';
    public string $aiTo          = '';
    public int    $aiBudgetMin   = 0;
    public int    $aiBudgetMax   = 0;
    public string $aiDateFrom    = '';
    public string $aiDateTo      = '';
    public int    $aiDays        = 0;
    public int    $aiTravelers   = 0;
    // 3-letter ISO code, e.g. 'PHP'/'USD' — resolved in mount() to the
    // traveler's own profile currency preference (currency_code()) if not
    // already set, so "the application's default currency" (Currency
    // Validation Rule 2) means THEIR default, not a hardcoded assumption.
    // Overridden for the rest of the conversation the moment the traveler
    // explicitly names a different supported currency (Rule 1).
    public string $aiCurrency    = '';
    public array  $aiPackage     = [];
    public int    $aiGenCount    = 0;

    public array $aiDestinationChoices = [];

    public array $messages = [];

    public string $awaitingSlot = '';

    public int $missCount = 0;

    private ?string $ambiguityNotice = null;

    private ?string $currencyNotice = null;

    // Set inside parseAiPrompt()'s budget step when the traveler names a
    // currency marker that isn't one of the twelve SUPPORTED_CURRENCIES
    // (Currency Validation Rule 5) — never synced, same reasoning as
    // $ambiguityNotice above.
    private ?string $unsupportedCurrencyNotice = null;

    private array $aiPlaceCache = [];

    // Set inside aiPlaceFallback() specifically when every AI provider
    // failed to respond at all (not when one responded and said "not a real
    // place") — lets the missing-destination message tell the traveler
    // "we couldn't check right now" instead of implying they made a typo,
    // without loosening the actual accept/reject gate itself. Reset at the
    // top of every turn in automateTrip().
    private bool $placeVerificationFailed = false;

    public bool $aiBudgetIsDaily = false;

    public bool $showHistory       = false;
    public ?int $viewingHistoryId  = null;
    public ?int $historyEntryToDelete = null;

    public function getProfileInterestsProperty(): array
    {
        return auth()->user()->userProfile?->interests ?? [];
    }

    public function getConversationHistoryProperty()
    {
        return AiConversationHistory::where('user_id', auth()->id())
            ->latest()
            ->get();
    }

    public function getViewingHistoryEntryProperty()
    {
        if ($this->viewingHistoryId === null) return null;

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

        AiConversationHistory::where('user_id', auth()->id())
            ->where('id', $this->historyEntryToDelete)
            ->delete();

        if ($this->viewingHistoryId === $this->historyEntryToDelete) {
            $this->viewingHistoryId = null;
        }

        $this->historyEntryToDelete = null;
    }

    public function mount(): void
    {
        // The traveler's own profile preference is "the application's
        // default currency" (Currency Validation Rule 2) — set here,
        // unconditionally, before the no-draft early return below, so a
        // brand-new conversation still starts from THEIR default rather
        // than a hardcoded PHP assumption. A resumed draft's own stored
        // currency (set below) overrides this once one exists.
        $this->aiCurrency = currency_code();

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
        $this->aiTravelers   = $draft->ai_travelers;
        $this->aiCurrency    = $draft->ai_currency ?: $this->aiCurrency;
        $this->awaitingSlot  = $draft->awaiting_slot;
        $this->missCount     = $draft->miss_count;
        $this->aiStep        = $draft->ai_step;
        $this->aiPackage     = $draft->ai_package ?? [];
        $this->aiGenCount    = $draft->ai_gen_count;

        if ($this->aiStep === 'loading') {
            $this->processAiTrip();
        }
    }

    public function dehydrate(): void
    {

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
                'ai_travelers'  => $this->aiTravelers,
                'ai_currency'   => $this->aiCurrency,
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

        if (strcasecmp($userText, '/reset') === 0) {
            $this->resetConversation();
            return;
        }

        $previouslyAwaiting = $this->awaitingSlot;
        $this->placeVerificationFailed = false;

        $this->messages[] = ['role' => 'user', 'text' => $userText];

        if (!empty($this->aiDestinationChoices)) {
            if (preg_match('/^(\d{1,2})$/', $userText, $m)) {
                $index = ((int) $m[1]) - 1;
                if (isset($this->aiDestinationChoices[$index])) {
                    $this->aiTo = $this->aiDestinationChoices[$index];
                }
            }

            $this->aiDestinationChoices = [];
        }

        if ($this->isGreetingOnly($userText)) {
            $this->aiPrompt = '';
            $this->messages[] = ['role' => 'assistant', 'text' => self::GREETING_REPLY];
            $this->dispatch('message-added');
            return;
        }

        if ($this->containsProfanity($userText)) {
            $this->aiPrompt = '';
            $this->messages[] = ['role' => 'assistant', 'text' => self::PROFANITY_REPLY];
            $this->dispatch('message-added');
            return;
        }

        $this->parseAiPrompt();

        if ($this->ambiguityNotice !== null) {
            $notice = $this->ambiguityNotice;
            $this->ambiguityNotice = null;
            $this->aiPrompt = '';
            $this->messages[] = ['role' => 'assistant', 'text' => $notice];
            $this->dispatch('message-added');
            return;
        }

        // Currency Validation Rule 5 — stop the turn here, before any
        // budget value gets set from the same message, so an unsupported
        // currency is never silently treated as pesos.
        if ($this->unsupportedCurrencyNotice !== null) {
            $notice = $this->unsupportedCurrencyNotice;
            $this->unsupportedCurrencyNotice = null;
            $this->aiPrompt = '';
            $this->messages[] = ['role' => 'assistant', 'text' => $notice];
            $this->dispatch('message-added');
            return;
        }

        if ($this->currencyNotice !== null) {
            $notice = $this->currencyNotice;
            $this->currencyNotice = null;
            $this->messages[] = ['role' => 'assistant', 'text' => $notice];
            $this->dispatch('message-added');
        }

        $this->applyDirectAnswerFallback($userText);

        $optionsCount = $this->parseOptionsCount($userText);
        if ($optionsCount !== null) {
            $choices = $this->suggestDestinations($userText, $optionsCount);
            if (!empty($choices)) {
                $this->aiDestinationChoices = $choices;
                $this->aiTo     = '';
                $this->aiPrompt = '';
                $list = '';
                foreach ($choices as $i => $name) {
                    $list .= ($i + 1) . ". {$name}\n";
                }
                $this->missCount    = 0;
                $this->awaitingSlot = 'destination';
                $this->messages[] = ['role' => 'assistant', 'text' =>
                    "Here are a few options:\n" . trim($list) . "\n\nJust tell me the number or the name of the one you'd like."];
                $this->dispatch('message-added');
                return;
            }

        }

        if ($this->aiTo === '' && $this->isRecommendationRequest($userText)) {

            $namedPlace = $this->knownPlaceName($this->cleanCityName($userText));
            if ($namedPlace !== '' && $this->aiFrom !== '' && strtolower($namedPlace) === strtolower($this->aiFrom)) {
                $namedPlace = '';
            }

            if ($namedPlace !== '') {
                $this->aiTo = $namedPlace;

            } else {
                $suggestion = $this->suggestDestination($userText);
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

            }
        }

        if ($this->aiTo !== '' && $this->aiBudgetMax > 0 && $this->isBudgetEnoughQuestion($userText)) {
            $days   = $this->aiDays > 0 ? $this->aiDays : 3;
            $perDay = (int) round($this->aiBudgetMax / $days);
            $floor  = $this->budgetFloor();
            $dayLabel = $this->aiDays > 0 ? "{$days}-day" : "typical {$days}-day";

            if ($perDay >= $floor) {
                $reply = "Yes, ₱" . number_format($this->aiBudgetMax) . " should be enough for a {$dayLabel} trip to {$this->aiTo} — that's about ₱" . number_format($perDay) . "/day, which is a reasonable budget for flights, stay, food, and activities. Want me to put together a package?";
            } else {
                $suggested = $floor * $days;
                $reply = "That might be tight — ₱" . number_format($this->aiBudgetMax) . " works out to about ₱" . number_format($perDay) . "/day for a {$dayLabel} trip to {$this->aiTo}. Most trips need at least ₱" . number_format($floor) . "/day (around ₱" . number_format($suggested) . " total) to comfortably cover flights, stay, food, and activities. Want me to plan around that instead, or try to make ₱" . number_format($this->aiBudgetMax) . " work?";
            }

            $this->aiPrompt = '';
            $this->messages[] = ['role' => 'assistant', 'text' => $reply];
            $this->dispatch('message-added');
            return;
        }

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

        $stillMissing = $this->missingSlotKey();

        if ($stillMissing !== '') {
            // A slot failing to resolve for the first time ever ($stillMissing
            // !== $previouslyAwaiting) isn't automatically the SAME as the
            // traveler never having tried it — "I want to go to abcsd city"
            // is a real (if unresolved) attempt at destination, not silence
            // about it, and deserves the "I didn't catch that" phrasing
            // immediately rather than the neutral first-ask question, which
            // reads as if the attempt was never even seen.
            $this->missCount = match (true) {
                $stillMissing === $previouslyAwaiting => $this->missCount + 1,
                $this->looksLikeAttempt($stillMissing, $userText) => 1,
                default => 0,
            };
            $this->awaitingSlot = $stillMissing;

            // A place-verification service outage means we genuinely never
            // found out whether what the traveler typed is real — telling
            // them "I didn't catch that" would wrongly imply THEY got it
            // wrong, when it's on us. Only overrides destination/origin,
            // the only slots aiPlaceFallback() is ever involved in.
            $reply = ($this->placeVerificationFailed && in_array($stillMissing, ['destination', 'origin'], true))
                ? "I'm having trouble verifying that place right now — could you try again in a moment, or double-check the spelling?"
                : $this->questionFor($stillMissing, $this->missCount);

            $this->messages[] = ['role' => 'assistant', 'text' => $reply];
            $this->dispatch('message-added');
            return;
        }

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

        if ($this->aiBudgetMax > 0 && $this->aiBudgetMax < self::MINIMUM_TOTAL_BUDGET) {
            $this->aiBudgetMin  = 0;
            $this->aiBudgetMax  = 0;
            $this->awaitingSlot = 'budget';
            $this->missCount    = 0;
            $this->messages[] = ['role' => 'assistant', 'text' =>
                'That budget looks too low to plan a real trip — could you give me a more realistic number (at least ₱' . number_format(self::MINIMUM_TOTAL_BUDGET) . ')?'];
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

    private function resetConversation(): void
    {
        AiConversationDraft::where('user_id', auth()->id())->delete();

        $this->aiPrompt        = '';
        $this->aiStep          = '';
        $this->aiFrom          = '';
        $this->aiTo            = '';
        $this->aiBudgetMin     = 0;
        $this->aiBudgetMax     = 0;
        $this->aiDateFrom      = '';
        $this->aiDateTo        = '';
        $this->aiDays          = 0;
        $this->aiTravelers     = 0;
        $this->aiCurrency      = currency_code();
        $this->aiPackage       = [];
        $this->aiGenCount      = 0;
        $this->aiBudgetIsDaily = false;
        $this->aiDestinationChoices = [];
        $this->awaitingSlot    = '';
        $this->missCount       = 0;
        $this->messages        = [['role' => 'assistant', 'text' => "Conversation reset — let's start fresh! Where would you like to go?"]];

        $this->dispatch('message-added');
    }

    private const NON_ANSWER_FILLERS = [
        'hi', 'hello', 'hey', 'yo', 'sup', 'hiya',
        'good morning', 'good afternoon', 'good evening', 'good day',
        'ok', 'okay', 'k', 'yes', 'yeah', 'yep', 'yup', 'sure', 'alright', 'fine',
        'no', 'nope', 'nah', 'not sure', 'idk', "i don't know", 'dunno', 'maybe',
        'cool', 'great', 'nice', 'awesome', 'thanks', 'thank you', 'please',
        'what', 'why', 'how', 'um', 'uh', 'hmm', 'huh',
    ];

    private const GREETINGS = [
        'hi', 'hello', 'hey', 'yo', 'sup', 'hiya',
        'good morning', 'good afternoon', 'good evening', 'good day',
        'how are you', 'how are you doing', "what's up", 'whats up', 'howdy',
    ];

    private const GREETING_REPLY = "Hello! 😊 How can I help you with your travel plans today?";

    private const PROFANITY_WORDS = [
        'fuck', 'fucking', 'fucked', 'fucker', 'motherfucker',
        'shit', 'shitty', 'bullshit',
        'bitch', 'bitches',
        'asshole', 'assholes',
        'bastard', 'cunt', 'dumbass', 'douchebag',
    ];

    private const PROFANITY_REPLY = "Let's keep things friendly here 🙂 — I'm happy to help plan your trip, just let me know your destination, budget, and dates without the language.";

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

    private function isBudgetEnoughQuestion(string $text): bool
    {
        return str_contains($text, '?') && (bool) preg_match('/\benough\b/i', $text);
    }

    private function suggestDestination(string $userText = ''): string
    {

        set_time_limit(90);

        $interests = $this->profileInterests;
        $interestText = !empty($interests)
            ? implode(', ', $interests)
            : 'general sightseeing, popular beaches, and well-rounded trips';
        $requestText = trim($userText) !== '' ? trim($userText) : '(no specific request — just pick something)';

        $prompt = <<<PROMPT
        You are a Philippine travel assistant. A traveler doesn't know where to go and wants a recommendation.

        Traveler's message: "{$requestText}"
        Traveler's saved interests (fallback only, use these if the message above states no specific preference of its own): {$interestText}

        Suggest exactly ONE real, specific travel destination (a city or island, not a country). If the traveler's message names a preference — weather, scenery (beach/mountain/nature), who it's for (couples/family/photographers/first-time travelers), a vibe (hidden gem, food, nightlife), or anything similar — the destination MUST match that preference first, ahead of the saved interests. It can be in the Philippines or an international destination.

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

    private function parseOptionsCount(string $text): ?int
    {
        if (preg_match('/\btop\s*(\d{1,2})\b/i', $text, $m)) {
            return max(2, min(6, (int) $m[1]));
        }
        if (preg_match('/\b(\d{1,2})\s*(?:options|choices|picks|destinations|places)\b/i', $text, $m)) {
            return max(2, min(6, (int) $m[1]));
        }
        if (preg_match('/\b(?:a\s+few|some|multiple|several|other|more)\s+(?:options|choices|destinations|places)\b/i', $text)
            || preg_match('/\b(?:give|show)\s+me\s+(?:some\s+)?options\b|\bmore\s+options\b|\bother\s+options\b|\ba\s+list\s+of\s+(?:destinations|places|options)\b/i', $text)) {

            return 3;
        }
        return null;
    }

    private function suggestDestinations(string $userText, int $count): array
    {
        set_time_limit(90);

        $interests = $this->profileInterests;
        $interestText = !empty($interests)
            ? implode(', ', $interests)
            : 'general sightseeing, popular beaches, and well-rounded trips';
        $requestText = trim($userText) !== '' ? trim($userText) : '(no specific request — just pick some)';

        $prompt = <<<PROMPT
        You are a Philippine travel assistant. A traveler wants several destination options to choose from, not just one.

        Traveler's message: "{$requestText}"
        Traveler's saved interests (fallback only, use these if the message above states no specific preference of its own): {$interestText}

        Suggest exactly {$count} real, specific, DIFFERENT travel destinations (cities or islands, not countries) that best match what the traveler asked for. If the message names a preference — weather, scenery, who it's for, a vibe, or anything similar — every destination MUST match it. They can be in the Philippines or international. No duplicates.

        Return JSON only, no markdown:
        {"destinations": ["city name", "city name"]}
        PROMPT;

        try {
            $raw = (new GeminiService())->generate($prompt);
            if (!$raw) { $raw = (new GroqService())->generate($prompt); }
            if (!$raw) { $raw = (new OpenRouterService())->generate($prompt); }
            if (!$raw) return [];

            $raw  = trim(preg_replace('/```json\s*|```\s*/i', '', $raw));
            $data = json_decode($raw, true);
            if (!is_array($data) || empty($data['destinations']) || !is_array($data['destinations'])) return [];
        } catch (\Throwable) {
            return [];
        }

        $resolved = [];
        foreach ($data['destinations'] as $candidate) {
            if (!is_string($candidate)) continue;
            $name = $this->knownPlaceName($this->cleanCityName($candidate));
            if ($name === '') continue;

            $isDuplicate = false;
            foreach ($resolved as $existing) {
                if (strtolower($existing) === strtolower($name)) { $isDuplicate = true; break; }
            }
            if (!$isDuplicate) $resolved[] = $name;
        }

        return $resolved;
    }

    private function isGreetingOnly(string $text): bool
    {
        $normalized = strtolower(trim($text, " \t\n\r\0\x0B.!?,"));
        return in_array($normalized, self::GREETINGS, true);
    }

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

    private function applyDirectAnswerFallback(string $userText): void
    {
        if (str_word_count($userText) > 6) return;
        if ($this->isNonAnswerFiller($userText)) return;

        if ($this->awaitingSlot === 'destination' && $this->aiTo === '') {

            $resolved = $this->knownPlaceName($this->cleanCityName($userText));
            if ($resolved !== '') {
                $this->aiTo = $resolved;
            }
        } elseif ($this->awaitingSlot === 'origin' && $this->aiFrom === '') {
            $resolved = $this->knownPlaceName($this->cleanCityName($userText));
            if ($resolved !== '') {
                $this->aiFrom = $resolved;
            }
        } elseif ($this->awaitingSlot === 'travelers' && $this->aiTravelers === 0) {

            if (preg_match('/\bsolo\b|\bjust me\b|\balone\b|\bmyself\b/i', $userText)
                && !preg_match('/\band\b|\bwith\b|\+/i', $userText)) {
                $this->aiTravelers = 1;
            } elseif (preg_match('/(\d{1,2})/', $userText, $m)) {
                $v = (int) $m[1];
                if ($v > 0) $this->aiTravelers = $v;
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

    }

    private function extractWithAi(string $userText): string
    {

        set_time_limit(90);

        $known = sprintf(
            "- Origin: %s\n- Destination: %s\n- Travelers: %s\n- Budget: %s\n- Travel dates: %s",
            $this->aiFrom !== '' ? $this->aiFrom : 'unknown',
            $this->aiTo !== '' ? $this->aiTo : 'unknown',
            $this->aiTravelers > 0 ? $this->aiTravelers : 'unknown',
            ($this->aiBudgetMin || $this->aiBudgetMax) ? "₱{$this->aiBudgetMin}-₱{$this->aiBudgetMax}" : 'unknown',
            ($this->aiDateFrom && $this->aiDateTo) ? "{$this->aiDateFrom} to {$this->aiDateTo}" : 'unknown',
        );

        $today = date('l, M j, Y');

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
  "travelers": "number of people traveling, or null",
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

        if ($this->aiTravelers === 0 && !empty($data['travelers'])) {
            $v = (int) $data['travelers'];
            if ($v > 0) {
                $this->aiTravelers = $v;
            }
        }

        // The model is only trusted with a budget it read off actual digits
        // in the traveler's own message — guards against it hallucinating a
        // number out of a letters-only reply (e.g. random keyboard mashing),
        // while still allowing currency mentions like "$500" or "5k USD"
        // since those always carry a digit alongside the currency word.
        if ($this->aiBudgetMin === 0 && $this->aiBudgetMax === 0
            && (!empty($data['budget_min']) || !empty($data['budget_max']))
            && preg_match('/\d/', $userText)) {
            $this->aiBudgetMin = min(self::MAX_BUDGET, (int) ($data['budget_min'] ?? $data['budget_max']));
            $this->aiBudgetMax = min(self::MAX_BUDGET, (int) ($data['budget_max'] ?? $data['budget_min']));
        }

        if (($this->aiDateFrom === '' || $this->aiDateTo === '')
            && !empty($data['date_from']) && !empty($data['date_to'])) {
            $tsFrom = strtotime($data['date_from'] . ' ' . date('Y'));
            $tsTo   = strtotime($data['date_to']);

            if ($tsFrom && $tsTo && $tsFrom >= strtotime('today')) {
                $this->aiDateFrom = $data['date_from'];
                $this->aiDateTo   = $data['date_to'];
                $this->aiDays     = (int) ceil(abs($tsTo - $tsFrom) / 86400) + 1;
            }
        }

        return '';
    }

    private function missingSlotKey(): string
    {
        if ($this->aiTo === '') return 'destination';
        if ($this->aiFrom === '') return 'origin';
        if ($this->aiTravelers === 0) return 'travelers';
        if ($this->aiBudgetMin === 0 && $this->aiBudgetMax === 0) return 'budget';
        if ($this->aiDateFrom === '' || $this->aiDateTo === '') return 'dates';
        return '';
    }

    private function questionFor(string $slot, int $missCount): string
    {
        if ($missCount <= 0) {
            return match ($slot) {
                'destination' => "Sure! Where would you like to go?",
                'origin'      => "Nice choice! Where will you be traveling from?",
                'travelers'   => "Got it. How many people are traveling?",
                'budget'      => "Got it. What's your budget for this trip?",
                'dates'       => "Got it. When are you planning to travel? (e.g. \"August 3 to 10\")",
                default       => '',
            };
        }

        if ($missCount === 1) {
            return match ($slot) {
                'destination' => "Sorry, I didn't quite catch a destination there — which place would you like to go to?",
                'origin'      => "Hmm, I still need a starting point — what city will you be flying from?",
                'travelers'   => "I didn't catch a number — how many people are going, e.g. \"2\" or \"solo\"?",
                'budget'      => "I didn't catch a number — roughly how much do you want to spend, e.g. \"20000\" or \"20k\"?",
                'dates'       => "I still need actual travel dates — something like \"August 3 to 10\" or \"8/3/2026\" works best.",
                default       => '',
            };
        }

        return match ($slot) {
            'destination' => "Let's try just the place name by itself — for example: Boracay",
            'origin'      => "Just the city name works — for example: Manila",
            'travelers'   => "Just a plain number works — for example: 2",
            'budget'      => "Just a plain number works — for example: 20000",
            'dates'       => "Just the dates works — for example: August 3 to 10, 2026",
            default       => '',
        };
    }

    // Whether $userText looks like a genuine (even if it ultimately failed
    // to resolve) attempt at answering $slot — cheap, deliberately loose
    // heuristics per slot, not another AI call. Only reached after the
    // greeting/filler/off-topic checks earlier in automateTrip() already
    // returned, so anything landing here is already known to be real,
    // substantive text — this just asks whether that text was actually
    // AIMED at the slot in question.
    private function looksLikeAttempt(string $slot, string $userText): bool
    {
        return match ($slot) {
            'destination', 'origin' => $this->hasPlaceLikeCandidate($userText),
            'travelers' => (bool) preg_match('/\d|\bsolo\b|\balone\b|\bjust me\b|\bmyself\b/i', $userText),
            'budget'    => (bool) preg_match('/\d/', $userText),
            'dates'     => (bool) preg_match(
                '/\d|january|february|march|april|may|june|july|august|september|october|november|december|jan|feb|mar|apr|jun|jul|aug|sep|oct|nov|dec/i',
                $userText
            ),
            default => false,
        };
    }

    // A capitalized word run is always worth treating as a place attempt
    // (same "looks like a proper noun" heuristic this file's own city regex
    // relies on elsewhere). Otherwise, only count a bare "to/in/at/from..."
    // preposition as an attempt when it's followed by a word that ISN'T one
    // of the common verbs/connectors that show up after it in perfectly
    // ordinary sentences having nothing to do with naming a place — a bare
    // "\bto\b" check alone would wrongly flag "I want to plan a trip" as an
    // attempted destination just because it contains the word "to".
    private function hasPlaceLikeCandidate(string $text): bool
    {
        if (preg_match('/[A-Z][a-z]+/', $text)) return true;

        $notPlaceWords = 'plan|book|go|travel|visit|find|get|make|do|have|see|know|ask|try|be|buy|spend|save|figure|decide|somewhere|anywhere|someplace';
        return (bool) preg_match(
            '/\b(?:to|in|at|from|visit(?:ing)?|travel(?:l?ing)?\s+to|fly(?:ing)?\s+to|go(?:ing)?\s+to|stay(?:ing)?\s+(?:in|at))\s+(?!(?:' . $notPlaceWords . ')\b)[a-z]{2,}\b/iu',
            $text
        );
    }

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

    public function showResults(): void
    {
        if (!empty($this->aiPackage)) {
            $this->aiStep = 'results';
        }
    }

    #[On('ai-process-trip')]
    public function processAiTrip(): void
    {

        set_time_limit(450);

        $summary = $this->conversationSummary();
        $package = null;
        try {
            $package = (new GeminiService())->planTrip($summary);
        } catch (\Throwable) {

        }

        if (!$package || empty($package['to'])) {
            try {
                $package = (new GroqService())->planTrip($summary);
            } catch (\Throwable) {
                $package = null;
            }
        }

        if (!$package || empty($package['to'])) {
            try {
                $package = (new OpenRouterService())->planTrip($summary);
            } catch (\Throwable) {
                $package = null;
            }
        }

        if ($package && !empty($package['to'])) {
            // By the time this runs, aiFrom/aiTo are already a validated
            // destination from earlier in the conversation — this free-
            // generation step's OWN 'from'/'to' fields are just its echo of
            // that request, not a fresh decision, and generation can drift
            // (a known LLM failure mode, not malice). Only ever fills in if
            // still genuinely empty, gated the same as every other path, so
            // a generation echo can never silently overwrite an already-
            // validated destination with something unverified.
            if ($this->aiFrom === '') {
                $resolved = $this->knownPlaceName($this->cleanCityName($package['from'] ?? ''));
                if ($resolved !== '') $this->aiFrom = $resolved;
            }
            if ($this->aiTo === '') {
                $resolved = $this->knownPlaceName($this->cleanCityName($package['to'] ?? ''));
                if ($resolved !== '') $this->aiTo = $resolved;
            }
            if ($this->aiTravelers === 0 && !empty($package['travelers'])) {
                $v = (int) $package['travelers'];
                if ($v > 0) $this->aiTravelers = $v;
            }

            if (isset($package['transport']) && is_array($package['transport'])) {
                $package['transport']['from_code'] = $this->resolveCode($this->aiFrom ?: 'Manila');
                $package['transport']['to_code']   = $this->resolveCode($this->aiTo);
            }

            if ($this->aiBudgetMin === 0 && $this->aiBudgetMax === 0
                && (!empty($package['budget_min']) || !empty($package['budget_max']))) {
                $this->aiBudgetMin = (int) ($package['budget_min'] ?? $package['budget_max']);
                $this->aiBudgetMax = (int) ($package['budget_max'] ?? $package['budget_min']);
            }

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

            $travelers = max(1, $this->aiTravelers);
            if (isset($package['transport']['cost'])) {
                $package['transport']['cost'] = (int) round($package['transport']['cost'] * $travelers);
            }
            if (isset($package['food']['cost'])) {
                $package['food']['cost'] = (int) round($package['food']['cost'] * $travelers);
            }

            $transportCost     = (int)($package['transport']['cost']     ?? 0);
            $accommodationCost = (int)($package['accommodation']['cost'] ?? 0);
            $foodCost          = (int)($package['food']['cost']          ?? 0);
            $attractionsCost   = (int)($package['attractions']['cost']   ?? 0);

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

        $this->parseAiPrompt();
        $serpPackage = $this->buildSerpApiPackage();
        if ($serpPackage) {
            $this->aiPackage = $serpPackage;
            $this->aiStep    = 'results';
            return;
        }

        $this->generateAiPackage();
        $this->aiStep = 'results';
    }

    private function buildSerpApiPackage(): ?array
    {
        if (empty($this->aiTo)) return null;

        $serp   = new SerpApiService();
        $days   = max(1, $this->aiDays);
        $budget = $this->aiBudgetMax ?: $this->aiBudgetMin ?: 30000;

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

        $fromCode = $this->resolveCode($this->aiFrom ?: 'Manila');
        $toCode   = $this->resolveCode($this->aiTo);

        $transportBudget     = (int)round($budget * 0.18);
        $accommodationBudget = (int)round($budget * 0.50);
        $foodBudget          = (int)round($budget * 0.28);
        $attractionBudget    = (int)round($budget * 0.04);

        $gen = $this->aiGenCount;
        $flightData  = $serp->searchFlights($fromCode, $toCode, $checkIn, $checkOut, $gen, $transportBudget);
        $hotelData   = $serp->searchHotels($this->aiTo, $checkIn, $checkOut, $days, $gen, $accommodationBudget);
        $restaurData = $serp->searchRestaurants($this->aiTo, $days, $foodBudget, $gen);
        $attrItems   = $serp->searchAttractions($this->aiTo, $gen);

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

        if (!$flightData && !$hotelData && !$restaurData && !$attrItems) return null;

        $travelers = max(1, $this->aiTravelers);
        if ($flightData) {
            $flightData['cost'] = (int) round(($flightData['cost'] ?? 0) * $travelers);
        }
        if ($restaurData) {
            $restaurData['cost'] = (int) round(($restaurData['cost'] ?? 0) * $travelers);
        }

        $transport = $flightData
            ? array_merge(['label'=>'TRANSPORTATION','icon'=>'fa-solid fa-plane','from_code'=>$fromCode,'to_code'=>$toCode], $flightData)
            : ['label'=>'TRANSPORTATION','icon'=>'fa-solid fa-plane','from_code'=>$fromCode,'to_code'=>$toCode,'detail'=>'Direct Flight · Round Trip','cost'=>$transportBudget];

        $accommodation = $hotelData
            ? array_merge(['label'=>'ACCOMMODATION','icon'=>'fa-solid fa-bed'], $hotelData)
            : ['label'=>'ACCOMMODATION','icon'=>'fa-solid fa-bed','name'=>'Hotel in '.$this->aiTo,'stars'=>3,'detail'=>$days.' Nights · Standard Room · '.$this->aiTo,'cost'=>$accommodationBudget];

        $food = $restaurData
            ? array_merge(['label'=>'FOOD & DINING','icon'=>'fa-solid fa-utensils'], $restaurData)
            : ['label'=>'FOOD & DINING','icon'=>'fa-solid fa-utensils','name'=>'Dining in '.$this->aiTo,'detail'=>$days.' Days · Breakfast, Lunch, & Dinner · '.$this->aiTo,'cost'=>$foodBudget];

        $items    = $attrItems ?? [[$this->aiTo . ' City Tour', '₱300'],['Local Market Visit','Free']];
        $attrCost = array_sum(array_map(
            fn($a) => is_numeric(str_replace(['₱',','], '', $a[1])) ? (int)str_replace(['₱',','], '', $a[1]) : 0,
            $items
        ));
        $attractions = ['label'=>'ATTRACTIONS','icon'=>'fa-solid fa-landmark','items'=>$items,'cost'=>$attrCost];

        $rawPackage = [
            'transport'     => $transport,
            'accommodation' => $accommodation,
            'food'          => $food,
            'attractions'   => $attractions,
        ];

        try {
            $enriched = (new GeminiService())->enrichPackage($rawPackage, $this->aiTo, $days, $budget);
            if (!$enriched) {
                $enriched = (new GroqService())->enrichPackage($rawPackage, $this->aiTo, $days, $budget);
            }
            if (!$enriched) {
                $enriched = (new OpenRouterService())->enrichPackage($rawPackage, $this->aiTo, $days, $budget);
            }
            if ($enriched && is_array($enriched)) {

                foreach (['transport','accommodation','food','attractions'] as $key) {
                    if (!isset($enriched[$key])) continue;
                    foreach ($enriched[$key] as $field => $val) {
                        if (!is_numeric($val)) {
                            $rawPackage[$key][$field] = $val;
                        }
                    }
                }

                if (!empty($rawPackage['attractions']['items'])) {
                    $rawPackage['attractions']['cost'] = array_sum(array_map(
                        fn($a) => is_numeric(str_replace(['₱',','], '', $a[1] ?? '')) ? (int)str_replace(['₱',','], '', $a[1]) : 0,
                        $rawPackage['attractions']['items']
                    ));
                }
            }
        } catch (\Throwable) {

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

    private function matchKnownPlace(string $city): ?array
    {
        $map = [

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

            'delhi' => 'DEL', 'new delhi' => 'DEL', 'india' => 'DEL',
            'mumbai' => 'BOM', 'bombay' => 'BOM',
            'bangalore' => 'BLR', 'bengaluru' => 'BLR',
            'chennai' => 'MAA', 'madras' => 'MAA',
            'kolkata' => 'CCU',
            'hyderabad' => 'HYD',

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

            'sydney' => 'SYD', 'australia' => 'SYD',
            'melbourne' => 'MEL',
            'brisbane' => 'BNE',
            'perth' => 'PER',
            'auckland' => 'AKL', 'new zealand' => 'AKL',

            'new york' => 'JFK', 'new york city' => 'JFK', 'nyc' => 'JFK',
            'los angeles' => 'LAX', 'la' => 'LAX',
            'san francisco' => 'SFO',
            'chicago' => 'ORD',
            'miami' => 'MIA',
            'toronto' => 'YYZ', 'canada' => 'YYZ',
            'vancouver' => 'YVR',
            'sao paulo' => 'GRU', 'brazil' => 'GRU',

            'nairobi' => 'NBO', 'kenya' => 'NBO',
            'johannesburg' => 'JNB', 'south africa' => 'JNB',
            'cairo' => 'CAI', 'egypt' => 'CAI',
            'casablanca' => 'CMN', 'morocco' => 'CMN',

            'maldives' => 'MLE', 'male' => 'MLE',
        ];

        $key = strtolower(trim($city));
        if (isset($map[$key])) return ['name' => $key, 'code' => $map[$key]];

        $words = preg_split('/[\s,!?.;:]+/', $key, -1, PREG_SPLIT_NO_EMPTY);
        $count = count($words);
        for ($len = $count - 1; $len >= 1; $len--) {
            for ($start = 0; $start + $len <= $count; $start++) {
                $candidate = implode(' ', array_slice($words, $start, $len));
                if (isset($map[$candidate])) return ['name' => $candidate, 'code' => $map[$candidate]];
            }
        }

        return $this->aiPlaceFallback($key);
    }

    private function aiPlaceFallback(string $key): ?array
    {

        if ($key === '' || mb_strlen($key) > 40) return null;
        if (in_array($key, self::NON_ANSWER_FILLERS, true)) return null;
        if (array_key_exists($key, $this->aiPlaceCache)) return $this->aiPlaceCache[$key];

        // Deterministic shape check, no AI call needed — catches the exact
        // class of bug that slipped through the AI's own judgment ("asavc123
        // city" got hallucinated as real). Cheap, free, and never wrong: no
        // real place name fuses digits into a word or repeats one character
        // 4+ times in a row, so this can only ever reject, never falsely
        // accept, and never needs to guess the way the AI check below does.
        if ($this->looksLikeGibberish($key)) return $this->aiPlaceCache[$key] = null;

        set_time_limit(90);

        // "Be skeptical... only if confident" is deliberate, not filler —
        // the plain "is this real?" version of this prompt is exactly what
        // let a single confidently-wrong answer ("Nicsland" hallucinated as
        // real) straight through. Asking for genuine confidence instead of
        // plausibility makes a lone guess less likely to pass even before
        // the second opinion below gets involved.
        $prompt = <<<PROMPT
        Is "{$key}" a real, specific travel destination — an actual city, town, or island that genuinely exists?

        Be skeptical. Only answer yes if you are genuinely confident this is a real place you have real knowledge of — not just because the name sounds plausible or place-like. If you don't specifically recognize it, or aren't sure, answer no. This is NOT a made-up place, NOT a generic word or phrase, and NOT a whole country by itself.

        If yes, return its most common English city/town/island name and the IATA code of the real airport travelers would realistically fly into to reach it (the nearest major one, if it has none of its own).
        If no, return false and null for both fields.

        Return JSON only, no markdown:
        {"is_real_place": true or false, "name": "city name or null", "iata_code": "CODE or null"}
        PROMPT;

        $providers = [GeminiService::class, GroqService::class, OpenRouterService::class];

        $first = $this->askPlaceVerifier($prompt, $providers);
        if ($first === null) {
            $this->placeVerificationFailed = true;
            return $this->aiPlaceCache[$key] = null;
        }

        // Cross-check with a genuinely DIFFERENT provider than whichever
        // just answered — a single model confidently inventing a
        // plausible-sounding place is exactly what slipped through before;
        // requiring a second, independent model to also confirm it is a
        // much stronger bar than trusting one answer alone.
        $second = $this->askPlaceVerifier($prompt, array_values(array_diff($providers, [$first['provider']])));
        if ($second === null) {
            $this->placeVerificationFailed = true;
            return $this->aiPlaceCache[$key] = null;
        }

        if ($first['data'] === null || $second['data'] === null) {
            // Either one said no, or one gave back something unusable —
            // both must agree it's real, so anything short of that is a
            // reject, not just whichever answered first.
            return $this->aiPlaceCache[$key] = null;
        }

        $code = strtoupper(trim((string) $first['data']['iata_code']));
        if (!preg_match('/^[A-Z]{3}$/', $code)) {
            return $this->aiPlaceCache[$key] = null;
        }

        return $this->aiPlaceCache[$key] = ['name' => strtolower(trim((string) $first['data']['name'])), 'code' => $code];
    }

    // Tries each provider class in order, stopping at the first one that
    // responds at all — returns null only when NONE of them responded
    // (a real outage), never when one responded with a "no" or bad JSON,
    // since that's a genuine answer aiPlaceFallback() needs to see, not a
    // failure to get one.
    private function askPlaceVerifier(string $prompt, array $providerClasses): ?array
    {
        foreach ($providerClasses as $class) {
            try {
                $raw = (new $class())->generate($prompt);
            } catch (\Throwable) {
                continue;
            }
            if (!$raw) continue;

            $raw  = trim(preg_replace('/```json\s*|```\s*/i', '', $raw));
            $json = json_decode($raw, true);
            $valid = is_array($json) && !empty($json['is_real_place']) && !empty($json['name']) && !empty($json['iata_code']);

            return ['provider' => $class, 'data' => $valid ? $json : null];
        }

        return null;
    }

    // Conservative on purpose: only flags shapes that are essentially NEVER
    // a real place, nothing borderline or uncertain. Anything not caught
    // here still goes through the actual AI check as before — this only
    // ever short-circuits an obvious reject, it never decides an accept.
    private function looksLikeGibberish(string $text): bool
    {
        // Letters with digits fused directly into them, no space between
        // ("asavc123", "abc123def") — real place names don't do this.
        if (preg_match('/[a-zA-Z]{2,}\d+|\d+[a-zA-Z]{2,}/', $text)) return true;

        // The same character repeated 4+ times in a row ("aaaa", "xxxxxxx")
        // — keyboard mashing, never a real place name.
        if (preg_match('/(.)\1{3,}/i', $text)) return true;

        // A short (2-4 char) chunk repeated 3+ times in a row ("asdasdasd",
        // "lolol") — same keyboard-mashing signal as above, just a repeated
        // PATTERN instead of a single repeated character.
        if (preg_match('/(.{2,4})\1{2,}/i', $text)) return true;

        // Mostly symbols/punctuation rather than actual letters ("%@!#",
        // "???city"). \p{L} (any Unicode letter, not just a-z) so a real
        // place with an apostrophe, hyphen, or accented letter — "Coeur
        // d'Alene", "São Paulo", "St. Petersburg" — is never caught by
        // this: verified empirically, those all sit at 75%+ letters. The
        // 0.7 cutoff leaves comfortable margin above every real place
        // tested while still catching anything symbol-dominant.
        $nonSpace = preg_replace('/\s+/u', '', $text);
        if ($nonSpace !== '') {
            $letters = preg_replace('/[^\p{L}]/u', '', $nonSpace);
            if (mb_strlen($letters) / mb_strlen($nonSpace) < 0.7) return true;
        }

        return false;
    }

    public function iataCode(string $city): string
    {
        return $this->matchKnownPlace($city)['code'] ?? '';
    }

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

    private function sameOriginAndDestination(): bool
    {
        if ($this->aiFrom === '' || $this->aiTo === '') return false;
        if (strtolower($this->aiFrom) === strtolower($this->aiTo)) return true;

        $fromCode = $this->iataCode($this->aiFrom);
        return $fromCode !== '' && $fromCode === $this->iataCode($this->aiTo);
    }

    private function budgetFloor(): int
    {
        $profileDailyBudget = (int) (auth()->user()?->userProfile?->daily_budget ?? 0);
        return $profileDailyBudget > 0 ? $profileDailyBudget : 500;
    }

    private const MINIMUM_TOTAL_BUDGET = 10000;

    // Single source of truth for the Currency Validation Rules' exact
    // supported list (PHP + 11 others) — name, display symbol, and the
    // peso exchange rate (pesos per 1 unit of that currency; PHP's own
    // rate is 1 by definition) all live together per code, instead of
    // three separate maps that could silently drift out of sync with each
    // other. "Supported" for Rule 3/5 purposes means "a key in this array."
    private const SUPPORTED_CURRENCIES = [
        'PHP' => ['name' => 'Philippine pesos',   'symbol' => '₱',    'rate' => 1],
        'USD' => ['name' => 'US dollars',         'symbol' => '$',    'rate' => 56],
        'EUR' => ['name' => 'euros',              'symbol' => '€',    'rate' => 61],
        'GBP' => ['name' => 'British pounds',     'symbol' => '£',    'rate' => 71],
        'JPY' => ['name' => 'Japanese yen',       'symbol' => '¥',    'rate' => 0.38],
        'SGD' => ['name' => 'Singapore dollars',  'symbol' => 'S$',   'rate' => 42],
        'AUD' => ['name' => 'Australian dollars', 'symbol' => 'A$',   'rate' => 37],
        'KRW' => ['name' => 'Korean won',         'symbol' => '₩',    'rate' => 0.041],
        'HKD' => ['name' => 'Hong Kong dollars',  'symbol' => 'HK$',  'rate' => 7.2],
        'THB' => ['name' => 'Thai baht',          'symbol' => '฿',    'rate' => 1.6],
        'MYR' => ['name' => 'Malaysian ringgit',  'symbol' => 'RM',   'rate' => 12.5],
        'AED' => ['name' => 'UAE dirhams',        'symbol' => 'AED ', 'rate' => 15.3],
    ];

    // Free-text symbol/name variants (including fullwidth Unicode
    // lookalikes some keyboards/IMEs produce, e.g. U+FFE5 ¥ instead of the
    // standard U+00A5) that all resolve to one SUPPORTED_CURRENCIES code.
    private const CURRENCY_ALIASES = [
        '$' => 'USD', '＄' => 'USD', 'usd' => 'USD',
        '€' => 'EUR', 'eur' => 'EUR',
        '£' => 'GBP', '￡' => 'GBP', 'gbp' => 'GBP',
        '¥' => 'JPY', '￥' => 'JPY', 'jpy' => 'JPY',
        '₩' => 'KRW', '￦' => 'KRW', 'krw' => 'KRW',
        'sgd' => 'SGD', 'aud' => 'AUD',
        'hkd' => 'HKD', 'thb' => 'THB',
        'myr' => 'MYR', 'aed' => 'AED',
        '₱' => 'PHP', 'php' => 'PHP', 'peso' => 'PHP', 'pesos' => 'PHP',
    ];

    // Detects an explicitly-named NON-peso currency (Currency Validation
    // Rule 1) alongside a budget figure, converts it to pesos for internal
    // budget tracking, and — as a side effect — locks $this->aiCurrency to
    // that currency for the rest of the conversation. Returns null for a
    // bare peso mention (₱/pesos/php): those already have their own
    // dedicated regex branches in parseAiPrompt() and need no conversion,
    // so this function's only job is the NON-peso case.
    private function detectAndConvertCurrency(string $text): ?array
    {
        $symbolOrCode = '(?:\$|＄|€|£|￡|¥|￥|₩|￦|₱|USD|EUR|GBP|JPY|SGD|AUD|KRW|HKD|THB|MYR|AED|PHP|pesos?)';

        $number = '(?:\d{1,3}(?:,\d{3})+|\d+(?:\.\d+)?)';

        if (preg_match('/(' . $symbolOrCode . ')\s*(' . $number . ')/iu', $text, $m)) {
            [$marker, $amountRaw] = [$m[1], $m[2]];
        } elseif (preg_match('/(' . $number . ')\s*(' . $symbolOrCode . ')/iu', $text, $m)) {
            [$amountRaw, $marker] = [$m[1], $m[2]];
        } else {
            return null;
        }

        $code = self::CURRENCY_ALIASES[strtolower($marker)] ?? null;
        if ($code === null || $code === 'PHP') return null;

        $currency = self::SUPPORTED_CURRENCIES[$code] ?? null;
        if ($currency === null) return null;

        $foreignAmount = (float) str_replace(',', '', $amountRaw);
        if ($foreignAmount <= 0) return null;

        $this->aiCurrency = $code;

        return [
            'code'         => $code,
            'currencyName' => $currency['name'],
            'pesoAmount'   => (int) round($foreignAmount * $currency['rate']),
            'displayLabel' => $currency['symbol'] . number_format($foreignAmount),
        ];
    }

    // Currency Validation Rule 5: a currency marker the traveler named that
    // ISN'T one of the twelve supported ones — never silently misread as
    // pesos or invented on our end (Rule 3). Two shapes checked: a 3-letter
    // ISO-style code adjacent to a number ("500 CAD", "CAD 500" — the same
    // adjacency requirement SUPPORTED_CURRENCIES detection uses, so a
    // random unrelated 3-letter acronym elsewhere in the message is never
    // mistaken for a currency), and a small set of other real currency
    // symbols not in our supported list. Returns the unrecognized code, or
    // null when nothing currency-shaped-but-unsupported is present.
    private function detectUnsupportedCurrency(string $text): ?string
    {
        $number = '(?:\d{1,3}(?:,\d{3})+|\d+(?:\.\d+)?)';

        if (preg_match('/\b([A-Z]{3})\b\s*' . $number . '/', $text, $m)
            || preg_match('/' . $number . '\s*\b([A-Z]{3})\b/', $text, $m)) {
            $code = strtoupper($m[1]);
            if (!isset(self::SUPPORTED_CURRENCIES[$code])) return $code;
        }

        foreach (['₹' => 'INR', '₫' => 'VND', '₴' => 'UAH', 'R$' => 'BRL'] as $sym => $code) {
            if (str_contains($text, $sym)) return $code;
        }

        return null;
    }

    // Currency Validation Rule 4: every cost shown to the traveler must be
    // in $this->aiCurrency, not raw pesos with an unrelated symbol slapped
    // on front. Every cost is generated/stored internally in pesos
    // (rewriting the whole generation pipeline — Gemini prompts, SerpAPI
    // cost parsing, the static Layer 3 table — to think natively in
    // foreign currencies is a far larger change than this fix); this is
    // the single conversion point the view calls instead of formatting a
    // raw peso number with currency_symbol() (that helper reads the
    // traveler's PROFILE display preference, which is a different,
    // unrelated setting from the currency actually selected for this
    // trip — that mismatch was the bonus bug found while reviewing this).
    // PHP's own rate is 1, so callers see byte-identical output to before
    // this feature existed whenever no other currency was ever selected.
    public function displayAmount(int|float $pesoAmount, ?string $currencyCode = null): string
    {
        $currency = self::SUPPORTED_CURRENCIES[$currencyCode ?? $this->aiCurrency] ?? self::SUPPORTED_CURRENCIES['PHP'];
        return $currency['symbol'] . number_format($pesoAmount / $currency['rate']);
    }

    private function pickFromPool(array $pool, int $gen, int $poolSize = 3): array
    {
        $offset = $gen === 0 ? 0 : min($gen, max(0, count($pool) - $poolSize));
        $slice  = array_slice($pool, $offset, $poolSize) ?: $pool;
        return $slice[array_rand($slice)];
    }

    public function regeneratePackage(): void
    {
        $this->aiGenCount++;

        $package = $this->buildSerpApiPackage();
        if ($package) {
            $this->aiPackage = $package;
            return;
        }

        $this->generateAiPackage();
    }

    public function proceedToWizardItinerary(): mixed
    {
        if (empty($this->aiPackage)) return null;
        $pkg = $this->aiPackage;

        $year = date('Y');
        if ($this->aiDateTo && preg_match('/(\d{4})$/', $this->aiDateTo, $ym)) {
            $year = $ym[1];
        }

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
            'ai_travelers'  => $this->aiTravelers,
            'ai_package'    => $this->aiPackage,
        ]);

        AiConversationDraft::where('user_id', auth()->id())->delete();
        $this->messages = [];

        return $this->redirect(route('trips.plan'), navigate: true);
    }

    public function backToConversation(): void
    {
        $this->aiStep = '';
    }

    public function editWithWizard(?string $section = null): mixed
    {

        $year = date('Y');
        if ($this->aiDateTo && preg_match('/(\d{4})$/', $this->aiDateTo, $ym)) {
            $year = $ym[1];
        }
        $start = $this->aiDateFrom ? date('Y-m-d', strtotime($this->aiDateFrom . ', ' . $year)) : '';
        $end   = $this->aiDateTo   ? date('Y-m-d', strtotime($this->aiDateTo)) : '';

        // Editing a package card from the AI results screen — as soon as
        // the traveler picks a replacement for this specific section, the
        // wizard patches it straight into the AiConversationDraft and sends
        // them back here (mount() restores this exact results screen)
        // instead of continuing on through the rest of the wizard's steps.
        session(['ai_edit_return' => true, 'ai_edit_section' => $section]);

        return $this->redirect(route('trips.plan', array_filter([
            'from'       => $this->aiFrom,
            'to'         => $this->aiTo,
            'budget_min' => $this->aiBudgetMin ?: null,
            'budget_max' => $this->aiBudgetMax ?: null,
            'start'      => $start ?: null,
            'end'        => $end ?: null,
        ])), navigate: true);
    }

    // Budget column is a plain `integer` (max ~2.1B), and no realistic
    // travel budget approaches that anyway — clamp here so a stray long
    // digit string (e.g. someone typing numbers into the destination
    // field) can never reach the database and blow up the column's range.
    private const MAX_BUDGET = 10_000_000;

    private function parseMoneyToken(string $token): int
    {
        $token = trim($token);
        if (preg_match('/^(\d+(?:,\d{3})*)\s*[kK]$/', $token, $m)) {
            return min(self::MAX_BUDGET, (int) str_replace(',', '', $m[1]) * 1000);
        }
        return min(self::MAX_BUDGET, (int) str_replace(',', '', $token));
    }

    private function cleanCityName(string $name): string
    {
        $name = trim($name);

        $name = preg_replace('/^\s*(go(?:ing)?|travel(?:ling)?|fly(?:ing)?|visit(?:ing)?|head(?:ing)?|trip)\s+(?:to\s+)?/i', '', $name);

        $name = preg_replace('/\s+(january|february|march|april|may|june|july|august|september|october|november|december|jan|feb|mar|apr|jun|jul|aug|sep|oct|nov|dec)\b.*/i', '', $name);

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

        $withoutDate = $raw;

        if (preg_match('/\b(' . $mp . ')\.?\s+(\d{1,2})\s*(?:[-–]|to)\s+(' . $mp . ')\.?\s+(\d{1,2})(?:,?\s*(\d{4}))?\b/i', $raw, $m)) {
            $mon1  = $monthMap[strtolower($m[1])];
            $mon2  = $monthMap[strtolower($m[3])];
            $year  = !empty($m[5]) ? (int)$m[5] : (int)date('Y');
            $d1    = (int)$m[2]; $d2 = (int)$m[4];

            if (checkdate($mon1, $d1, $year) && checkdate($mon2, $d2, $year)) {
                $ts1   = mktime(0,0,0,$mon1,$d1,$year);
                $ts2   = mktime(0,0,0,$mon2,$d2,$year);
                $this->aiDateFrom = date('M j', $ts1);
                $this->aiDateTo   = date('M j, Y', $ts2);
                $this->aiDays     = (int)ceil(abs($ts2-$ts1)/86400)+1;
                $withoutDate      = str_replace($m[0], '', $raw);
            }

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

        if (preg_match('/\bsolo\b|\bjust me\b|\balone\b|\bby myself\b/i', $withoutDate)
            && !preg_match('/\band\b|\bwith\b|\+/i', $withoutDate)) {
            $this->aiTravelers = 1;
        } elseif (preg_match('/\b(\d{1,2})\s*(?:people|persons?|pax|travelers?|adults?|of us)\b/i', $withoutDate, $m)) {
            $v = (int) $m[1];
            if ($v > 0) $this->aiTravelers = $v;
        }

        $big = '(?:\d{1,3}(?:,\d{3})+|\d{4,}|\d+\s*[kK]\b)';

        // Checked before every other budget branch below — an unsupported
        // currency must never fall through and get silently misread as a
        // bare peso number by the generic patterns further down.
        if (($unsupported = $this->detectUnsupportedCurrency($withoutDate)) !== null) {
            $this->unsupportedCurrencyNotice = "I couldn't recognize the selected currency. Please choose one of the supported currencies from the list.";

        } elseif ($this->aiBudgetMin === 0 && $this->aiBudgetMax === 0
            && ($conversion = $this->detectAndConvertCurrency($withoutDate)) !== null) {
            $this->aiBudgetMin = $this->aiBudgetMax = min(self::MAX_BUDGET, $conversion['pesoAmount']);
            $this->currencyNotice = "Got it — {$conversion['displayLabel']} is about ₱" . number_format($conversion['pesoAmount']) . ", I'll plan around that.";

        } elseif (
               preg_match('/daily\s*budget\D{0,15}?[₱P]?\s*(' . $big . ')/ui', $withoutDate, $m)
            || preg_match('/[₱P]?\s*(' . $big . ')\D{0,15}?\bdaily\b/ui', $withoutDate, $m)
            || preg_match('/[₱P]?\s*(' . $big . ')\s*(?:per\s*day|\/\s*day|a\s*day)\b/ui', $withoutDate, $m)
        ) {
            $this->aiBudgetMin = $this->aiBudgetMax = $this->parseMoneyToken($m[1]);
            $this->aiBudgetIsDaily = true;

        } elseif (preg_match('/[₱P]?\s*(' . $big . ')\s*(?:[-–]|to)\s*[₱P]?\s*(' . $big . ')/ui', $withoutDate, $m)) {
            $a = $this->parseMoneyToken($m[1]);
            $b = $this->parseMoneyToken($m[2]);
            $this->aiBudgetMin = min($a,$b);
            $this->aiBudgetMax = max($a,$b);

        } elseif (preg_match('/budget\s*(?:is|of|:)?\s*[₱P]?\s*(' . $big . ')/ui', $withoutDate, $m)) {
            $this->aiBudgetMin = $this->aiBudgetMax = $this->parseMoneyToken($m[1]);

        } elseif (preg_match('/[₱]\s*(' . $big . ')/u', $withoutDate, $m)) {
            $this->aiBudgetMin = $this->aiBudgetMax = $this->parseMoneyToken($m[1]);

        } elseif (preg_match('/(' . $big . ')\s*(?:pesos?|php)\b/ui', $withoutDate, $m)) {
            $this->aiBudgetMin = $this->aiBudgetMax = $this->parseMoneyToken($m[1]);

        } elseif (preg_match('/\b(' . $big . ')\b/', $withoutDate, $m)) {
            $this->aiBudgetMin = $this->aiBudgetMax = $this->parseMoneyToken($m[1]);

        }

        $months = 'january|february|march|april|may|june|july|august|september|october|november|december|jan|feb|mar|apr|jun|jul|aug|sep|oct|nov|dec';
        $city   = '(?!(?:' . $months . ')\b)[A-Z][a-z]+(?: [A-Z][a-z]+){0,2}';

        $ambiguousTo = preg_match(
            '/\b(?:travel(?:l?ing)?\s+(?:to|in)|go(?:ing)?\s+(?:to|in)|visit(?:ing)?|fly(?:ing)?\s+to|heading\s+to|stay(?:ing)?\s+(?:in|at)|to|in|at)\s+(' . $city . ')\s+or\s+(' . $city . ')\b/u',
            $withoutDate
        );
        $ambiguousFrom = preg_match('/\bfrom\s+(' . $city . ')\s+or\s+(' . $city . ')\b/u', $withoutDate);

        $hasFrom = !$ambiguousFrom && preg_match('/\bfrom\s+(' . $city . ')\b/u', $withoutDate, $mf);
        $hasTo   = !$ambiguousTo && preg_match('/\b(?:travel(?:l?ing)?\s+(?:to|in)|go(?:ing)?\s+(?:to|in)|visit(?:ing)?|fly(?:ing)?\s+to|heading\s+to|stay(?:ing)?\s+(?:in|at)|to|in|at)\s+(' . $city . ')\b/u', $withoutDate, $mt);

        $hasTwoCities = !$ambiguousFrom && !$ambiguousTo
            && preg_match('/(' . $city . ')\s+to\s+(' . $city . ')/u', $withoutDate, $m2);

        // Every branch below resolves through knownPlaceName() before ever
        // touching aiFrom/aiTo — the capitalized-word shape ($city, above)
        // only means "this looks like a proper noun", not "this is a real
        // place". A raw trim($mt[1])-style assignment here was the actual
        // bug behind "Plan a trip to Nicsland" sailing straight past every
        // other check built today: it's properly capitalized, so it always
        // matched this exact pattern and got assigned with zero
        // verification, while any lowercase or off-pattern phrasing of the
        // same fake place fell through to the (already-gated) paths below
        // instead. A resolution failure here just leaves aiFrom/aiTo
        // untouched — missingSlotKey() then re-asks normally, same as any
        // other unresolved destination.
        if ($hasFrom && $hasTo) {
            $resolvedFrom = $this->knownPlaceName($this->cleanCityName(trim($mf[1])));
            $resolvedTo   = $this->knownPlaceName($this->cleanCityName(trim($mt[1])));
            if ($resolvedFrom !== '') $this->aiFrom = $resolvedFrom;
            if ($resolvedTo !== '')   $this->aiTo   = $resolvedTo;
        } elseif ($hasTwoCities) {
            $resolvedFrom = $this->knownPlaceName($this->cleanCityName(trim($m2[1])));
            $resolvedTo   = $this->knownPlaceName($this->cleanCityName(trim($m2[2])));
            if ($resolvedFrom !== '' && $resolvedTo !== '' && strtolower($resolvedFrom) !== strtolower($resolvedTo)) {
                $this->aiFrom = $resolvedFrom;
                $this->aiTo   = $resolvedTo;
            }
        } elseif ($hasFrom) {
            $resolved = $this->knownPlaceName($this->cleanCityName(trim($mf[1])));
            if ($resolved !== '') $this->aiFrom = $resolved;
        } elseif ($hasTo) {
            $resolved = $this->knownPlaceName($this->cleanCityName(trim($mt[1])));
            if ($resolved !== '') $this->aiTo = $resolved;
        }

        if ($ambiguousTo || $ambiguousFrom) {
            $this->ambiguityNotice = "Looks like you named more than one option there — could you tell me just the one you'd like to go with?";
        }

        $anyCase = '[A-Za-z]+(?: [A-Za-z]+){0,2}';

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

        if ($this->aiBudgetIsDaily && $this->aiDays > 0) {
            $this->aiBudgetMin *= $this->aiDays;
            $this->aiBudgetMax *= $this->aiDays;
            $this->aiBudgetIsDaily = false;
        }
    }

    private function generateAiPackage(): void
    {
        $dest    = strtolower($this->aiTo);
        $days    = max(1, $this->aiDays);
        $budget  = $this->aiBudgetMax ?: $this->aiBudgetMin ?: 30000;

        $lookup = [

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

        $data = null;
        foreach ($lookup as $key => $d) {
            if (str_contains($dest, $key)) { $data = $d; break; }
        }

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

        $travelers     = max(1, $this->aiTravelers);
        $transport     = (int) round($budget * 0.18);
        $accommodation = (int) round(($budget * 0.50 / $days)) * $days;
        $foodTotal     = $data['meal_day'] * $days * $travelers;
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
