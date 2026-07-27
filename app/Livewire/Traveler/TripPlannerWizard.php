<?php
namespace App\Livewire\Traveler;

use App\Models\Destination;
use App\Models\SavingsGoal;
use App\Models\Trip;
use App\Models\TripBudget;
use App\Services\SerpApiService;
use App\Services\SerperService;
use Carbon\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

#[Layout('layouts.app', ['active' => 'trips'])]
class TripPlannerWizard extends Component
{
    // ── Trips list view (shown when user already has trips) ─
    public bool   $showList      = false;
    public bool   $showEmpty     = false;
    public ?int   $tripToDelete  = null;

    // ── Navigation ─────────────────────────────────────────
    public int    $step     = 0;
    public string $planningMode = ''; // 'manual' | 'ai'

    // ── Step 1: trip details form (new) ───────────────────
    public string $manualFrom      = '';
    public string $manualTo        = '';
    public string $manualBudgetMin = '';
    public string $manualBudgetMax = '';
    public string $travelWith      = ''; // 'solo' | 'group'

    // ── Step 2: flight selection ───────────────────────────
    public array  $flightResults     = [];
    public bool   $flightLoading     = false;
    public string $flightError       = '';
    public string $flightTripType    = 'one_way'; // 'one_way' | 'round_trip' | 'multi_city'
    public ?array $selectedFlight    = null;
    // multi-city leg 2 (synced on search)
    public string $mcTo              = '';
    public string $mcStartDate       = '';
    public string $mcEndDate         = '';
    public array  $mcFlightResults   = [];
    public bool   $mcFlightLoading   = false;
    public bool   $mcFlightStep      = false; // true = showing leg 2 flight list
    public bool   $mcSearched        = false; // true after multi-city search fires
    public ?array $selectedMcFlight  = null;

    // ── Step 3: accommodation ──────────────────────────────
    public array  $hotelResults      = [];
    public bool   $hotelLoading      = false;
    public ?array $selectedHotel     = null;
    public int    $hotelGuests       = 1;
    public string $hotelType         = 'hotel'; // hotel | apartment | inn
    // multi-city leg 2 accommodation
    public bool   $mcHotelStep       = false;
    public array  $mcHotelResults    = [];
    public bool   $mcHotelLoading    = false;
    public ?array $selectedMcHotel   = null;

    // ── Step 4: food & dining ─────────────────────────────
    public array  $venueResults     = [];
    public bool   $venueLoading     = false;
    public ?array $selectedVenue    = null;
    public string $venueCategory    = 'All Cuisines';
    public bool   $mcVenueStep      = false;
    public array  $mcVenueResults   = [];
    public bool   $mcVenueLoading   = false;
    public ?array $selectedMcVenue  = null;

    // ── Step 5: attractions ────────────────────────────────
    public array  $attractionResults     = [];
    public bool   $attractionLoading     = false;
    public ?array $selectedAttraction    = null;
    public string $attractionType        = 'All Attractions';
    public bool   $mcAttractionStep      = false;
    public array  $mcAttractionResults   = [];
    public bool   $mcAttractionLoading   = false;
    public ?array $selectedMcAttraction  = null;

    // Step 8 — AI-generated itinerary
    public ?array $aiItinerary      = null;
    public bool   $aiLoading        = false;
    public bool   $showBudgetAdjust = false;
    public string $adjustBudgetMin  = '';
    public string $adjustBudgetMax  = '';

    // ── Step 3 (was 2): scope ─────────────────────────────
    public string $tripScope = '';

    // ── Step 3: destination ────────────────────────────────
    public ?int   $destinationId   = null;
    public string $destinationName = '';
    public string $destSearch      = '';

    // ── Step 4: calendar ───────────────────────────────────
    public string $startDate  = '';
    public string $endDate    = '';
    public int    $calYear;
    public int    $calMonth;

    // ── Step 4: group + budget ─────────────────────────────
    public string $groupType  = '';
    public int    $travelers  = 1;
    public string $budgetTier = '';

    // ── Step 5: editable cost categories ──────────────────
    public float  $transportation = 0;
    public float  $accommodation  = 0;
    public float  $food           = 0;
    public float  $attractions    = 0;
    public float  $shopping       = 0;
    public float  $emergency      = 0;
    public float  $budgetLimit    = 0;
    public string $editingCategory = '';

    // ── Cost rate tables (₱ per trip) ─────────────────────
    private const RATES = [
        'local' => [
            'Shoestring' => ['transport_base' => 2000,   'transport_daily' => 100,  'accommodation_night' => 800,   'food_day' => 350,  'attractions_day' => 150,  'shopping_per_person' => 800  ],
            'Mid-range'  => ['transport_base' => 8000,   'transport_daily' => 400,  'accommodation_night' => 3000,  'food_day' => 1200, 'attractions_day' => 600,  'shopping_per_person' => 3000 ],
            'Luxury'     => ['transport_base' => 20000,  'transport_daily' => 1000, 'accommodation_night' => 8000,  'food_day' => 3000, 'attractions_day' => 1500, 'shopping_per_person' => 10000],
        ],
        'international' => [
            'Shoestring' => ['transport_base' => 25000,  'transport_daily' => 500,  'accommodation_night' => 2000,  'food_day' => 1000, 'attractions_day' => 500,  'shopping_per_person' => 2000 ],
            'Mid-range'  => ['transport_base' => 55000,  'transport_daily' => 1000, 'accommodation_night' => 5000,  'food_day' => 2500, 'attractions_day' => 1500, 'shopping_per_person' => 7000 ],
            'Luxury'     => ['transport_base' => 150000, 'transport_daily' => 3000, 'accommodation_night' => 15000, 'food_day' => 6000, 'attractions_day' => 4000, 'shopping_per_person' => 25000],
        ],
    ];

    public function mount(): void
    {
        $this->calYear  = (int) date('Y');
        $this->calMonth = (int) date('n');
        // /trips/plan always starts the wizard fresh; /trips shows list or empty state
        $hasTrips = auth()->user()->trips()->exists();
        $isPlanRoute = request()->routeIs('trips.plan');
        $this->showList  = !$isPlanRoute && $hasTrips;
        $this->showEmpty = !$isPlanRoute && !$hasTrips;
    }

    public function startNewTrip(): mixed
    {
        return $this->redirect(route('trips.plan'), navigate: true);
    }

    public function startFromEmpty(): void
    {
        $this->showEmpty = false;
    }

    public function confirmDelete(int $id): void
    {
        $this->tripToDelete = $id;
    }

    public function cancelDelete(): void
    {
        $this->tripToDelete = null;
    }

    public function deleteTrip(): void
    {
        if (!$this->tripToDelete) return;
        $trip = Trip::where('id', $this->tripToDelete)->where('user_id', auth()->id())->firstOrFail();
        $trip->delete();
        $this->tripToDelete = null;
        if (!auth()->user()->trips()->exists()) {
            $this->showList = false;
            $this->step     = 1;
        }
    }

    public function getMyTripsProperty()
    {
        return auth()->user()->trips()
            ->withSum('budgets as total_estimated', 'estimated_cost')
            ->withSum('expenses as total_spent', 'amount')
            ->latest('start_date')
            ->get();
    }

    // ── Step 1 ─────────────────────────────────────────────
    public function selectPlanningMode(string $mode): void
    {
        $this->planningMode = $mode;
        $this->step = 1;
    }

    public static function staticIataCode(string $city): string
    {
        return (new self)->iataCode($city);
    }

    public function iataCode(string $city): string
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

        return $map[strtolower(trim($city))] ?? '';
    }

    private function resolveCode(string $city): string
    {
        $code = $this->iataCode($city);
        return $code !== '' ? $code : trim($city);
    }

    public function proceedFromTripDetails(): void
    {
        $this->validate([
            'manualFrom' => 'required|string|min:2',
            'manualTo'   => 'required|string|min:2',
        ], [
            'manualFrom.required' => 'Please enter your origin city.',
            'manualTo.required'   => 'Please enter your destination.',
        ]);

        if (strtolower(trim($this->manualFrom)) === strtolower(trim($this->manualTo))) {
            $this->addError('manualTo', 'Destination must be different from your origin city.');
            return;
        }

        // Parse single budget field: "30,000 - 50,000" or "30000" or "30,000 to 50,000"
        $raw = $this->manualBudgetMin;
        if (preg_match('/(\d[\d,]*)\s*(?:[-–to]+)\s*(\d[\d,]*)/i', $raw, $m)) {
            $this->manualBudgetMin = preg_replace('/[^\d]/', '', $m[1]);
            $this->manualBudgetMax = preg_replace('/[^\d]/', '', $m[2]);
        } else {
            $single = preg_replace('/[^\d]/', '', $raw);
            $this->manualBudgetMin = $single;
            $this->manualBudgetMax = $single;
        }

        $this->step = 2;
        $this->searchManualFlights();
    }

    public function searchManualFlights(): void
    {
        set_time_limit(120);
        $this->flightLoading   = true;
        $this->flightResults   = [];
        $this->flightError     = '';
        $this->mcFlightStep    = false;
        $this->mcSearched      = $this->flightTripType === 'multi_city' && !empty($this->mcTo);
        $this->mcFlightResults = [];
        $this->selectedFlight  = null;
        $this->selectedMcFlight = null;

        $serp      = new SerpApiService();
        $fromCode  = $this->resolveCode($this->manualFrom);
        $toCode    = $this->resolveCode($this->manualTo);

        if ($fromCode === $toCode || strtolower(trim($this->manualFrom)) === strtolower(trim($this->manualTo))) {
            $this->flightError   = 'Origin and destination cannot be the same city. Please choose a different destination.';
            $this->flightLoading = false;
            return;
        }
        $depart    = $this->startDate ?: date('Y-m-d', strtotime('+7 days'));
        $return    = $this->flightTripType === 'round_trip' && $this->endDate ? $this->endDate : '';

        try {
            $data = $serp->searchFlightsRaw($fromCode, $toCode, $depart, $return);
            if (empty($data)) {
                $serper = new SerperService();
                $data = $serper->searchFlights($fromCode, $toCode, $depart, $return);
            }
            $this->flightResults = $data ?? [];
        } catch (\Throwable $e) {
            try {
                $serper = new SerperService();
                $this->flightResults = $serper->searchFlights($fromCode, $toCode, $depart, $return) ?? [];
            } catch (\Throwable $e2) {
                $this->flightResults = [];
            }
        }

        $this->flightLoading = false;
    }

    public function selectFlight(int $index): void
    {
        $this->selectedFlight = $this->flightResults[$index] ?? null;

        // Multi-city: search leg 2 flights before going to accommodation
        if ($this->flightTripType === 'multi_city' && $this->mcTo && $this->mcStartDate) {
            $this->mcFlightStep    = true;
            $this->mcFlightResults = [];
            $this->mcFlightLoading = true;
            $serp      = new SerpApiService();
            $fromCode  = $this->resolveCode($this->manualTo);
            $toCode    = $this->resolveCode($this->mcTo);
            try {
                // Each multi-city leg is a one-way flight, not a round trip
                $data = $serp->searchFlightsRaw($fromCode, $toCode, $this->mcStartDate);
                if (empty($data)) {
                    $serper = new SerperService();
                    $data = $serper->searchFlights($fromCode, $toCode, $this->mcStartDate);
                }
                $this->mcFlightResults = $data ?? [];
            } catch (\Throwable $e) {
                try {
                    $serper = new SerperService();
                    $this->mcFlightResults = $serper->searchFlights($fromCode, $toCode, $this->mcStartDate) ?? [];
                } catch (\Throwable $e2) {
                    $this->mcFlightResults = [];
                }
            }
            $this->mcFlightLoading = false;
            return;
        }

        // Auto-detect scope from destination
        $intlKeywords = ['singapore','bangkok','phuket','bali','kuala lumpur','hong kong','tokyo','osaka','seoul','taipei','dubai','london','paris','new york','sydney','rome','barcelona','amsterdam','maldives','vietnam','hanoi','ho chi minh','jakarta','yangon','colombo','kathmandu','delhi','mumbai','beijing','shanghai','auckland'];
        $toLower = strtolower($this->manualTo);
        $isIntl  = false;
        foreach ($intlKeywords as $kw) {
            if (str_contains($toLower, $kw)) { $isIntl = true; break; }
        }
        $this->tripScope = $isIntl ? 'international' : 'local';
        $this->step = 3;
        $this->searchAccommodations();
    }

    public function selectMcFlight(int $index): void
    {
        $this->selectedMcFlight = $this->mcFlightResults[$index] ?? null;
        $this->mcFlightStep = false;

        $intlKeywords = ['singapore','bangkok','phuket','bali','kuala lumpur','hong kong','tokyo','osaka','seoul','taipei','dubai','london','paris','new york','sydney','rome','barcelona','amsterdam','maldives','vietnam','hanoi','ho chi minh','jakarta','yangon','colombo','kathmandu','delhi','mumbai','beijing','shanghai','auckland'];
        $toLower = strtolower($this->mcTo);
        $isIntl  = false;
        foreach ($intlKeywords as $kw) {
            if (str_contains($toLower, $kw)) { $isIntl = true; break; }
        }
        $this->tripScope = $isIntl ? 'international' : 'local';
        $this->step = 3;
        $this->searchAccommodations();
    }

    public function searchAccommodations(): void
    {
        set_time_limit(120);
        $serp = new SerpApiService();

        if ($this->mcHotelStep) {
            // Re-search second destination
            $this->mcHotelLoading = true;
            $this->mcHotelResults = [];
            $checkIn  = $this->mcStartDate ?: $this->startDate ?: date('Y-m-d', strtotime('+7 days'));
            $checkOut = $this->mcEndDate   ?: $this->endDate   ?: date('Y-m-d', strtotime('+12 days'));
            $nights   = max(1, \Carbon\Carbon::parse($checkIn)->diffInDays(\Carbon\Carbon::parse($checkOut)));
            try {
                $data = $serp->searchHotelsRaw($this->mcTo, $checkIn, $checkOut, $nights, $this->hotelType);
                if (empty($data)) {
                    $serper = new SerperService();
                    $data = $serper->searchHotels($this->mcTo, $checkIn, $checkOut, $nights, $this->hotelType);
                }
                $this->mcHotelResults = $data ?? [];
            } catch (\Throwable $e) {
                try {
                    $serper = new SerperService();
                    $this->mcHotelResults = $serper->searchHotels($this->mcTo, $checkIn, $checkOut, $nights, $this->hotelType) ?? [];
                } catch (\Throwable $e2) {
                    $this->mcHotelResults = [];
                }
            }
            $this->mcHotelLoading = false;
            return;
        }

        $this->hotelLoading    = true;
        $this->hotelResults    = [];
        $this->mcHotelResults  = [];
        $this->selectedHotel   = null;
        $this->selectedMcHotel = null;
        $checkIn  = $this->startDate ?: date('Y-m-d', strtotime('+7 days'));
        $checkOut = $this->endDate   ?: date('Y-m-d', strtotime('+12 days'));
        $nights   = max(1, \Carbon\Carbon::parse($checkIn)->diffInDays(\Carbon\Carbon::parse($checkOut)));
        try {
            $data = $serp->searchHotelsRaw($this->manualTo, $checkIn, $checkOut, $nights, $this->hotelType);
            if (empty($data)) {
                $serper = new SerperService();
                $data = $serper->searchHotels($this->manualTo, $checkIn, $checkOut, $nights, $this->hotelType);
            }
            $this->hotelResults = $data ?? [];
        } catch (\Throwable $e) {
            try {
                $serper = new SerperService();
                $this->hotelResults = $serper->searchHotels($this->manualTo, $checkIn, $checkOut, $nights, $this->hotelType) ?? [];
            } catch (\Throwable $e2) {
                $this->hotelResults = [];
            }
        }

        if (empty($this->hotelResults)) {
            $this->hotelResults = $this->fallbackHotels($this->manualTo, $nights, $this->hotelType);
        }

        $this->hotelLoading = false;
    }

    private function fallbackHotels(string $destination, int $nights, string $type = 'hotel'): array
    {
        $dest = $destination;
        $templates = [
            'hotel' => [
                ['name' => 'Grand %s Hotel',          'stars' => 4, 'nightly' => 3500],
                ['name' => '%s Crown Hotel',           'stars' => 4, 'nightly' => 2800],
                ['name' => 'Seda %s',                  'stars' => 5, 'nightly' => 6500],
                ['name' => 'Go Hotels %s',             'stars' => 3, 'nightly' => 1200],
                ['name' => '%s Bay Hotel',             'stars' => 3, 'nightly' => 1800],
                ['name' => 'Microtel by Wyndham %s',  'stars' => 3, 'nightly' => 1500],
                ['name' => 'Crimson Hotel %s',        'stars' => 5, 'nightly' => 7200],
                ['name' => '%s Suites Hotel',         'stars' => 3, 'nightly' => 2200],
            ],
            'apartment' => [
                ['name' => '%s Studio Apartments',    'stars' => 3, 'nightly' => 1800],
                ['name' => 'Garden Residences %s',    'stars' => 3, 'nightly' => 2200],
                ['name' => '%s Service Apartments',   'stars' => 4, 'nightly' => 3000],
                ['name' => 'City Flats %s',           'stars' => 3, 'nightly' => 1500],
            ],
            'inn' => [
                ['name' => 'Happy Inn %s',            'stars' => 2, 'nightly' => 800],
                ['name' => '%s Travelers Lodge',      'stars' => 2, 'nightly' => 700],
                ['name' => 'Cozy Inn %s',             'stars' => 2, 'nightly' => 900],
                ['name' => '%s Guesthouse',           'stars' => 2, 'nightly' => 650],
            ],
        ];
        $list = $templates[$type] ?? $templates['hotel'];
        $shortDest = explode(',', $dest)[0];

        return array_map(function ($t) use ($shortDest, $nights, $type) {
            $nightly = $t['nightly'];
            return [
                'name'      => sprintf($t['name'], $shortDest),
                'stars'     => $t['stars'],
                'image'     => null,
                'nightly'   => $nightly,
                'total'     => $nightly * $nights,
                'nights'    => $nights,
                'dist'      => null,
                'type'      => $type,
                'typeLabel' => ucfirst($type),
                'lat'       => null,
                'lng'       => null,
            ];
        }, $list);
    }

    public function skipAccommodation(): void
    {
        $this->selectedHotel   = null;
        $this->selectedMcHotel = null;
        $this->mcHotelStep     = false;
        $this->step = 4;
        $this->searchVenues();
    }

    public function selectAccommodation(int $index): void
    {
        $this->selectedHotel = $this->hotelResults[$index] ?? null;

        if ($this->flightTripType === 'multi_city' && $this->mcTo) {
            $this->mcHotelStep    = true;
            $this->mcHotelResults = [];
            $this->mcHotelLoading = true;
            $this->dispatch('searchMcHotels');
            return;
        }

        $this->step = 4;
        $this->searchVenues();
    }

    #[\Livewire\Attributes\On('searchMcHotels')]
    public function searchMcHotels(): void
    {
        set_time_limit(120);
        $checkIn  = $this->mcStartDate ?: $this->startDate ?: date('Y-m-d', strtotime('+7 days'));
        $checkOut = $this->mcEndDate   ?: $this->endDate   ?: date('Y-m-d', strtotime($checkIn . ' +5 days'));
        $nights   = max(1, \Carbon\Carbon::parse($checkIn)->diffInDays(\Carbon\Carbon::parse($checkOut)));
        $serp = new SerpApiService();
        try {
            $data = $serp->searchHotelsRaw($this->mcTo, $checkIn, $checkOut, $nights, $this->hotelType);
            if (empty($data)) {
                $serper = new SerperService();
                $data = $serper->searchHotels($this->mcTo, $checkIn, $checkOut, $nights, $this->hotelType);
            }
            $this->mcHotelResults = $data ?? [];
        } catch (\Throwable $e) {
            try {
                $serper = new SerperService();
                $this->mcHotelResults = $serper->searchHotels($this->mcTo, $checkIn, $checkOut, $nights, $this->hotelType) ?? [];
            } catch (\Throwable $e2) {
                $this->mcHotelResults = [];
            }
        }
        if (empty($this->mcHotelResults)) {
            $this->mcHotelResults = $this->fallbackHotels($this->mcTo, $nights, $this->hotelType);
        }
        $this->mcHotelLoading = false;
    }

    public function selectMcAccommodation(int $index): void
    {
        $this->selectedMcHotel = $this->mcHotelResults[$index] ?? null;
        $this->mcHotelStep = false;
        $this->step = 4;
        $this->searchVenues();
    }

    // ── Step 4: food & dining ──────────────────────────────
    public function searchVenues(): void
    {
        set_time_limit(60);
        $this->venueLoading        = true;
        $this->venueResults        = [];
        $this->mcVenueStep         = false;
        $this->mcVenueResults      = [];
        $this->selectedVenue       = null;
        $this->selectedMcVenue     = null;
        $this->mcAttractionStep    = false;
        $this->mcAttractionResults = [];
        $dest = $this->manualTo ?: $this->mcTo ?: '';
        if (!$dest) { $this->venueLoading = false; return; }
        $serp = new SerpApiService();
        try {
            $this->venueResults = $serp->searchRestaurantsRaw($dest, $this->venueCategory) ?? [];
            if (empty($this->venueResults)) {
                $serper = new SerperService();
                $this->venueResults = $serper->searchRestaurants($dest, $this->venueCategory) ?? [];
            }
        } catch (\Throwable $e) {
            try {
                $serper = new SerperService();
                $this->venueResults = $serper->searchRestaurants($dest, $this->venueCategory) ?? [];
            } catch (\Throwable $e2) {
                $this->venueResults = [];
            }
        }
        $this->venueLoading = false;
    }

    public function skipVenue(): void
    {
        $this->selectedVenue   = null;
        $this->selectedMcVenue = null;
        $this->mcVenueStep     = false;
        $this->step = 5;
        $this->searchAttractionsList();
    }

    public function selectVenue(int $index): void
    {
        if (!$this->mcVenueStep) {
            $this->selectedVenue = $this->venueResults[$index] ?? null;
            if ($this->flightTripType === 'multi_city' && $this->mcTo) {
                $this->mcVenueStep    = true;
                $this->mcVenueResults = [];
                $this->mcVenueLoading = true;
                $this->dispatch('searchMcVenues');
            } else {
                $this->step = 5;
                $this->searchAttractionsList();
            }
        } else {
            $this->selectedMcVenue = $this->mcVenueResults[$index] ?? null;
            $this->step = 5;
            $this->searchAttractionsList();
        }
    }

    #[On('searchMcVenues')]
    public function searchMcVenues(): void
    {
        set_time_limit(120);
        $serp = new SerpApiService();
        try {
            $this->mcVenueResults = $serp->searchRestaurantsRaw($this->mcTo, $this->venueCategory) ?? [];
            if (empty($this->mcVenueResults)) {
                $serper = new SerperService();
                $this->mcVenueResults = $serper->searchRestaurants($this->mcTo, $this->venueCategory) ?? [];
            }
        } catch (\Throwable) {
            try {
                $serper = new SerperService();
                $this->mcVenueResults = $serper->searchRestaurants($this->mcTo, $this->venueCategory) ?? [];
            } catch (\Throwable) {
                $this->mcVenueResults = [];
            }
        }
        $this->mcVenueLoading = false;
    }

    // ── Step 5: attractions ────────────────────────────────
    public function searchAttractionsList(): void
    {
        set_time_limit(60);
        $serp = new SerpApiService();

        if ($this->flightTripType !== 'multi_city' || !$this->mcTo) {
            $this->mcAttractionStep = false;
        }

        if ($this->mcAttractionStep) {
            $this->mcAttractionLoading = true;
            $this->mcAttractionResults = [];
            try {
                $this->mcAttractionResults = $serp->searchAttractionsRaw($this->mcTo, $this->attractionType) ?? [];
                if (empty($this->mcAttractionResults)) {
                    $serper = new SerperService();
                    $this->mcAttractionResults = $serper->searchAttractions($this->mcTo) ?? [];
                }
            } catch (\Throwable $e) {
                try {
                    $serper = new SerperService();
                    $this->mcAttractionResults = $serper->searchAttractions($this->mcTo) ?? [];
                } catch (\Throwable $e2) {
                    $this->mcAttractionResults = [];
                }
            }
            $this->mcAttractionLoading = false;
            return;
        }

        $this->attractionLoading    = true;
        $this->attractionResults    = [];
        $this->mcAttractionResults  = [];
        $this->selectedAttraction   = null;
        $this->selectedMcAttraction = null;
        $dest = $this->manualTo ?: $this->mcTo ?: '';
        if (!$dest) { $this->attractionLoading = false; return; }
        try {
            $this->attractionResults = $serp->searchAttractionsRaw($dest, $this->attractionType) ?? [];
            if (empty($this->attractionResults)) {
                $serper = new SerperService();
                $this->attractionResults = $serper->searchAttractions($dest) ?? [];
            }
        } catch (\Throwable $e) {
            try {
                $serper = new SerperService();
                $this->attractionResults = $serper->searchAttractions($dest) ?? [];
            } catch (\Throwable $e2) {
                $this->attractionResults = [];
            }
        }
        $this->attractionLoading = false;
    }

    public function skipAttraction(): void
    {
        $this->selectedAttraction   = null;
        $this->selectedMcAttraction = null;
        $this->mcAttractionStep     = false;
        $this->step = 6;
    }

    public function selectAttraction(int $index): void
    {
        if (!$this->mcAttractionStep) {
            $this->selectedAttraction = $this->attractionResults[$index] ?? null;
            if ($this->flightTripType === 'multi_city' && $this->mcTo) {
                $this->mcAttractionStep    = true;
                $this->mcAttractionResults = [];
                $this->mcAttractionLoading = true;
                $this->dispatch('searchMcAttractions');
            } else {
                $this->step = 6;
            }
        } else {
            $this->selectedMcAttraction = $this->mcAttractionResults[$index] ?? null;
            $this->step = 6;
        }
    }

    #[On('searchMcAttractions')]
    public function searchMcAttractions(): void
    {
        set_time_limit(120);
        $serp = new SerpApiService();
        try {
            $this->mcAttractionResults = $serp->searchAttractionsRaw($this->mcTo, $this->attractionType) ?? [];
        } catch (\Throwable) {
            $this->mcAttractionResults = [];
        }
        $this->mcAttractionLoading = false;
    }


    public function backToFlights(): void
    {
        $this->step = 2;
    }

    public function updatedFlightTripType(): void
    {
        if ($this->flightTripType !== 'multi_city') {
            $this->mcSearched   = false;
            $this->mcTo         = '';
            $this->mcStartDate  = '';
            $this->mcEndDate    = '';
            // flights
            $this->mcFlightResults  = [];
            $this->mcFlightStep     = false;
            $this->selectedMcFlight = null;
            // accommodation
            $this->mcHotelStep      = false;
            $this->mcHotelResults   = [];
            $this->mcHotelLoading   = false;
            $this->selectedMcHotel  = null;
            // food & dining
            $this->mcVenueStep      = false;
            $this->mcVenueResults   = [];
            $this->mcVenueLoading   = false;
            $this->selectedMcVenue  = null;
            // attractions
            $this->mcAttractionStep    = false;
            $this->mcAttractionResults = [];
            $this->mcAttractionLoading = false;
            $this->selectedMcAttraction = null;
        }
    }

    public function swapCities(): void
    {
        [$this->manualFrom, $this->manualTo] = [$this->manualTo, $this->manualFrom];
    }

    public function confirmEmergencyFund(): void
    {
        $this->step = 7;
    }

    public function generateItinerary(): void
    {
        $this->aiLoading   = true;
        $this->aiItinerary = null;
        $this->step        = 8;

        $profile       = auth()->user()?->userProfile;
        $profileBudget = (int) ($profile?->daily_budget ?? 0);
        $interests     = array_merge($profile?->interests ?? [], $profile?->sub_interests ?? []);

        $budMin  = $profileBudget > 0 ? $profileBudget : (int) preg_replace('/[^\d]/', '', $this->manualBudgetMin);
        $budMax  = (int) preg_replace('/[^\d]/', '', $this->manualBudgetMax ?: $this->manualBudgetMin);
        $budMax  = max(0, $budMax - (int) $this->emergency);
        if ($budMin >= $budMax) $budMin = (int) round($budMax * 0.8);
        $dest    = trim($this->manualTo ?: $this->mcTo ?: 'Unknown');

        // Calculate how much the traveler already spent on selections
        $selectionCost = 0;
        $selectionCost += (int) ($this->selectedFlight['price']    ?? 0);
        $selectionCost += (int) ($this->selectedMcFlight['price']  ?? 0);
        $selectionCost += (int) ($this->selectedHotel['total']     ?? 0);
        $selectionCost += (int) ($this->selectedMcHotel['total']   ?? 0);
        $selectionCost += (int) ($this->selectedVenue['priceMax']  ?? $this->selectedVenue['priceMin']   ?? 0);
        $selectionCost += (int) ($this->selectedMcVenue['priceMax'] ?? $this->selectedMcVenue['priceMin'] ?? 0);
        $selectionCost += $this->selectedAttraction['isFree']   ?? false ? 0 : (int) preg_replace('/[^\d]/', '', $this->selectedAttraction['price']   ?? '0');
        $selectionCost += $this->selectedMcAttraction['isFree'] ?? false ? 0 : (int) preg_replace('/[^\d]/', '', $this->selectedMcAttraction['price'] ?? '0');

        // AI budget = remaining after selections; ensure AI fills up to at least budMin
        $aiBudMin = max(0, $budMin - $selectionCost);
        $aiBudMax = max($aiBudMin + 500, $budMax - $selectionCost);

        $alreadySelected = array_filter([
            $this->selectedFlight['airline'] ?? null,
            $this->selectedHotel['name']     ?? null,
            $this->selectedVenue['name']     ?? null,
            $this->selectedAttraction['name'] ?? null,
            $this->selectedMcFlight['airline'] ?? null,
            $this->selectedMcHotel['name']   ?? null,
            $this->selectedMcVenue['name']   ?? null,
            $this->selectedMcAttraction['name'] ?? null,
        ]);

        $departTime = $this->selectedFlight['depart'] ?? $this->selectedMcFlight['depart'] ?? '';

        $args = [
            $dest,
            $this->startDate ?: now()->toDateString(),
            $this->endDate   ?: now()->toDateString(),
            $aiBudMin,
            $aiBudMax,
            $profileBudget,
            $interests,
            array_values($alreadySelected),
            null,
            $departTime,
        ];

        try {
            $result = (new \App\Services\GeminiService())->suggestAdditionalItinerary(...$args);
        } catch (\Throwable) {
            $result = null;
        }

        if (!$result) {
            try {
                $result = (new \App\Services\GroqService())->suggestAdditionalItinerary(...$args);
            } catch (\Throwable) {
                $result = null;
            }
        }

        $this->aiItinerary = $result;
        $this->aiLoading   = false;
    }

    public function openBudgetAdjust(): void
    {
        $this->adjustBudgetMin  = $this->manualBudgetMin;
        $this->adjustBudgetMax  = $this->manualBudgetMax ?: $this->manualBudgetMin;
        $this->showBudgetAdjust = true;
    }

    public function applyBudgetAdjust(): void
    {
        if ($this->adjustBudgetMin) $this->manualBudgetMin = preg_replace('/[^\d]/', '', $this->adjustBudgetMin);
        if ($this->adjustBudgetMax) $this->manualBudgetMax = preg_replace('/[^\d]/', '', $this->adjustBudgetMax);
        $this->showBudgetAdjust = false;
        $this->regenerateItinerary();
    }

    public function regenerateItinerary(): void
    {
        $this->showBudgetAdjust = false;

        $profile       = auth()->user()?->userProfile;
        $profileBudget = (int) ($profile?->daily_budget ?? 0);
        $interests     = array_merge($profile?->interests ?? [], $profile?->sub_interests ?? []);

        // Min = profile preferred budget; Max = trip total entered in wizard
        $budMin = $profileBudget > 0 ? $profileBudget : (int) preg_replace('/[^\d]/', '', $this->manualBudgetMin);
        $budMax = (int) preg_replace('/[^\d]/', '', $this->manualBudgetMax ?: $this->manualBudgetMin);
        $budMax = max(0, $budMax - (int) $this->emergency);
        if ($budMin >= $budMax) $budMin = (int) round($budMax * 0.8);
        $dest   = trim($this->manualTo ?: $this->mcTo ?: 'Unknown');

        // Subtract traveler selection costs to get remaining AI budget
        $selectionCost  = 0;
        $selectionCost += (int) ($this->selectedFlight['price']     ?? 0);
        $selectionCost += (int) ($this->selectedMcFlight['price']   ?? 0);
        $selectionCost += (int) ($this->selectedHotel['total']      ?? 0);
        $selectionCost += (int) ($this->selectedMcHotel['total']    ?? 0);
        $selectionCost += (int) ($this->selectedVenue['priceMax']   ?? $this->selectedVenue['priceMin']   ?? 0);
        $selectionCost += (int) ($this->selectedMcVenue['priceMax'] ?? $this->selectedMcVenue['priceMin'] ?? 0);
        $selectionCost += $this->selectedAttraction['isFree']   ?? false ? 0 : (int) preg_replace('/[^\d]/', '', $this->selectedAttraction['price']   ?? '0');
        $selectionCost += $this->selectedMcAttraction['isFree'] ?? false ? 0 : (int) preg_replace('/[^\d]/', '', $this->selectedMcAttraction['price'] ?? '0');

        $aiBudMin = max(0, $budMin - $selectionCost);
        $aiBudMax = max($aiBudMin + 500, $budMax - $selectionCost);

        $alreadySelected = array_values(array_filter([
            $this->selectedFlight['airline']        ?? null,
            $this->selectedHotel['name']            ?? null,
            $this->selectedVenue['name']            ?? null,
            $this->selectedAttraction['name']       ?? null,
            $this->selectedMcFlight['airline']      ?? null,
            $this->selectedMcHotel['name']          ?? null,
            $this->selectedMcVenue['name']          ?? null,
            $this->selectedMcAttraction['name']     ?? null,
        ]));

        // Check over/under budget against remaining AI budget
        $currentCost = 0;
        foreach ($this->aiItinerary['days'] ?? [] as $day) {
            foreach ($day['activities'] ?? [] as $act) {
                if (isset($act['cost']) && is_numeric($act['cost'])) $currentCost += (float) $act['cost'];
            }
        }
        $isOverBudget  = $aiBudMax > 0 && $currentCost > $aiBudMax;
        $isUnderBudget = $aiBudMin > 0 && $currentCost < $aiBudMin;

        $this->aiItinerary = null;
        $this->aiLoading   = true;

        if ($isOverBudget) {
            $constraint = "The previous itinerary exceeded the remaining activity budget. You MUST suggest cheaper activities. Total cost MUST be between ₱{$aiBudMin} and ₱{$aiBudMax}. Do NOT exceed ₱{$aiBudMax}.";
        } elseif ($isUnderBudget) {
            $constraint = "The previous itinerary was under budget. Suggest higher-quality experiences, premium dining, guided tours, and notable attractions to bring the total closer to ₱{$aiBudMax}. Total MUST be between ₱{$aiBudMin} and ₱{$aiBudMax}.";
        } else {
            $varieties  = ['Focus on hidden gems.', 'Mix cultural, culinary, and outdoor experiences.', 'Focus on authentic local experiences.', 'Include wellness and evening entertainment.', 'Prioritize unique local tours.'];
            $constraint = "Keep total cost between ₱{$aiBudMin} and ₱{$aiBudMax}. " . $varieties[array_rand($varieties)];
        }

        $departTime = $this->selectedFlight['depart'] ?? $this->selectedMcFlight['depart'] ?? '';

        $args = [
            $dest,
            $this->startDate ?: now()->toDateString(),
            $this->endDate   ?: now()->toDateString(),
            $aiBudMin, $aiBudMax, $profileBudget,
            $interests,
            $alreadySelected,
            $constraint,
            $departTime,
        ];

        $result = null;
        try { $result = (new \App\Services\GeminiService())->suggestAdditionalItinerary(...$args); } catch (\Throwable) {}
        if (!$result) {
            try { $result = (new \App\Services\GroqService())->suggestAdditionalItinerary(...$args); } catch (\Throwable) {}
        }

        $this->aiItinerary = $result;
        $this->aiLoading   = false;
    }

    public function goToSummary(): void
    {
        $this->step = 9;
    }

    public function saveItinerary(): void
    {
        $rawBudget = $this->manualBudgetMax ?: $this->manualBudgetMin;
        $budget    = (float) preg_replace('/[^\d.]/', '', $rawBudget);
        $tripStart = $this->startDate ?: now()->toDateString();
        $tripEnd   = $this->endDate   ?: now()->toDateString();

        // Calculate total cost for saved trips display
        $flightCost  = (int) ($this->selectedFlight['price']   ?? $this->selectedMcFlight['price']   ?? 0);
        $hotelCost   = (int) ($this->selectedHotel['total']    ?? $this->selectedMcHotel['total']    ?? 0);
        $venueCost   = (int) ($this->selectedVenue['priceMax'] ?? $this->selectedVenue['priceMin']   ?? $this->selectedMcVenue['priceMax'] ?? $this->selectedMcVenue['priceMin'] ?? 0);
        $attrCost    = ($this->selectedAttraction['isFree']   ?? false ? 0 : (int) preg_replace('/[^\d]/', '', $this->selectedAttraction['price']   ?? '0'))
                     + ($this->selectedMcAttraction['isFree'] ?? false ? 0 : (int) preg_replace('/[^\d]/', '', $this->selectedMcAttraction['price'] ?? '0'));
        $aiCost      = 0;
        if ($this->aiItinerary && !empty($this->aiItinerary['days'])) {
            foreach ($this->aiItinerary['days'] as $day) {
                foreach ($day['activities'] ?? [] as $act) { $aiCost += (int)($act['cost'] ?? 0); }
            }
        }
        $totalCost   = $flightCost + $hotelCost + $venueCost + $attrCost + $aiCost + (float)($this->emergency ?? 0);

        $coverImage  = $this->selectedHotel['image']   ?? $this->selectedMcHotel['image']
                    ?? $this->selectedAttraction['image'] ?? $this->selectedMcAttraction['image'] ?? null;

        // Build summary for modal display
        $fromCode = $this->selectedFlight['dep_id'] ?? $this->selectedMcFlight['dep_id'] ?? 'MNL';
        $toCode   = $this->selectedFlight['arr_id'] ?? $this->selectedMcFlight['arr_id'] ?? '';
        $airline  = $this->selectedFlight['airline'] ?? $this->selectedMcFlight['airline'] ?? 'Flight';
        $flightDetail = $airline . ' · Round-trip flight (' . $fromCode . ' - ' . $toCode . ' | ' . $toCode . ' - ' . $fromCode . ')';

        $nights      = $this->selectedHotel ? (int)($this->selectedHotel['nights'] ?? 1) : (int)($this->selectedMcHotel['nights'] ?? 1);
        $hotelName   = $this->selectedHotel['name'] ?? $this->selectedMcHotel['name'] ?? null;
        $hotelDetail = $hotelName ? $nights . ' night' . ($nights !== 1 ? 's' : '') . ' at ' . $hotelName : null;

        $venueName   = $this->selectedVenue['name'] ?? $this->selectedMcVenue['name'] ?? null;
        $venueCuisine = $this->selectedVenue['cuisine'] ?? $this->selectedMcVenue['cuisine'] ?? null;
        $venueDetail = $venueName ? ($venueCuisine ? $venueName . ' · ' . $venueCuisine : $venueName) : null;

        $attrNames = array_filter([
            $this->selectedAttraction['name']   ?? null,
            $this->selectedMcAttraction['name'] ?? null,
        ]);
        $attrDetail = $attrNames ? implode(' & ', $attrNames) : null;

        $summaryData = [
            'transportation' => ['detail' => $flightDetail,                              'cost' => $flightCost],
            'accommodation'  => ['detail' => $hotelDetail,                               'cost' => $hotelCost],
            'food'           => ['detail' => $venueDetail,                               'cost' => $venueCost],
            'attractions'    => ['detail' => $attrDetail,                                'cost' => $attrCost + $aiCost],
            'emergency_fund' => ['detail' => 'Safety buffer for unexpected costs',       'cost' => (int)($this->emergency ?? 0)],
        ];

        $trip = Trip::create([
            'user_id'          => auth()->id(),
            'destination'      => trim($this->manualTo ?: $this->mcTo ?: 'Unknown'),
            'start_date'       => $tripStart,
            'end_date'         => $tripEnd,
            'budget_limit'     => $budget ?: 0,
            'travel_type'      => 'Solo',
            'num_travelers'    => 1,
            'total_cost'       => $totalCost,
            'cover_image'      => $coverImage,
            'summary_data'     => $summaryData,
            'origin'           => trim($this->manualFrom ?: $this->mcFrom ?: 'Manila'),
            'origin_code'      => $fromCode,
            'destination_code' => $toCode,
        ]);

        // Spread traveler selections across travel dates
        $day1Date  = \Carbon\Carbon::parse($tripStart);
        $lastDate  = \Carbon\Carbon::parse($tripEnd);
        $totalDays = max(1, (int) $day1Date->diffInDays($lastDate) + 1);
        $destLabel = trim($this->manualTo ?: $this->mcTo ?: '');
        $origLabel = trim($this->manualFrom ?: $this->mcFrom ?: 'Manila');

        $flight  = $this->selectedFlight    ?? $this->selectedMcFlight    ?? null;
        $hotel   = $this->selectedHotel     ?? $this->selectedMcHotel     ?? null;
        $venue   = $this->selectedVenue     ?? $this->selectedMcVenue     ?? null;
        $attr1   = $this->selectedAttraction   ?? null;
        $attr2   = $this->selectedMcAttraction ?? null;
        $isRound = $flight && strtolower($flight['type'] ?? '') === 'round trip';

        // Day 1 — Arrival: flight in, hotel check-in
        if ($flight) {
            $trip->itinerary()->create([
                'title'          => ($flight['airline'] ?? 'Flight') . ' arrival to ' . $destLabel,
                'type'           => 'Flight',
                'start_datetime' => $day1Date->copy()->setTimeFromTimeString('10:00'),
                'end_datetime'   => $day1Date->copy()->setTimeFromTimeString('12:00'),
                'location'       => $destLabel,
                'notes'          => $flight['number'] ?? null,
            ]);
        }
        if ($hotel) {
            $trip->itinerary()->create([
                'title'          => 'Check-in at ' . ($hotel['name'] ?? 'Hotel'),
                'type'           => 'Hotel',
                'start_datetime' => $day1Date->copy()->setTimeFromTimeString('14:00'),
                'end_datetime'   => $day1Date->copy()->setTimeFromTimeString('15:00'),
                'location'       => $destLabel,
                'notes'          => null,
            ]);
        }

        // Day 2 (or Day 1 if 1-day trip) — Venue & Attractions
        $actDay = $totalDays >= 2 ? $day1Date->copy()->addDay() : $day1Date->copy();
        if ($attr1) {
            $trip->itinerary()->create([
                'title'          => 'Visit ' . ($attr1['name'] ?? 'Attraction'),
                'type'           => 'Activity',
                'start_datetime' => $actDay->copy()->setTimeFromTimeString('09:00'),
                'end_datetime'   => $actDay->copy()->setTimeFromTimeString('11:00'),
                'location'       => $destLabel,
                'notes'          => null,
            ]);
        }
        if ($venue) {
            $trip->itinerary()->create([
                'title'          => 'Lunch at ' . ($venue['name'] ?? 'Restaurant'),
                'type'           => 'Activity',
                'start_datetime' => $actDay->copy()->setTimeFromTimeString('12:00'),
                'end_datetime'   => $actDay->copy()->setTimeFromTimeString('13:30'),
                'location'       => $destLabel,
                'notes'          => $venue['cuisine'] ?? null,
            ]);
        }
        if ($attr2) {
            $trip->itinerary()->create([
                'title'          => 'Visit ' . ($attr2['name'] ?? 'Attraction'),
                'type'           => 'Activity',
                'start_datetime' => $actDay->copy()->setTimeFromTimeString('15:00'),
                'end_datetime'   => $actDay->copy()->setTimeFromTimeString('17:00'),
                'location'       => $destLabel,
                'notes'          => null,
            ]);
        }

        // Last day — Hotel checkout + return flight
        if ($totalDays >= 2) {
            if ($hotel) {
                $trip->itinerary()->create([
                    'title'          => 'Check-out from ' . ($hotel['name'] ?? 'Hotel'),
                    'type'           => 'Hotel',
                    'start_datetime' => $lastDate->copy()->setTimeFromTimeString('10:00'),
                    'end_datetime'   => $lastDate->copy()->setTimeFromTimeString('11:00'),
                    'location'       => $destLabel,
                    'notes'          => null,
                ]);
            }
            if ($flight && $isRound) {
                $trip->itinerary()->create([
                    'title'          => ($flight['airline'] ?? 'Flight') . ' departure to ' . $origLabel,
                    'type'           => 'Flight',
                    'start_datetime' => $lastDate->copy()->setTimeFromTimeString('13:00'),
                    'end_datetime'   => $lastDate->copy()->setTimeFromTimeString('15:00'),
                    'location'       => $origLabel,
                    'notes'          => $flight['number'] ?? null,
                ]);
            }
        }

        // Save AI-generated itinerary days
        // If selections took Day 2, AI starts Day 3; otherwise Day 2
        $aiDayOffset = ($totalDays >= 2 && ($attr1 || $attr2 || $venue)) ? 2 : 1;
        if ($this->aiItinerary && !empty($this->aiItinerary['days'])) {
            foreach ($this->aiItinerary['days'] as $i => $day) {
                $dayDate = \Carbon\Carbon::parse($tripStart)->addDays($i + $aiDayOffset);
                if ($dayDate->gt($lastDate)) break; // don't write past trip end
                foreach ($day['activities'] ?? [] as $act) {
                    $timeStr = $act['time'] ?? '09:00 AM';
                    try { $t = \Carbon\Carbon::parse($dayDate->toDateString() . ' ' . $timeStr); } catch (\Throwable) { $t = $dayDate->copy()->setTimeFromTimeString('09:00'); }
                    $actType = match(strtolower($act['type'] ?? '')) {
                        'transport', 'transportation', 'flight' => 'Transportation',
                        'hotel', 'accommodation'                => 'Hotel',
                        default                                 => 'Activity',
                    };
                    $trip->itinerary()->create([
                        'title'          => $act['title'] ?? 'Activity',
                        'type'           => $actType,
                        'start_datetime' => $t,
                        'end_datetime'   => $t->copy()->addHour(),
                        'location'       => trim($this->manualTo ?: $this->mcTo ?: ''),
                        'notes'          => $act['description'] ?? null,
                    ]);
                }
            }
        }

        $this->redirect(route('saved-trips'));
    }

    public function saveDraft(): void
    {
        Trip::create([
            'user_id'      => auth()->id(),
            'destination'  => $this->manualTo ?: 'Draft',
            'start_date'   => $this->startDate ?: now()->toDateString(),
            'end_date'     => $this->endDate   ?: now()->toDateString(),
            'budget_limit' => $this->parseBudgetInput($this->manualBudgetMax ?: $this->manualBudgetMin),
            'travel_type'  => 'Solo',
            'num_travelers'=> 1,
        ]);
        session()->flash('success', 'Draft saved!');
        $this->redirect(route('trips.plan'), navigate: true);
    }

    private function parseBudgetInput(string $val): float
    {
        return (float) preg_replace('/[^\d.]/', '', $val);
    }

    public function selectScope(string $scope): void
    {
        $this->tripScope = $scope;
        $this->step = 4;
    }

    // ── Step 4 ─────────────────────────────────────────────
    public function selectDestination(int $id): void
    {
        $dest = Destination::findOrFail($id);
        $this->destinationId   = $dest->id;
        $this->destinationName = $dest->name;
        $this->step = 5;
    }

    // ── Step 4: calendar ───────────────────────────────────
    public function prevMonth(): void
    {
        if ($this->calMonth === 1) { $this->calMonth = 12; $this->calYear--; }
        else $this->calMonth--;
    }

    public function nextMonth(): void
    {
        if ($this->calMonth === 12) { $this->calMonth = 1; $this->calYear++; }
        else $this->calMonth++;
    }

    public function selectDay(string $date): void
    {
        if ($date < date('Y-m-d')) return;

        if (!$this->startDate || ($this->startDate && $this->endDate)) {
            $this->startDate = $date;
            $this->endDate   = '';
        } elseif ($date < $this->startDate) {
            $this->endDate   = $this->startDate;
            $this->startDate = $date;
        } else {
            $this->endDate = $date;
        }
    }

    public function proceedFromCalendar(): void
    {
        if (empty($this->startDate)) {
            $this->dispatch('calendar-validation-error');
            return;
        }

        if (empty($this->endDate)) {
            $this->endDate = $this->startDate;
        }

        $this->step = 6;
    }

    // ── Step 6: group + budget ─────────────────────────────
    public function selectGroup(string $group): void
    {
        if ($this->groupType === $group) {
            $this->groupType = '';
            $this->travelers = 1;
            return;
        }
        $this->groupType = $group;
        $this->travelers = match ($group) {
            'Solo'   => 1,
            'Couple' => 2,
            default  => max($this->travelers, 2),
        };
    }

    public function incrementTravelers(): void
    {
        if ($this->travelers < 20) $this->travelers++;
    }

    public function decrementTravelers(): void
    {
        $min = in_array($this->groupType, ['Family', 'Friends']) ? 2 : 1;
        if ($this->travelers > $min) $this->travelers--;
    }

    public function selectBudgetTier(string $tier): void
    {
        $this->budgetTier = $this->budgetTier === $tier ? '' : $tier;
    }

    public function calculateAndProceed(): void
    {
        set_time_limit(120);
        $missingGroup  = empty($this->groupType);
        $missingBudget = empty($this->budgetTier);

        if ($missingGroup || $missingBudget) {
            $this->dispatch('validation-error',
                missingGroup:  $missingGroup,
                missingBudget: $missingBudget,
            );
            return;
        }

        $this->calculateEstimate();
        $this->step = 7;
    }

    // ── Step 7: inline category editing ───────────────────
    public function startEditing(string $category): void
    {
        $this->editingCategory = $category;
    }

    public function stopEditing(): void
    {
        $this->editingCategory = '';
        $subtotal = $this->transportation + $this->accommodation + $this->food
                  + $this->attractions + $this->shopping;
        $this->emergency   = round($subtotal * 0.05, 2);
        $this->budgetLimit = round($subtotal + $this->emergency, 2);
    }

    // ── Confirm ────────────────────────────────────────────
    public function confirm(): mixed
    {
        $this->validate([
            'destinationName' => 'required|string',
            'startDate'       => 'required|date',
            'endDate'         => 'required|date|after_or_equal:startDate',
            'groupType'       => 'required|in:Solo,Couple,Family,Friends',
            'budgetTier'      => 'required|in:Shoestring,Mid-range,Luxury',
            'budgetLimit'     => 'required|numeric|min:1',
        ]);

        $trip = Trip::create([
            'user_id'       => auth()->id(),
            'destination'   => $this->destinationName,
            'start_date'    => $this->startDate,
            'end_date'      => $this->endDate,
            'num_travelers' => $this->travelers,
            'budget_limit'  => $this->budgetLimit,
            'travel_type'   => $this->groupType,
            'notes'         => "Budget tier: {$this->budgetTier}; Scope: {$this->tripScope}",
        ]);

        foreach ([
            'Transportation'      => $this->transportation,
            'Accommodation'       => $this->accommodation,
            'Food'                => $this->food,
            'Tourist Attractions' => $this->attractions,
            'Shopping'            => $this->shopping,
            'Emergency Funds'     => $this->emergency,
        ] as $cat => $amount) {
            TripBudget::create([
                'trip_id'        => $trip->id,
                'category'       => $cat,
                'estimated_cost' => $amount,
                'actual_spent'   => 0,
            ]);
        }

        SavingsGoal::create([
            'user_id'         => auth()->id(),
            'trip_id'         => $trip->id,
            'goal_name'       => $this->destinationName . ' Trip',
            'target_amount'   => $this->budgetLimit,
            'current_savings' => 0,
            'deadline'        => $this->startDate,
        ]);

        return $this->redirect(route('itinerary.index') . '?trip_id=' . $trip->id, navigate: true);
    }

    // ── Computed properties ────────────────────────────────
    #[Computed]
    public function destinations()
    {
        $userCountry = auth()->user()->country ?? 'Philippines';
        $query = Destination::orderBy('name');
        if ($this->tripScope === 'local') {
            $query->where('country', $userCountry);
        } else {
            $query->where('country', '!=', $userCountry);
        }
        if ($this->destSearch) {
            $query->where('name', 'like', "%{$this->destSearch}%");
        }
        return $query->get();
    }

    public function getDaysProperty(): int
    {
        if (!$this->startDate || !$this->endDate) return 0;
        return max(1, (int) Carbon::parse($this->startDate)->diffInDays($this->endDate));
    }

    public function getCalendarDaysProperty(): array
    {
        $first    = Carbon::createFromDate($this->calYear, $this->calMonth, 1);
        $total    = $first->daysInMonth;
        $startDow = $first->dayOfWeek;
        $today    = date('Y-m-d');

        $days = array_fill(0, $startDow, null);
        for ($d = 1; $d <= $total; $d++) {
            $date   = sprintf('%04d-%02d-%02d', $this->calYear, $this->calMonth, $d);
            $days[] = [
                'day'     => $d,
                'date'    => $date,
                'isPast'  => $date < $today,
                'isToday' => $date === $today,
                'isStart' => $date === $this->startDate,
                'isEnd'   => $date === $this->endDate,
                'inRange' => $this->startDate && $this->endDate
                             && $date > $this->startDate && $date < $this->endDate,
            ];
        }
        return $days;
    }

    public function getComfortLevelProperty(): string
    {
        return match ($this->budgetTier) {
            'Shoestring' => 'BUDGET',
            'Luxury'     => 'PREMIUM',
            default      => 'STANDARD',
        };
    }

    public function getSmartTipProperty(): string
    {
        return match ($this->budgetTier) {
            'Shoestring' => 'Opt for hostels, local street food, and free attractions to stretch your funds further.',
            'Luxury'     => 'Consider hiring a private guide for exclusive access and a personalized premium experience.',
            default      => 'Book accommodation 2+ weeks early to unlock better rates and ensure availability.',
        };
    }

    public function getVarianceProperty(): float
    {
        return round($this->budgetLimit * 0.05, 2);
    }

    public function getTravelersLabelProperty(): string
    {
        return $this->travelers . ' ' . match ($this->groupType) {
            'Family'  => ($this->travelers === 1 ? 'Person' : 'People'),
            'Friends' => ($this->travelers === 1 ? 'Person' : 'People'),
            'Couple'  => 'Adults',
            default   => 'Adult',
        };
    }

    private function calculateEstimate(): void
    {
        $days  = $this->getDaysProperty();
        $n     = $this->travelers;
        $scope = $this->tripScope ?: 'local';
        $r     = self::RATES[$scope][$this->budgetTier] ?? self::RATES['local']['Mid-range'];
        $rooms = max(1, (int) ceil($n / 2));

        $this->transportation = round(($r['transport_base'] + $r['transport_daily'] * $days) * $n, 2);
        $this->accommodation  = round($r['accommodation_night'] * $days * $rooms, 2);
        $this->food           = round($r['food_day'] * $days * $n, 2);
        $this->attractions    = round($r['attractions_day'] * $days * $n, 2);
        $this->shopping       = round($r['shopping_per_person'] * $n, 2);

        $subtotal = $this->transportation + $this->accommodation + $this->food
                  + $this->attractions + $this->shopping;
        $this->emergency   = round($subtotal * 0.05, 2);
        $this->budgetLimit = round($subtotal + $this->emergency, 2);
    }

    public function render()
    {
        return view('livewire.traveler.trip-planner-wizard');
    }
}
