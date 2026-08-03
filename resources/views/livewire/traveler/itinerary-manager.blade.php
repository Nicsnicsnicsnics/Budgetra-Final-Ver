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
    @if (!auth()->user()?->userProfile)
    <h2 style="font-weight:700;font-size:22px;margin-bottom:10px;color:#1A0A00;">Set up your profile first</h2>
    <p style="color:#9B8EA0;margin-bottom:28px;font-size:14px;max-width:320px;line-height:1.6;">Complete your travel profile before planning a trip.</p>
    <a href="{{ route('profile.setup') }}" style="display:inline-flex;align-items:center;gap:10px;background:#934B19;color:#fff;border-radius:30px;padding:14px 32px;font-size:13px;font-weight:700;letter-spacing:.06em;text-decoration:none;text-transform:uppercase;">
        <i class="fa-solid fa-user"></i> Set Up Your Profile First
    </a>
    @else
    <h2 style="font-weight:700;font-size:22px;margin-bottom:10px;color:#1A0A00;">{{ $isMoments ? 'No moments yet' : 'No itineraries yet' }}</h2>
    <p style="color:#9B8EA0;margin-bottom:28px;font-size:14px;max-width:320px;line-height:1.6;">{{ $isMoments ? 'Plan a trip first to add moments for your destinations.' : 'Plan a trip first to see the itineraries for your destinations.' }}</p>
    <a href="{{ route('trips.plan') }}" style="display:inline-flex;align-items:center;gap:10px;background:#934B19;color:#fff;border-radius:30px;padding:14px 32px;font-size:13px;font-weight:700;letter-spacing:.06em;text-decoration:none;text-transform:uppercase;">
        <i class="fa-solid fa-plane"></i> Plan Your First Trip
    </a>
    @endif
</div>

@else
<div style="flex:1;display:flex;align-items:{{ $tab === 'moments' ? 'center' : 'flex-start' }};justify-content:center;">
<div style="width:100%;{{ $tab === 'moments' ? 'max-width:1180px;' : '' }}">

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

    @if($tab === 'moments')
    @include('livewire.traveler.moments')
    @else

    {{-- Destination dropdown --}}
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

        // Per-category color for chips/legend/dots — same categories used
        // throughout the app's other cost/summary breakdowns.
        $catColor = [
            'flight' => '#3B82F6', 'hotel' => '#0D9488',
            'food'   => '#EF4444', 'activity' => '#10B981',
        ];
        $typeToCat = [
            'Flight' => 'flight', 'Accommodation' => 'hotel', 'Hotel' => 'hotel',
            'Food & Dining' => 'food', 'Dining' => 'food', 'food' => 'food', 'restaurant' => 'food',
            'Attraction' => 'activity', 'Activity' => 'activity', 'attraction' => 'activity',
            'Transport' => 'flight', 'Transportation' => 'flight', 'transport' => 'flight', 'flight' => 'flight',
        ];

        // Full per-day agenda (title/time/category) for the sidebar list,
        // chips in the month grid, and the day/week views.
        $itemsByDate = [];
        foreach ($allItems as $item) {
            $date = $item->start_datetime->toDateString();
            $cat  = $typeToCat[$item->type ?? 'Activity'] ?? 'activity';
            $itemsByDate[$date][] = [
                'title' => $item->title,
                'time'  => $item->start_datetime->format('g:i A'),
                'color' => $catColor[$cat],
            ];
        }

        // Which date the sidebar / day view show by default: today if the
        // trip covers it, otherwise the trip's first day.
        $todayStr = now()->toDateString();
        $agendaDate = ($todayStr >= $startStr && $todayStr <= $endStr) ? $todayStr : $startStr;
    @endphp

    {{-- Calendar --}}
    <style>
        /* display:grid lives in a class (not an inline style) here on
           purpose — Alpine's x-show toggles the element's inline
           `style.display` between 'none' and '' when it shows/hides a
           month. If display:grid were set inline on the same element,
           clearing it back to '' drops to the browser's default block
           display, and 7 day-cell divs stack one per line instead of
           forming a grid. A class-based display isn't touched by that. */
        .itin-cal-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:2px;}
        .itin-cal-grid-wide{display:grid;grid-template-columns:repeat(7,1fr);gap:8px;}
        .itin-view-btn{-webkit-appearance:none;appearance:none;border:none;outline:none;background:transparent;color:#817470;border-radius:8px;padding:7px 16px;font-size:12px;font-weight:700;cursor:pointer;font-family:inherit;transition:background .15s,color .15s,box-shadow .15s;}
        .itin-view-btn:hover{color:#934B19;}
        .itin-view-btn-active,.itin-view-btn-active:hover{background:#fff;color:#934B19;box-shadow:0 1px 4px rgba(0,0,0,.08);}
        .itin-nav-btn{-webkit-appearance:none;appearance:none;border-radius:8px;border:1px solid #d3c3be;background:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;color:#4f4441;font-family:inherit;transition:background .15s,border-color .15s;}
        .itin-nav-btn:hover{background:#F8F5F2;border-color:#934B19;color:#934B19;}
        .itin-nav-btn:disabled{opacity:.35;cursor:default;background:#fff;border-color:#d3c3be;color:#4f4441;}
        .itin-mini-day{aspect-ratio:1;display:flex;align-items:center;justify-content:center;font-size:11px;border-radius:7px;transition:background .15s;}
        .itin-mini-day-trip{color:#1c1c19;font-weight:600;cursor:pointer;}
        .itin-mini-day-trip:hover{background:#F5EBDF;}
        .itin-mini-day-selected,.itin-mini-day-selected:hover{background:#934B19;color:#fff;}
        .itin-cal-toggle{transition:background .15s;}
        .itin-cal-toggle:hover{background:#F8F5F2;}
    </style>
    <div x-data="{
            mi: 0,
            view: 'month',
            selDate: '{{ $agendaDate }}',
            showFlight: true, showHotel: true, showFood: true, showActivity: true,
         }"
         style="display:flex;gap:20px;align-items:flex-start;">

        {{-- ── Sidebar ── --}}
        <div style="width:240px;flex-shrink:0;display:flex;flex-direction:column;gap:16px;">

            {{-- Mini month calendar --}}
            <div style="background:#fff;border:1.5px solid var(--border);border-radius:16px;padding:16px;box-shadow:0 2px 10px rgba(0,0,0,.04);">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
                    <button @click="mi = Math.max(0, mi-1)" type="button" class="itin-nav-btn" :disabled="mi===0" style="width:26px;height:26px;">
                        <i class="fa-solid fa-chevron-left" style="font-size:10px;"></i>
                    </button>
                    @foreach($months as $mIdx => $month)
                    <span x-show="mi === {{ $mIdx }}" style="font-size:13px;font-weight:700;color:#1c1c19;">{{ $month->format('F Y') }}</span>
                    @endforeach
                    <button @click="mi = Math.min({{ count($months) - 1 }}, mi+1)" type="button" class="itin-nav-btn" :disabled="mi==={{ count($months) - 1 }}" style="width:26px;height:26px;">
                        <i class="fa-solid fa-chevron-right" style="font-size:10px;"></i>
                    </button>
                </div>
                <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:2px;margin-bottom:2px;">
                    @foreach(['Mo','Tu','We','Th','Fr','Sa','Su'] as $wd)
                    <div style="text-align:center;font-size:9px;font-weight:700;color:#817470;padding:2px 0;">{{ $wd }}</div>
                    @endforeach
                </div>
                @foreach($months as $mIdx => $month)
                @php
                    $firstDowMon = ($month->copy()->startOfMonth()->dayOfWeek + 6) % 7; // Monday-first offset
                    $daysInMonth = $month->daysInMonth;
                @endphp
                <div x-show="mi === {{ $mIdx }}" class="itin-cal-grid">
                    @for($i = 0; $i < $firstDowMon; $i++)<div></div>@endfor
                    @for($d = 1; $d <= $daysInMonth; $d++)
                    @php
                        $dateStr = $month->format('Y-m') . '-' . str_pad($d, 2, '0', STR_PAD_LEFT);
                        $dc      = \Carbon\Carbon::parse($dateStr);
                        $isTrip  = $dc >= $tripStart && $dc <= $tripEnd;
                    @endphp
                    <div @click="selDate='{{ $dateStr }}';{{ $isTrip ? "\$wire.selectDay('{$dateStr}')" : '' }}"
                         class="itin-mini-day {{ $isTrip ? 'itin-mini-day-trip' : '' }}"
                         :class="selDate==='{{ $dateStr }}' ? 'itin-mini-day-selected' : ''"
                         style="{{ $isTrip ? '' : 'color:#c8c0bb;' }}">
                        {{ $d }}
                    </div>
                    @endfor
                </div>
                @endforeach
            </div>

            {{-- My Calendars (category filter) --}}
            <div style="background:#fff;border:1.5px solid var(--border);border-radius:16px;padding:16px;box-shadow:0 2px 10px rgba(0,0,0,.04);">
                <div style="font-size:12px;font-weight:800;color:#1c1c19;margin-bottom:14px;">My Calendars</div>
                <div style="display:flex;flex-direction:column;gap:4px;">
                    @foreach([['showFlight','Transportation','#3B82F6'],['showHotel','Accommodation','#0D9488'],['showFood','Food & Dining','#EF4444'],['showActivity','Attractions','#10B981']] as [$flag, $label, $color])
                    <label class="itin-cal-toggle" style="display:flex;align-items:center;gap:10px;cursor:pointer;font-size:12px;font-weight:600;color:#4f4441;padding:6px 8px;border-radius:9px;margin:0 -8px;">
                        <span style="width:9px;height:9px;border-radius:50%;background:{{ $color }};flex-shrink:0;"></span>
                        <span style="flex:1;">{{ $label }}</span>
                        <input type="checkbox" x-model="{{ $flag }}" style="accent-color:#934B19;width:15px;height:15px;cursor:pointer;flex-shrink:0;">
                    </label>
                    @endforeach
                </div>
            </div>

            {{-- Selected day's events --}}
            <div style="background:#fff;border:1.5px solid var(--border);border-radius:16px;padding:16px;box-shadow:0 2px 10px rgba(0,0,0,.04);">
                <div style="font-size:12px;font-weight:800;color:#1c1c19;margin-bottom:12px;" x-text="selDate==='{{ $agendaDate }}' ? {{ $agendaDate === $todayStr ? "'Today\\'s Events'" : "'Trip Events'" }} : 'Events'"></div>
                @foreach($itemsByDate as $iDate => $iItems)
                <div x-show="selDate==='{{ $iDate }}'" style="display:flex;flex-direction:column;gap:10px;">
                    @foreach($iItems as $it)
                    <div style="display:flex;align-items:flex-start;gap:9px;">
                        <span style="width:8px;height:8px;border-radius:50%;background:{{ $it['color'] }};flex-shrink:0;margin-top:4px;"></span>
                        <div style="min-width:0;">
                            <div style="font-size:12px;font-weight:700;color:#1c1c19;line-height:1.4;">{{ $it['title'] }}</div>
                            <div style="font-size:11px;color:#9B8EA0;">{{ $it['time'] }}</div>
                        </div>
                    </div>
                    @endforeach
                </div>
                @endforeach
                @if(empty($itemsByDate))
                <p style="font-size:12px;color:#9B8EA0;margin:0;">No events yet.</p>
                @endif
            </div>
        </div>

        {{-- ── Main calendar ── --}}
        <div style="flex:1;min-width:0;background:#fff;border:1.5px solid var(--border);border-radius:16px;padding:24px 28px;box-shadow:0 2px 10px rgba(0,0,0,.04);">

            {{-- View switch + month navigation --}}
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
                <div style="display:inline-flex;gap:4px;background:#F8F5F2;border-radius:10px;padding:3px;">
                    @foreach(['month'=>'Month','week'=>'Week','day'=>'Day'] as $vKey => $vLabel)
                    <button @click="view='{{ $vKey }}'" type="button" class="itin-view-btn"
                            :class="view==='{{ $vKey }}' ? 'itin-view-btn-active' : ''">
                        {{ $vLabel }}
                    </button>
                    @endforeach
                </div>
                <div style="display:flex;align-items:center;gap:12px;">
                    <button @click="mi = Math.max(0, mi-1)" type="button" class="itin-nav-btn" :disabled="mi===0" style="width:32px;height:32px;">
                        <i class="fa-solid fa-chevron-left" style="font-size:11px;"></i>
                    </button>
                    @foreach($months as $mIdx => $month)
                    <span x-show="mi === {{ $mIdx }}" style="font-size:15px;font-weight:700;color:#1c1c19;">{{ $month->format('F Y') }}</span>
                    @endforeach
                    <button @click="mi = Math.min({{ count($months) - 1 }}, mi+1)" type="button" class="itin-nav-btn" :disabled="mi==={{ count($months) - 1 }}" style="width:32px;height:32px;">
                        <i class="fa-solid fa-chevron-right" style="font-size:11px;"></i>
                    </button>
                </div>
            </div>

            {{-- MONTH VIEW --}}
            <div x-show="view==='month'">
                <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:4px;margin-bottom:6px;">
                    @foreach(['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $wd)
                    <div style="text-align:center;font-size:11px;font-weight:700;color:#817470;padding:4px 0;">{{ $wd }}</div>
                    @endforeach
                </div>
                @foreach($months as $mIdx => $month)
                @php
                    $firstDowMon = ($month->copy()->startOfMonth()->dayOfWeek + 6) % 7;
                    $daysInMonth = $month->daysInMonth;
                @endphp
                <div x-show="mi === {{ $mIdx }}" class="itin-cal-grid">
                    @for($i = 0; $i < $firstDowMon; $i++)<div style="min-height:88px;min-width:0;"></div>@endfor
                    @for($d = 1; $d <= $daysInMonth; $d++)
                    @php
                        $dateStr = $month->format('Y-m') . '-' . str_pad($d, 2, '0', STR_PAD_LEFT);
                        $dc      = \Carbon\Carbon::parse($dateStr);
                        $isTrip  = $dc >= $tripStart && $dc <= $tripEnd;
                        $dayItemsList = $itemsByDate[$dateStr] ?? [];
                        $shown  = array_slice($dayItemsList, 0, 2);
                        $more   = max(0, count($dayItemsList) - 2);
                    @endphp
                    @if($isTrip)
                    <div wire:click="selectDay('{{ $dateStr }}')" @click="selDate='{{ $dateStr }}'"
                         style="min-height:88px;min-width:0;padding:6px;border-radius:8px;cursor:pointer;background:#fafafa;transition:background .15s;display:flex;flex-direction:column;gap:3px;box-sizing:border-box;"
                         onmouseenter="this.style.background='#f0ede9'" onmouseleave="this.style.background='#fafafa'">
                        <span style="font-size:12px;font-weight:600;color:#1c1c19;">{{ str_pad($d,2,'0',STR_PAD_LEFT) }}</span>
                        @foreach($shown as $si => $it)
                        @php $cat = array_search($it['color'], $catColor) ?: 'activity'; @endphp
                        <div x-show="show{{ ucfirst($cat === 'hotel' ? 'Hotel' : ($cat==='food'?'Food':($cat==='flight'?'Flight':'Activity'))) }}"
                             style="background:{{ $it['color'] }}14;border-left:2.5px solid {{ $it['color'] }};border-radius:3px;padding:2px 5px;font-size:9px;font-weight:600;color:{{ $it['color'] }};overflow:hidden;white-space:nowrap;text-overflow:ellipsis;">
                            {{ $it['time'] }} - {{ $it['title'] }}
                        </div>
                        @endforeach
                        @if($more > 0)
                        <div style="font-size:9px;font-weight:700;color:#9B8EA0;padding:0 2px;">+{{ $more }} More</div>
                        @endif
                    </div>
                    @else
                    <div style="min-height:88px;min-width:0;padding:6px;box-sizing:border-box;">
                        <span style="font-size:12px;color:#c8c0bb;">{{ str_pad($d,2,'0',STR_PAD_LEFT) }}</span>
                    </div>
                    @endif
                    @endfor
                </div>
                @endforeach
            </div>

            {{-- WEEK VIEW — the 7-day row containing the selected date --}}
            <div x-show="view==='week'">
                @foreach($months as $mIdx => $month)
                @php
                    $firstDowMon = ($month->copy()->startOfMonth()->dayOfWeek + 6) % 7;
                    $daysInMonth = $month->daysInMonth;
                    $weeks = [];
                    $week  = array_fill(0, $firstDowMon, null);
                    for ($d = 1; $d <= $daysInMonth; $d++) {
                        $week[] = $month->format('Y-m') . '-' . str_pad($d, 2, '0', STR_PAD_LEFT);
                        if (count($week) === 7) { $weeks[] = $week; $week = []; }
                    }
                    if ($week) { $weeks[] = array_pad($week, 7, null); }
                @endphp
                <template x-if="mi === {{ $mIdx }}">
                    <div>
                        @foreach($weeks as $wRow)
                        @php $rowDates = array_filter($wRow); @endphp
                        <div x-show="{{ json_encode(array_values($rowDates)) }}.includes(selDate)" class="itin-cal-grid-wide">
                            @foreach($wRow as $dateStr)
                            @php
                                $dc     = $dateStr ? \Carbon\Carbon::parse($dateStr) : null;
                                $isTrip = $dc && $dc >= $tripStart && $dc <= $tripEnd;
                                $dayItemsList = $dateStr ? ($itemsByDate[$dateStr] ?? []) : [];
                            @endphp
                            <div style="min-height:220px;min-width:0;box-sizing:border-box;border:1px solid #f0ede9;border-radius:10px;padding:8px;{{ $isTrip ? 'cursor:pointer;background:#fafafa;' : 'background:#fcfbfa;' }}"
                                 @if($isTrip) wire:click="selectDay('{{ $dateStr }}')" @click="selDate='{{ $dateStr }}'" @endif>
                                @if($dateStr)
                                <div style="font-size:11px;font-weight:700;color:{{ $isTrip ? '#1c1c19' : '#c8c0bb' }};margin-bottom:6px;">{{ \Carbon\Carbon::parse($dateStr)->format('D, M j') }}</div>
                                @foreach($dayItemsList as $it)
                                @php $cat = array_search($it['color'], $catColor) ?: 'activity'; @endphp
                                <div x-show="show{{ ucfirst($cat === 'hotel' ? 'Hotel' : ($cat==='food'?'Food':($cat==='flight'?'Flight':'Activity'))) }}"
                                     style="background:{{ $it['color'] }}14;border-left:2.5px solid {{ $it['color'] }};border-radius:3px;padding:3px 6px;font-size:10px;font-weight:600;color:{{ $it['color'] }};margin-bottom:4px;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;">
                                    {{ $it['time'] }} - {{ $it['title'] }}
                                </div>
                                @endforeach
                                @endif
                            </div>
                            @endforeach
                        </div>
                        @endforeach
                    </div>
                </template>
                @endforeach
            </div>

            {{-- DAY VIEW — full agenda for the selected date --}}
            <div x-show="view==='day'">
                @foreach($itemsByDate as $iDate => $iItems)
                <div x-show="selDate==='{{ $iDate }}'" style="display:flex;flex-direction:column;gap:10px;">
                    @foreach($iItems as $it)
                    @php $cat = array_search($it['color'], $catColor) ?: 'activity'; @endphp
                    <div x-show="show{{ ucfirst($cat === 'hotel' ? 'Hotel' : ($cat==='food'?'Food':($cat==='flight'?'Flight':'Activity'))) }}"
                         style="display:flex;align-items:center;gap:14px;background:{{ $it['color'] }}0D;border-left:3px solid {{ $it['color'] }};border-radius:10px;padding:14px 16px;">
                        <div style="font-size:12px;font-weight:700;color:{{ $it['color'] }};width:80px;flex-shrink:0;">{{ $it['time'] }}</div>
                        <div style="font-size:14px;font-weight:600;color:#1c1c19;">{{ $it['title'] }}</div>
                    </div>
                    @endforeach
                </div>
                @endforeach
                @php $datesWithItems = array_keys($itemsByDate); @endphp
                <template x-if="!{{ Illuminate\Support\Js::from($datesWithItems) }}.includes(selDate)">
                    <p style="font-size:13px;color:#9B8EA0;text-align:center;padding:40px 0;margin:0;">No activities for this day yet — click it on the calendar to generate one.</p>
                </template>
            </div>

        </div>
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
            <div style="flex:1;min-width:0;box-sizing:border-box;background:#FAF6F2;border:1.5px solid #EDE5DC;border-radius:12px;padding:10px 14px;display:flex;align-items:center;justify-content:space-between;gap:8px;">
                <span style="font-size:13px;font-weight:600;color:#1c1c19;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;min-width:0;">{{ $trip ? $tripLabel($trip) : '' }}</span>
                <i class="fa-solid fa-chevron-down" style="font-size:10px;color:#9B8EA0;flex-shrink:0;"></i>
            </div>
        </div>

        {{-- Travel dates --}}
        <div style="display:flex;align-items:center;gap:14px;margin-bottom:12px;">
            <div style="display:flex;align-items:center;gap:5px;width:120px;flex-shrink:0;">
                <i class="fa-regular fa-calendar" style="color:#C8874A;font-size:11px;"></i>
                <span style="font-size:9px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#9B8EA0;">Travel Dates</span>
            </div>
            <div style="flex:1;min-width:0;box-sizing:border-box;background:#FAF6F2;border:1.5px solid #EDE5DC;border-radius:12px;padding:10px 14px;display:flex;align-items:center;justify-content:space-between;gap:8px;">
                <span style="font-size:13px;font-weight:600;color:#1c1c19;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;min-width:0;">{{ $trip ? $trip->start_date->format('M j') : '' }} – {{ $trip ? $trip->end_date->format('M j, Y') : '' }}</span>
                <i class="fa-regular fa-clock" style="font-size:12px;color:#C8874A;flex-shrink:0;"></i>
            </div>
        </div>

        {{-- Budget range --}}
        <div style="display:flex;align-items:center;gap:14px;margin-bottom:12px;">
            <div style="display:flex;align-items:center;gap:5px;width:120px;flex-shrink:0;">
                <i class="fa-solid fa-wallet" style="color:#C8874A;font-size:11px;"></i>
                <span style="font-size:9px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#9B8EA0;">Budget Range</span>
            </div>
            <div style="flex:1;min-width:0;box-sizing:border-box;background:#FAF6F2;border:1.5px solid #EDE5DC;border-radius:12px;padding:10px 14px;display:flex;align-items:center;justify-content:space-between;gap:8px;">
                <span style="font-size:13px;font-weight:600;color:#1c1c19;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;min-width:0;">{{ $budgetLow }} – {{ $budgetHigh }}</span>
                <i class="fa-solid fa-coins" style="font-size:12px;color:#C8874A;flex-shrink:0;"></i>
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
    'Flight'         => ['icon'=>'flight_takeoff', 'color'=>'#3B82F6'],
    'Hotel'          => ['icon'=>'hotel',           'color'=>'#0D9488'],
    'Transportation' => ['icon'=>'directions_car',  'color'=>'#3B82F6'],
    'Dining'         => ['icon'=>'restaurant',      'color'=>'#EF4444'],
    'Food'           => ['icon'=>'restaurant',      'color'=>'#EF4444'],
    'Attraction'     => ['icon'=>'photo_camera',    'color'=>'#10B981'],
    'Shopping'       => ['icon'=>'shopping_bag',    'color'=>'#10B981'],
    'Activity'       => ['icon'=>'explore',         'color'=>'#10B981'],
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

</div>
