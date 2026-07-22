<div style="display:flex;flex-direction:column;flex:1;min-height:0;">

@if ($this->trips->isEmpty())
{{-- Empty state --}}
@php $isMoments = $tab === 'moments'; @endphp
<div class="empty-state-center" style="min-height:80vh;">
    <div style="width:64px;height:64px;border-radius:16px;background:#934B19;display:flex;align-items:center;justify-content:center;margin-bottom:24px;">
        @if ($isMoments)
        <i class="fa-regular fa-images" style="font-size:28px;color:#fff;"></i>
        @else
        <i class="fa-solid fa-calendar-days" style="font-size:28px;color:#fff;"></i>
        @endif
    </div>
    <h2 style="font-weight:700;font-size:22px;margin-bottom:10px;color:#1A0A00;">{{ $isMoments ? 'No moments yet' : 'No itineraries yet' }}</h2>
    <p style="color:#9B8EA0;margin-bottom:28px;font-size:14px;max-width:320px;line-height:1.6;">{{ $isMoments ? 'Plan a trip first to add moments for your destinations.' : 'Plan a trip first to see the itineraries for your destinations.' }}</p>
    <a href="{{ route('trips.plan') }}" style="display:inline-flex;align-items:center;gap:10px;background:#934B19;color:#fff;border-radius:30px;padding:14px 32px;font-size:13px;font-weight:700;letter-spacing:.06em;text-decoration:none;text-transform:uppercase;">
        <i class="fa-solid fa-plane"></i> Plan Your First Trip
    </a>
</div>

@else
{{-- Destination selector + calendar --}}
<div style="flex:1;display:flex;align-items:center;justify-content:center;">
<div style="width:100%;max-width:720px;">

    {{-- Destination dropdown --}}
    @php
    $iataToCity = [
        'MNL'=>'Manila','CEB'=>'Cebu','IAO'=>'Siargao','PPS'=>'Puerto Princesa',
        'DVO'=>'Davao','ILO'=>'Iloilo','BCD'=>'Bacolod','TAG'=>'Tagbilaran',
        'GES'=>'General Santos','CBO'=>'Cotabato','ZAM'=>'Zamboanga',
        'KLO'=>'Kalibo','MPH'=>'Malay','RXS'=>'Roxas','TAC'=>'Tacloban',
        'SIN'=>'Singapore','KUL'=>'Kuala Lumpur','BKK'=>'Bangkok','HKG'=>'Hong Kong',
        'NRT'=>'Tokyo','ICN'=>'Seoul','HND'=>'Tokyo','KIX'=>'Osaka',
        'SYD'=>'Sydney','MEL'=>'Melbourne','LAX'=>'Los Angeles','JFK'=>'New York',
        'DXB'=>'Dubai','CDG'=>'Paris','LHR'=>'London','FCO'=>'Rome',
        'BCN'=>'Barcelona','AMS'=>'Amsterdam','HAN'=>'Hanoi','SGN'=>'Ho Chi Minh',
        'DPS'=>'Bali','CGK'=>'Jakarta','MLE'=>'Maldives',
    ];
    $tripLabel = fn($t) => ($t->origin ?? 'Manila') . ' to ' . ($iataToCity[$t->destination_code ?? ''] ?? $t->destination);
    @endphp
    <div style="margin-bottom:20px;">
        <div style="font-size:10px;font-weight:700;letter-spacing:.1em;color:var(--primary);text-transform:uppercase;margin-bottom:6px;">Destination</div>
        @if ($this->trips->count() === 1)
        @php $onlyTrip = $this->trips->first(); @endphp
        <div style="background:#fff;border:1.5px solid var(--border);border-radius:12px;padding:13px 16px;display:flex;align-items:center;gap:10px;">
            <i class="fa-solid fa-plane" style="color:#C8874A;font-size:13px;flex-shrink:0;"></i>
            <span style="font-size:14px;font-weight:600;color:var(--dark);">{{ $tripLabel($onlyTrip) }}</span>
        </div>
        @else
        <div x-data="{ open: false }" style="position:relative;">
            {{-- Trigger --}}
            <button @click="open = !open" @click.away="open = false" type="button"
                    style="width:100%;background:#fff;border:1.5px solid var(--border);border-radius:12px;padding:13px 16px;display:flex;align-items:center;gap:10px;cursor:pointer;text-align:left;">
                <i class="fa-solid fa-plane" style="color:#C8874A;font-size:13px;flex-shrink:0;"></i>
                <span style="flex:1;font-size:14px;font-weight:600;color:var(--dark);">
                    @foreach($this->trips as $t)
                        @if($selectedTripId == $t->id){{ $tripLabel($t) }}@endif
                    @endforeach
                </span>
                <i class="fa-solid fa-chevron-down" style="font-size:11px;color:#9B8EA0;transition:transform .2s;" :style="open ? 'transform:rotate(180deg)' : ''"></i>
            </button>
            {{-- Options --}}
            <div x-show="open" x-transition
                 style="position:absolute;top:calc(100% + 6px);left:0;right:0;background:#fff;border:1.5px solid var(--border);border-radius:12px;box-shadow:0 8px 24px rgba(45,27,20,.12);z-index:50;overflow:hidden;">
                @foreach($this->trips as $t)
                <button type="button"
                        wire:click="$set('selectedTripId', {{ $t->id }})"
                        @click="open = false"
                        style="width:100%;background:{{ $selectedTripId == $t->id ? '#FDF3EB' : '#fff' }};border:none;padding:12px 16px;display:flex;align-items:center;gap:10px;cursor:pointer;text-align:left;border-bottom:1px solid #f5f0eb;"
                        onmouseenter="this.style.background='#f5f0eb'" onmouseleave="this.style.background='{{ $selectedTripId == $t->id ? '#FDF3EB' : '#fff' }}'">
                    <i class="fa-solid fa-plane" style="color:#C8874A;font-size:12px;flex-shrink:0;"></i>
                    <span style="font-size:13px;font-weight:{{ $selectedTripId == $t->id ? '700' : '500' }};color:{{ $selectedTripId == $t->id ? '#934b19' : 'var(--dark)' }};">{{ $tripLabel($t) }}</span>
                </button>
                @endforeach
            </div>
        </div>
        @endif
    </div>

    @if ($selectedTripId && $this->selectedTrip)
    @php
        $trip      = $this->selectedTrip;
        $tripStart = $trip->start_date->copy()->startOfDay();
        $tripEnd   = $trip->end_date->copy()->startOfDay();
        $startStr  = $tripStart->toDateString();
        $endStr    = $tripEnd->toDateString();

        $typeToMs = [
            // Title-case (from wizard steps)
            'Flight'          => 'flight_takeoff',
            'Accommodation'   => 'hotel',
            'Hotel'           => 'hotel',
            'Food & Dining'   => 'restaurant',
            'Dining'          => 'restaurant',
            'Activity'        => 'explore',
            'Transport'       => 'directions_car',
            'Transportation'  => 'directions_car',
            'Attraction'      => 'photo_camera',
            'Shopping'        => 'shopping_bag',
            // AI lowercase types
            'flight'          => 'flight_takeoff',
            'food'            => 'restaurant',
            'restaurant'      => 'restaurant',
            'attraction'      => 'photo_camera',
            'leisure'         => 'local_activity',
            'transport'       => 'directions_car',
            'nature'          => 'forest',
            'beach'           => 'beach_access',
            'culture'         => 'account_balance',
            'adventure'       => 'hiking',
            'nightlife'       => 'nightlife',
            'spa'             => 'spa',
            'shopping'        => 'shopping_bag',
        ];

        // Title keyword → MS icon (used when type is generic 'Activity')
        $titleKeywords = [
            'breakfast'     => 'free_breakfast',
            'lunch'         => 'lunch_dining',
            'dinner'        => 'dinner_dining',
            'snack'         => 'bakery_dining',
            'food'          => 'restaurant',
            'restaurant'    => 'restaurant',
            'cafe'          => 'local_cafe',
            'coffee'        => 'local_cafe',
            'bakery'        => 'bakery_dining',
            'hike'          => 'hiking',
            'hiking'        => 'hiking',
            'island'        => 'beach_access',
            'beach'         => 'beach_access',
            'snorkel'       => 'scuba_diving',
            'dive'          => 'scuba_diving',
            'lagoon'        => 'water',
            'waterfall'     => 'waterfall',
            'cave'          => 'landscape',
            'pool'          => 'pool',
            'surf'          => 'surfing',
            'tour'          => 'tour',
            'visit'         => 'photo_camera',
            'view'          => 'landscape',
            'sunset'        => 'wb_twilight',
            'sunrise'       => 'wb_twilight',
            'market'        => 'storefront',
            'shop'          => 'shopping_bag',
            'temple'        => 'account_balance',
            'church'        => 'account_balance',
            'museum'        => 'museum',
            'garden'        => 'local_florist',
            'forest'        => 'forest',
            'park'          => 'park',
            'adventure'     => 'hiking',
            'street'        => 'restaurant',
        ];

        // Fetch all itinerary items for this trip and group icons by date
        $iconsByDate = [];
        $allItems = \App\Models\Itinerary::where('trip_id', $trip->id)
            ->orderBy('start_datetime')
            ->get();
        foreach ($allItems as $item) {
            $date  = $item->start_datetime->toDateString();
            $type  = $item->type ?? 'Activity';
            $title = strtolower($item->title ?? '');

            if (isset($typeToMs[$type]) && $type !== 'Activity') {
                $icon = $typeToMs[$type];
            } else {
                // Infer from title keywords
                $icon = 'explore';
                foreach ($titleKeywords as $keyword => $msIcon) {
                    if (str_contains($title, $keyword)) { $icon = $msIcon; break; }
                }
            }

            if (!isset($iconsByDate[$date])) $iconsByDate[$date] = [];
            if (!in_array($icon, $iconsByDate[$date])) $iconsByDate[$date][] = $icon;
        }
        // Last day: swap flight_takeoff → flight_land
        if (isset($iconsByDate[$endStr])) {
            $iconsByDate[$endStr] = array_map(
                fn($ic) => $ic === 'flight_takeoff' ? 'flight_land' : $ic,
                $iconsByDate[$endStr]
            );
        }

        // Build list of months to show
        $months = [];
        $cur = $tripStart->copy()->startOfMonth();
        $endMonth = $tripEnd->copy()->startOfMonth();
        while ($cur <= $endMonth) { $months[] = $cur->copy(); $cur->addMonth(); }
    @endphp

    @if($tab === 'moments')
    {{-- Moments: Leaflet map centered on the trip's destination --}}
    @php $mc = $this->mapCenter; @endphp
    <div style="background:#fff;border:1.5px solid var(--border);border-radius:16px;padding:16px;box-shadow:0 2px 10px rgba(0,0,0,.04);">
        <p style="margin:0 0 12px;font-size:12px;color:#9B8EA0;display:flex;align-items:center;gap:6px;">
            <i class="fa-solid fa-circle-info" style="color:#C8874A;"></i>
            Click anywhere on the map to drop a pin for a place you visited.
        </p>
        <div
            wire:key="moments-map-{{ $selectedTripId }}"
            wire:ignore
            x-data
            x-init="initMomentsMap($el, $wire, {{ $mc['lat'] }}, {{ $mc['lng'] }}, {{ $mc['zoom'] }}, {{ json_encode($tripLabel($trip) . ' · ' . $tripStart->format('M j') . '–' . $tripEnd->format('M j, Y')) }}, {{ json_encode($this->initialPins) }})"
            style="width:100%;height:440px;border-radius:12px;overflow:hidden;"
        ></div>
    </div>
    @else
    {{-- Calendar card --}}
    <div style="background:#fff;border:1.5px solid var(--border);border-radius:16px;padding:24px 28px;box-shadow:0 2px 10px rgba(0,0,0,.04);"
         x-data="{ mi: 0 }">

        {{-- Month navigation --}}
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
            <button @click="mi = Math.max(0, mi-1)" :disabled="mi === 0"
                    style="width:32px;height:32px;border-radius:8px;border:1px solid #d3c3be;background:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;color:#4f4441;"
                    :style="mi===0 ? 'opacity:.35;cursor:default' : ''">
                <i class="fa-solid fa-chevron-left" style="font-size:11px;"></i>
            </button>
            @foreach($months as $mIdx => $month)
            <span x-show="mi === {{ $mIdx }}" style="font-size:15px;font-weight:700;color:#1c1c19;">{{ $month->format('F Y') }}</span>
            @endforeach
            <button @click="mi = Math.min({{ count($months) - 1 }}, mi+1)" :disabled="mi === {{ count($months) - 1 }}"
                    style="width:32px;height:32px;border-radius:8px;border:1px solid #d3c3be;background:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;color:#4f4441;"
                    :style="mi==={{ count($months) - 1 }} ? 'opacity:.35;cursor:default' : ''">
                <i class="fa-solid fa-chevron-right" style="font-size:11px;"></i>
            </button>
        </div>

        {{-- Weekday headers --}}
        <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:4px;margin-bottom:6px;">
            @foreach(['Su','Mo','Tu','We','Th','Fr','Sa'] as $wd)
            <div style="text-align:center;font-size:11px;font-weight:700;color:#817470;padding:4px 0;">{{ $wd }}</div>
            @endforeach
        </div>

        {{-- Month grids --}}
        @foreach($months as $mIdx => $month)
        @php
            $firstDow    = $month->copy()->startOfMonth()->dayOfWeek;
            $daysInMonth = $month->daysInMonth;
        @endphp
        <div x-show="mi === {{ $mIdx }}">
            <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:2px;">
                @for($i = 0; $i < $firstDow; $i++)<div style="min-height:64px;"></div>@endfor
                @for($d = 1; $d <= $daysInMonth; $d++)
                @php
                    $dateStr = $month->format('Y-m') . '-' . str_pad($d, 2, '0', STR_PAD_LEFT);
                    $dc      = \Carbon\Carbon::parse($dateStr);
                    $isTrip  = $dc >= $tripStart && $dc <= $tripEnd;
                    $isEdge  = $dateStr === $startStr || $dateStr === $endStr;
                    $icons   = array_slice($iconsByDate[$dateStr] ?? [], 0, 3);
                @endphp
                @if($isTrip)
                <div wire:click="selectDay('{{ $dateStr }}')"
                     style="min-height:64px;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:6px 2px;border-radius:8px;cursor:pointer;background:#fafafa;transition:background .15s;"
                     onmouseenter="this.style.background='#f0ede9'" onmouseleave="this.style.background='#fafafa'">
                    <span style="font-size:14px;font-weight:500;color:#1c1c19;">{{ $d }}</span>
                    @if(count($icons))
                    <div style="display:flex;gap:2px;margin-top:4px;flex-wrap:wrap;justify-content:center;">
                        @foreach($icons as $icon)
                        <span class="material-symbols-outlined" style="font-size:11px;color:#934b19;font-variation-settings:'FILL' 0,'wght' 400,'GRAD' 0,'opsz' 24;">{{ $icon }}</span>
                        @endforeach
                    </div>
                    @endif
                </div>
                @else
                <div style="min-height:64px;display:flex;align-items:center;justify-content:center;">
                    <span style="font-size:14px;color:#c8c0bb;">{{ $d }}</span>
                </div>
                @endif
                @endfor
            </div>
        </div>
        @endforeach

    </div>
    @endif

    @endif

</div>
</div>
@endif


{{-- ── Generate Itinerary modal ──────────────────────── --}}
@if ($showGenerateModal && $selectedDate)
@php
    $trip = $this->selectedTrip;
    $budgetLow  = $trip ? number_format((int)($trip->budget_limit * 0.6)) : '0';
    $budgetHigh = $trip ? number_format((int)$trip->budget_limit) : '0';
    $interests  = ['Food Trip', 'Adventure', 'Nature', 'Sightseeing'];
    if ($trip) {
        $ttype = strtolower($trip->travel_type ?? '');
        if (str_contains($ttype, 'leisure'))  $interests = ['Food Trip', 'Nature', 'Sightseeing', 'Beaches'];
        if (str_contains($ttype, 'adventure')) $interests = ['Adventure', 'Hiking', 'Nature', 'Beaches'];
        if (str_contains($ttype, 'business')) $interests = ['Business', 'Dining', 'City Tours', 'Culture'];
        if (str_contains($ttype, 'family'))   $interests = ['Family Activities', 'Food Trip', 'Parks', 'Beaches'];
        if (str_contains($ttype, 'romance'))  $interests = ['Romantic Dining', 'Beaches', 'Sunsets', 'Spa'];
    }
@endphp
<div style="position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:1000;display:flex;align-items:center;justify-content:center;padding:16px;" wire:click.self="closeModals">
    <div style="background:#fff;border-radius:20px;width:100%;max-width:420px;box-shadow:0 20px 60px rgba(45,27,20,.18);padding:24px 24px 20px;">

        {{-- Header --}}
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:22px;">
            <div style="width:36px;height:36px;border-radius:10px;background:#FDF3EB;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="fa-solid fa-receipt" style="color:#934B19;font-size:15px;"></i>
            </div>
            <span style="font-size:17px;font-weight:800;color:#1c1c19;font-family:'Hanken Grotesk',sans-serif;">Generate Itinerary</span>
        </div>

        {{-- Destination --}}
        <div style="display:flex;align-items:center;gap:14px;margin-bottom:12px;">
            <div style="display:flex;align-items:center;gap:5px;width:120px;flex-shrink:0;">
                <i class="fa-solid fa-plane" style="color:#C8874A;font-size:11px;"></i>
                <span style="font-size:9px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#9B8EA0;">Destination</span>
            </div>
            <div style="flex:1;background:#FAF6F2;border:1.5px solid #EDE5DC;border-radius:12px;padding:10px 14px;display:flex;align-items:center;justify-content:space-between;">
                <span style="font-size:13px;font-weight:600;color:#1c1c19;">{{ $trip ? $tripLabel($trip) : '' }}</span>
                <i class="fa-solid fa-chevron-down" style="font-size:10px;color:#9B8EA0;"></i>
            </div>
        </div>

        {{-- Travel dates --}}
        <div style="display:flex;align-items:center;gap:14px;margin-bottom:12px;">
            <div style="display:flex;align-items:center;gap:5px;width:120px;flex-shrink:0;">
                <i class="fa-regular fa-calendar" style="color:#C8874A;font-size:11px;"></i>
                <span style="font-size:9px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#9B8EA0;">Travel Dates</span>
            </div>
            <div style="flex:1;background:#FAF6F2;border:1.5px solid #EDE5DC;border-radius:12px;padding:10px 14px;display:flex;align-items:center;justify-content:space-between;">
                <span style="font-size:13px;font-weight:600;color:#1c1c19;">{{ $trip ? $trip->start_date->format('M j') : '' }} – {{ $trip ? $trip->end_date->format('M j, Y') : '' }}</span>
                <i class="fa-regular fa-clock" style="font-size:12px;color:#C8874A;"></i>
            </div>
        </div>

        {{-- Budget range --}}
        <div style="display:flex;align-items:center;gap:14px;margin-bottom:12px;">
            <div style="display:flex;align-items:center;gap:5px;width:120px;flex-shrink:0;">
                <i class="fa-solid fa-wallet" style="color:#C8874A;font-size:11px;"></i>
                <span style="font-size:9px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#9B8EA0;">Budget Range</span>
            </div>
            <div style="flex:1;background:#FAF6F2;border:1.5px solid #EDE5DC;border-radius:12px;padding:10px 14px;display:flex;align-items:center;justify-content:space-between;">
                <span style="font-size:13px;font-weight:600;color:#1c1c19;">{{ $budgetLow }} – {{ $budgetHigh }}</span>
                <i class="fa-solid fa-coins" style="font-size:12px;color:#C8874A;"></i>
            </div>
        </div>

        {{-- Selected interests --}}
        <div style="display:flex;align-items:flex-start;gap:14px;margin-bottom:22px;">
            <div style="display:flex;align-items:center;gap:5px;width:120px;flex-shrink:0;padding-top:6px;">
                <i class="fa-regular fa-heart" style="color:#C8874A;font-size:11px;"></i>
                <span style="font-size:9px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#9B8EA0;">Selected Interests</span>
            </div>
            <div style="flex:1;display:flex;flex-wrap:wrap;gap:6px;">
                @foreach($interests as $tag)
                <span style="display:inline-flex;align-items:center;gap:4px;padding:5px 12px;border-radius:20px;background:#FAF6F2;border:1px solid #EDE5DC;font-size:11px;font-weight:600;color:#934B19;">
                    <i class="fa-solid fa-tag" style="font-size:8px;color:#C8874A;"></i>{{ $tag }}
                </span>
                @endforeach
            </div>
        </div>

        {{-- Full-width action button --}}
        <button wire:click="generateItinerary"
                style="width:100%;background:#934B19;color:#fff;border:none;border-radius:12px;padding:14px;font-size:14px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;font-family:'Hanken Grotesk',sans-serif;"
                wire:loading.attr="disabled" wire:target="generateItinerary"
                onmouseenter="this.style.background='#7A3C12'" onmouseleave="this.style.background='#934B19'">
            <span wire:loading.remove wire:target="generateItinerary">Generate Itinerary <i class="fa-solid fa-wand-magic-sparkles" style="font-size:12px;opacity:.9;"></i></span>
            <span wire:loading wire:target="generateItinerary"><i class="fa-solid fa-spinner fa-spin"></i> Generating…</span>
        </button>

    </div>
</div>
@endif

{{-- ── Day itinerary modal ────────────────────────────── --}}
@if ($showDayModal && $selectedDate)
@php
$msIconMap = [
    'Flight'         => ['icon'=>'flight_takeoff', 'color'=>'#F1A53D'],
    'Hotel'          => ['icon'=>'hotel',           'color'=>'#934b19'],
    'Transportation' => ['icon'=>'directions_car',  'color'=>'#6b5e8c'],
    'Dining'         => ['icon'=>'restaurant',      'color'=>'#ba4a4a'],
    'Food'           => ['icon'=>'restaurant',      'color'=>'#ba4a4a'],
    'Attraction'     => ['icon'=>'photo_camera',    'color'=>'#4f9648'],
    'Shopping'       => ['icon'=>'shopping_bag',    'color'=>'#b07e00'],
    'Activity'       => ['icon'=>'explore',         'color'=>'#4f7b94'],
];
$titleIconMap = [
    'breakfast'  => ['icon'=>'free_breakfast',  'color'=>'#e07b39'],
    'lunch'      => ['icon'=>'lunch_dining',     'color'=>'#ba4a4a'],
    'dinner'     => ['icon'=>'dinner_dining',    'color'=>'#ba4a4a'],
    'snack'      => ['icon'=>'bakery_dining',    'color'=>'#ba4a4a'],
    'food'       => ['icon'=>'restaurant',       'color'=>'#ba4a4a'],
    'restaurant' => ['icon'=>'restaurant',       'color'=>'#ba4a4a'],
    'cafe'       => ['icon'=>'local_cafe',       'color'=>'#ba4a4a'],
    'coffee'     => ['icon'=>'local_cafe',       'color'=>'#ba4a4a'],
    'bakery'     => ['icon'=>'bakery_dining',    'color'=>'#ba4a4a'],
    'street'     => ['icon'=>'restaurant',       'color'=>'#ba4a4a'],
    'hike'       => ['icon'=>'hiking',           'color'=>'#4f7b94'],
    'hiking'     => ['icon'=>'hiking',           'color'=>'#4f7b94'],
    'island'     => ['icon'=>'beach_access',     'color'=>'#1976b0'],
    'beach'      => ['icon'=>'beach_access',     'color'=>'#1976b0'],
    'snorkel'    => ['icon'=>'scuba_diving',     'color'=>'#1976b0'],
    'dive'       => ['icon'=>'scuba_diving',     'color'=>'#1976b0'],
    'lagoon'     => ['icon'=>'water',            'color'=>'#1976b0'],
    'waterfall'  => ['icon'=>'waterfall',        'color'=>'#1976b0'],
    'cave'       => ['icon'=>'landscape',        'color'=>'#4f7b94'],
    'pool'       => ['icon'=>'pool',             'color'=>'#1976b0'],
    'surf'       => ['icon'=>'surfing',          'color'=>'#1976b0'],
    'tour'       => ['icon'=>'tour',             'color'=>'#4f7b94'],
    'visit'      => ['icon'=>'photo_camera',     'color'=>'#4f9648'],
    'view'       => ['icon'=>'landscape',        'color'=>'#4f7b94'],
    'sunset'     => ['icon'=>'wb_twilight',      'color'=>'#e07b39'],
    'sunrise'    => ['icon'=>'wb_twilight',      'color'=>'#e07b39'],
    'market'     => ['icon'=>'storefront',       'color'=>'#b07e00'],
    'shop'       => ['icon'=>'shopping_bag',     'color'=>'#b07e00'],
    'temple'     => ['icon'=>'account_balance',  'color'=>'#6b5e8c'],
    'church'     => ['icon'=>'account_balance',  'color'=>'#6b5e8c'],
    'museum'     => ['icon'=>'museum',           'color'=>'#6b5e8c'],
    'garden'     => ['icon'=>'local_florist',    'color'=>'#4f9648'],
    'forest'     => ['icon'=>'forest',           'color'=>'#4f9648'],
    'park'       => ['icon'=>'park',             'color'=>'#4f9648'],
    'adventure'  => ['icon'=>'hiking',           'color'=>'#4f7b94'],
    'arrival'    => ['icon'=>'flight_land',      'color'=>'#F1A53D'],
    'departure'  => ['icon'=>'flight_takeoff',   'color'=>'#F1A53D'],
    'check-in'   => ['icon'=>'hotel',            'color'=>'#934b19'],
    'check-out'  => ['icon'=>'hotel',            'color'=>'#934b19'],
    'check in'   => ['icon'=>'hotel',            'color'=>'#934b19'],
    'check out'  => ['icon'=>'hotel',            'color'=>'#934b19'],
];
@endphp
<div style="position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:1000;display:flex;align-items:center;justify-content:center;padding:16px;" wire:click.self="closeModals">
    <div style="background:#fff;border-radius:16px;width:100%;max-width:420px;max-height:80vh;display:flex;flex-direction:column;box-shadow:0 20px 60px rgba(45,27,20,0.18);">

        {{-- Header --}}
        <div style="padding:22px 24px 16px;flex-shrink:0;">
            <h2 style="font-size:18px;font-weight:800;color:#1c1c19;margin:0 0 12px;">Trip Itinerary</h2>
            <span style="display:inline-block;padding:5px 14px;border-radius:20px;background:#FDF3EB;color:#934b19;font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;">
                {{ \Carbon\Carbon::parse($selectedDate)->format('F j, Y') }}
            </span>
        </div>

        <div style="height:1px;background:#f0ede9;flex-shrink:0;"></div>

        {{-- Activity list --}}
        <div style="overflow-y:auto;flex:1;padding:8px 24px 16px;">
            @forelse ($this->dayItems as $item)
            @php
                $iType  = $item->type ?? 'Activity';
                $iTitle = strtolower($item->title ?? '');
                if (isset($msIconMap[$iType]) && $iType !== 'Activity') {
                    $mi = $msIconMap[$iType];
                } else {
                    $mi = $msIconMap['Activity'];
                    foreach ($titleIconMap as $kw => $mi2) {
                        if (str_contains($iTitle, $kw)) { $mi = $mi2; break; }
                    }
                }
            @endphp
            <div style="display:flex;align-items:flex-start;gap:14px;padding:14px 0;border-bottom:1px solid #f5f0eb;">
                <div style="width:36px;height:36px;border-radius:50%;border:1px solid #e8ddd4;background:#fcf9f4;display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:2px;">
                    <span class="material-symbols-outlined" style="font-size:18px;color:{{ $mi['color'] }};font-variation-settings:'FILL' 0,'wght' 400,'GRAD' 0,'opsz' 24;">{{ $mi['icon'] }}</span>
                </div>
                <div style="flex:1;min-width:0;">
                    <div style="font-size:14px;font-weight:600;color:#1c1c19;margin-bottom:2px;">{{ $item->title }}</div>
                    <div style="font-size:12px;color:#817470;">{{ $item->start_datetime->format('g:i A') }}</div>
                    @if($item->notes)<div style="font-size:12px;color:#4f4441;margin-top:3px;font-style:italic;">{{ $item->notes }}</div>@endif
                </div>
                <button wire:click="deleteItem({{ $item->id }})" style="background:none;border:none;cursor:pointer;color:#c8c0bb;padding:4px;flex-shrink:0;" title="Remove">
                    <i class="fa-solid fa-xmark" style="font-size:12px;"></i>
                </button>
            </div>
            @empty
            <p style="text-align:center;padding:28px 0;font-size:13px;color:#9B8EA0;">No activities for this day.</p>
            @endforelse
        </div>

        {{-- Footer --}}
        <div style="padding:14px 24px 18px;flex-shrink:0;display:flex;justify-content:flex-end;">
            <button wire:click="closeModals"
                    style="background:#934b19;color:#fff;border:none;border-radius:10px;padding:10px 24px;font-size:13px;font-weight:700;cursor:pointer;font-family:'Hanken Grotesk',sans-serif;"
                    onmouseenter="this.style.background='#783603'" onmouseleave="this.style.background='#934b19'">
                Close
            </button>
        </div>

    </div>
</div>
@endif

{{-- ── Add/Edit Travel Pin modal ──────────────────────── --}}
@if ($showPinModal)
<div style="position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:1000;display:flex;align-items:center;justify-content:center;padding:16px;" wire:click.self="closePinModal">
    <div style="background:#fff;border-radius:20px;width:100%;max-width:420px;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(45,27,20,.18);padding:24px 24px 20px;">

        {{-- Header --}}
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px;">
            <div style="width:36px;height:36px;border-radius:10px;background:#FDF3EB;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="fa-solid fa-map-pin" style="color:#934B19;font-size:15px;"></i>
            </div>
            <span style="font-size:17px;font-weight:800;color:#1c1c19;font-family:'Hanken Grotesk',sans-serif;">
                {{ $pinModalMode === 'edit' ? 'Edit Travel Pin' : 'Add Travel Pin' }}
            </span>
        </div>
        <p style="margin:0 0 18px;font-size:11px;color:#9B8EA0;">
            <i class="fa-solid fa-location-dot" style="color:#C8874A;"></i>
            {{ number_format((float) $pinLat, 5) }}, {{ number_format((float) $pinLng, 5) }}
        </p>

        {{-- Place Name --}}
        <div style="margin-bottom:14px;">
            <label style="display:block;font-size:9px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#9B8EA0;margin-bottom:6px;">Place Name</label>
            <input type="text" wire:model="pinPlaceName" placeholder="e.g. Magellan's Cross"
                   style="width:100%;background:#FAF6F2;border:1.5px solid #EDE5DC;border-radius:12px;padding:11px 14px;font-size:13px;font-weight:600;color:#1c1c19;box-sizing:border-box;">
            @error('pinPlaceName') <span style="display:block;font-size:11px;color:#DC2626;margin-top:4px;">{{ $message }}</span> @enderror
        </div>

        {{-- Description / memory --}}
        <div style="margin-bottom:14px;">
            <label style="display:block;font-size:9px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#9B8EA0;margin-bottom:6px;">Description / Memory</label>
            <textarea wire:model="pinDescription" rows="3" placeholder="What happened here?"
                      style="width:100%;background:#FAF6F2;border:1.5px solid #EDE5DC;border-radius:12px;padding:11px 14px;font-size:13px;color:#1c1c19;box-sizing:border-box;resize:vertical;font-family:inherit;"></textarea>
            @error('pinDescription') <span style="display:block;font-size:11px;color:#DC2626;margin-top:4px;">{{ $message }}</span> @enderror
        </div>

        {{-- Date visited --}}
        <div style="margin-bottom:14px;">
            <label style="display:block;font-size:9px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#9B8EA0;margin-bottom:6px;">Date Visited</label>
            <input type="date" wire:model="pinVisitedDate"
                   style="width:100%;background:#FAF6F2;border:1.5px solid #EDE5DC;border-radius:12px;padding:11px 14px;font-size:13px;font-weight:600;color:#1c1c19;box-sizing:border-box;">
            @error('pinVisitedDate') <span style="display:block;font-size:11px;color:#DC2626;margin-top:4px;">{{ $message }}</span> @enderror
        </div>

        {{-- Photos --}}
        <div style="margin-bottom:20px;">
            <label style="display:block;font-size:9px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#9B8EA0;margin-bottom:6px;">Photos (optional, up to 6)</label>

            @if (count($pinExistingPhotos))
            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:6px;margin-bottom:8px;">
                @foreach ($pinExistingPhotos as $photo)
                <div style="position:relative;">
                    <img src="{{ $photo['url'] }}" style="width:100%;height:64px;object-fit:cover;border-radius:8px;display:block;">
                    <button type="button" wire:click="removeExistingPhoto({{ $photo['id'] }})" title="Remove photo"
                            style="position:absolute;top:-6px;right:-6px;width:20px;height:20px;border-radius:50%;background:#DC2626;color:#fff;border:2px solid #fff;font-size:9px;cursor:pointer;display:flex;align-items:center;justify-content:center;">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
                @endforeach
            </div>
            @endif

            @if (count($pinPhotos))
            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:6px;margin-bottom:8px;">
                @foreach ($pinPhotos as $index => $newPhoto)
                <div style="position:relative;">
                    <img src="{{ $newPhoto->temporaryUrl() }}" style="width:100%;height:64px;object-fit:cover;border-radius:8px;display:block;">
                    <button type="button" wire:click="removeNewPhoto({{ $index }})" title="Remove"
                            style="position:absolute;top:-6px;right:-6px;width:20px;height:20px;border-radius:50%;background:#DC2626;color:#fff;border:2px solid #fff;font-size:9px;cursor:pointer;display:flex;align-items:center;justify-content:center;">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
                @endforeach
            </div>
            @endif

            <input type="file" wire:model="pinPhotos" accept="image/*" multiple style="width:100%;font-size:12px;color:#4f4441;">
            <div wire:loading wire:target="pinPhotos" style="font-size:11px;color:#9B8EA0;margin-top:4px;"><i class="fa-solid fa-spinner fa-spin"></i> Uploading…</div>
            @error('pinPhotos') <span style="display:block;font-size:11px;color:#DC2626;margin-top:4px;">{{ $message }}</span> @enderror
            @error('pinPhotos.*') <span style="display:block;font-size:11px;color:#DC2626;margin-top:4px;">{{ $message }}</span> @enderror
        </div>

        {{-- Actions --}}
        <div style="display:flex;gap:10px;">
            <button wire:click="closePinModal" type="button"
                    style="flex:1;background:transparent;color:#6B7280;border:1.5px solid #E5E7EB;border-radius:12px;padding:12px 0;font-size:13px;font-weight:600;cursor:pointer;">
                Cancel
            </button>
            <button wire:click="savePin"
                    style="flex:2;background:#934B19;color:#fff;border:none;border-radius:12px;padding:12px 0;font-size:14px;font-weight:700;cursor:pointer;font-family:'Hanken Grotesk',sans-serif;"
                    wire:loading.attr="disabled" wire:target="savePin"
                    onmouseenter="this.style.background='#7A3C12'" onmouseleave="this.style.background='#934B19'">
                <span wire:loading.remove wire:target="savePin">{{ $pinModalMode === 'edit' ? 'Save Changes' : 'Add Pin' }}</span>
                <span wire:loading wire:target="savePin"><i class="fa-solid fa-spinner fa-spin"></i> Saving…</span>
            </button>
        </div>

    </div>
</div>
@endif

{{-- ── Delete Pin confirmation modal ─────────────────────── --}}
@if ($pinToDelete)
<div style="position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:2100;display:flex;align-items:center;justify-content:center;padding:20px;">
    <div style="background:#fff;border-radius:20px;width:100%;max-width:360px;box-shadow:0 20px 60px rgba(0,0,0,0.2);overflow:hidden;">
        {{-- Icon header --}}
        <div style="background:#FEF2F2;padding:28px 24px 20px;text-align:center;">
            <div style="width:52px;height:52px;border-radius:50%;background:#FEE2E2;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;">
                <i class="fa-solid fa-trash-can" style="font-size:22px;color:#DC2626;"></i>
            </div>
            <div style="font-size:17px;font-weight:700;color:#1A0A00;margin-bottom:6px;">Delete This Pin?</div>
            <div style="font-size:13px;color:#6B7280;line-height:1.5;">
                This travel pin and its photo will be permanently deleted.<br>This action cannot be undone.
            </div>
        </div>
        {{-- Actions --}}
        <div style="display:flex;gap:10px;padding:18px 20px;">
            <button wire:click="cancelDeletePin"
                    style="flex:1;background:transparent;color:#6B7280;border:1.5px solid #E5E7EB;border-radius:10px;padding:11px 0;font-size:13px;font-weight:600;cursor:pointer;">
                Cancel
            </button>
            <button wire:click="deletePin"
                    style="flex:1;background:#DC2626;color:#fff;border:none;border-radius:10px;padding:11px 0;font-size:13px;font-weight:600;cursor:pointer;">
                <span wire:loading.remove wire:target="deletePin">Delete</span>
                <span wire:loading wire:target="deletePin"><i class="fa-solid fa-spinner fa-spin"></i></span>
            </button>
        </div>
    </div>
</div>
@endif

<script>
    // Defined globally so it survives Livewire re-renders; called via x-init
    // on a wire:ignore + wire:key-ed div, so a trip switch always gets a
    // fresh element (and therefore a fresh map + pin state).
    window.initMomentsMap = function (el, wire, lat, lng, zoom, label, initialPins) {
        if (typeof L === 'undefined' || !el) return;

        // Livewire.on(...) listeners are global, not scoped to this element.
        // A trip switch runs this function again on a brand-new element (via
        // wire:key), so without this the previous map's listeners would keep
        // firing forever, stacking up one extra set per switch.
        if (window.__momentsMapUnsub) {
            window.__momentsMapUnsub.forEach(function (off) { off(); });
        }
        window.__momentsMapUnsub = [];

        var map = L.map(el).setView([lat, lng], zoom);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            maxZoom: 19,
        }).addTo(map);
        L.marker([lat, lng]).addTo(map).bindPopup(label).openPopup();

        var pinsById    = {};
        var markersById = {};
        var polyline    = null;

        function buildPopup(pin) {
            var wrap = document.createElement('div');
            wrap.style.cssText = 'min-width:180px;font-family:\'Hanken Grotesk\',sans-serif;';

            if (pin.photo_urls && pin.photo_urls.length) {
                var gallery = document.createElement('div');
                gallery.style.cssText = 'display:flex;gap:4px;overflow-x:auto;max-width:220px;margin-bottom:8px;';
                pin.photo_urls.forEach(function (url) {
                    var img = document.createElement('img');
                    img.src = url;
                    img.style.cssText = 'width:70px;height:70px;object-fit:cover;border-radius:6px;flex-shrink:0;cursor:pointer;';
                    img.title = 'Open full size';
                    img.addEventListener('click', function () { window.open(url, '_blank'); });
                    gallery.appendChild(img);
                });
                wrap.appendChild(gallery);
            }

            var title = document.createElement('div');
            title.textContent = pin.place_name;
            title.style.cssText = 'font-size:14px;font-weight:700;color:#1c1c19;margin-bottom:2px;';
            wrap.appendChild(title);

            var date = document.createElement('div');
            date.textContent = pin.visited_date;
            date.style.cssText = 'font-size:11px;color:#9B8EA0;margin-bottom:4px;';
            wrap.appendChild(date);

            if (pin.description) {
                var desc = document.createElement('div');
                desc.textContent = pin.description;
                desc.style.cssText = 'font-size:12px;color:#4f4441;margin:4px 0 8px;line-height:1.5;';
                wrap.appendChild(desc);
            }

            var actions = document.createElement('div');
            actions.style.cssText = 'display:flex;gap:6px;margin-top:6px;';

            var editBtn = document.createElement('button');
            editBtn.type = 'button';
            editBtn.textContent = 'Edit';
            editBtn.style.cssText = 'flex:1;background:#FDF3EB;color:#934B19;border:none;border-radius:8px;padding:6px 0;font-size:11px;font-weight:700;cursor:pointer;';
            editBtn.addEventListener('click', function () { wire.call('openEditPinModal', pin.id); });

            var delBtn = document.createElement('button');
            delBtn.type = 'button';
            delBtn.textContent = 'Delete';
            delBtn.style.cssText = 'flex:1;background:#FEF2F2;color:#DC2626;border:none;border-radius:8px;padding:6px 0;font-size:11px;font-weight:700;cursor:pointer;';
            delBtn.addEventListener('click', function () { wire.call('confirmDeletePin', pin.id); });

            actions.appendChild(editBtn);
            actions.appendChild(delBtn);
            wrap.appendChild(actions);
            return wrap;
        }

        function rebuildPolyline() {
            if (polyline) { map.removeLayer(polyline); polyline = null; }
            var ordered = Object.values(pinsById).sort(function (a, b) {
                return a.visited_date_sort < b.visited_date_sort ? -1 : (a.visited_date_sort > b.visited_date_sort ? 1 : 0);
            });
            if (ordered.length >= 2) {
                polyline = L.polyline(ordered.map(function (p) { return [p.lat, p.lng]; }), {
                    color: '#934B19', weight: 3, opacity: 0.7, dashArray: '6,6',
                }).addTo(map);
            }
        }

        function renderPin(pin) {
            pinsById[pin.id] = pin;
            var marker = markersById[pin.id];
            if (marker) {
                marker.setLatLng([pin.lat, pin.lng]);
                marker.setPopupContent(buildPopup(pin));
            } else {
                marker = L.marker([pin.lat, pin.lng]).addTo(map).bindPopup(buildPopup(pin));
                markersById[pin.id] = marker;
            }
            rebuildPolyline();
        }

        function removePin(pinId) {
            var marker = markersById[pinId];
            if (marker) { map.removeLayer(marker); delete markersById[pinId]; }
            delete pinsById[pinId];
            rebuildPolyline();
        }

        (initialPins || []).forEach(renderPin);

        // Marker clicks stop propagation in Leaflet, so this only fires when
        // clicking empty map area — never when clicking an existing pin.
        map.on('click', function (e) {
            wire.call('openAddPinModal', e.latlng.lat, e.latlng.lng);
        });

        window.__momentsMapUnsub.push(
            Livewire.on('pin-saved', function (payload) { renderPin(payload.pin); }),
            Livewire.on('pin-deleted', function (payload) { removePin(payload.id); })
        );

        el._leafletMap = map;
        // Container size can still be settling (flex layout) at init time.
        setTimeout(function () { map.invalidateSize(); }, 200);
    };
</script>

</div>
