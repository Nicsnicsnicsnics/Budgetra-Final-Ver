{{--
    Moments tab body: destination overview map, single-trip travel-pin map,
    and the pin add/edit/delete modals.

    @include()'d from itinerary-manager.blade.php only when $tab === 'moments'
    (and only once trips exist). Blade's @include shares the caller's
    variable scope, so $this (bound by Livewire to the ItineraryManager
    component) and $tripLabel (defined in the parent) are both available
    here without being passed explicitly.
--}}

@if ($momentsMode === 'overview')
{{-- Destination overview map: trip-status pins (visual only, not clickable)
     plus separate, clickable memory markers for every posted Moment. Click
     anywhere else on the map to post a new Moment there.

     Legend and hint float directly over the map (glassmorphism chips) rather
     than stacking above it, so the map itself gets almost the whole card. --}}
<style>
    .moments-overview-shell { position: relative; border-radius: 20px; overflow: hidden; box-shadow: 0 6px 28px rgba(45,27,20,.10); }
    .moments-overview-map-el { width: 100%; height: 640px; }
    @media (max-width: 900px) { .moments-overview-map-el { height: 480px; } }
    @media (max-width: 560px) { .moments-overview-map-el { height: 400px; } }
    .timeline-entry .timeline-card { transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease; }
    .timeline-entry:hover .timeline-card { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(45,27,20,.10); border-color: #934B19; }
    .moments-share-trip-btn { transition: background .15s ease, border-color .15s ease; }
    .moments-share-trip-btn:hover { background: #FDF3EB; border-color: #934B19; }
    .moments-float-chip {
        position: absolute; backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px);
        border-radius: 14px; box-shadow: 0 6px 20px rgba(0,0,0,.14); max-width: calc(100% - 32px);
    }
    .moments-float-legend {
        top: 16px; left: 16px; background: rgba(255,255,255,.82);
        padding: 10px 16px; display: flex; align-items: center; gap: 14px; flex-wrap: wrap;
    }
    .moments-float-hint {
        bottom: 16px; left: 16px; background: rgba(28,20,15,.80); color: #fff;
        padding: 10px 16px; font-size: 12px; display: flex; align-items: center; gap: 8px;
    }
    .moments-segmented {
        display: inline-flex; background: var(--bg, #F8F5F2); border: 1px solid var(--border, #E5E7EB);
        border-radius: 999px; padding: 4px; gap: 2px; margin-bottom: 16px;
    }
    .moments-segment {
        display: inline-flex; align-items: center; gap: 7px; border: none; background: transparent;
        color: #817470; font-size: 13px; font-weight: 700; padding: 8px 18px; border-radius: 999px;
        cursor: pointer; transition: background .2s ease, color .2s ease, box-shadow .2s ease;
    }
    .moments-segment.is-active { background: #fff; color: #934B19; box-shadow: 0 2px 8px rgba(45,27,20,.10); }
    .moments-segment:not(.is-active):hover { color: #4f4441; }
</style>

{{-- Map View / Timeline View toggle for the overview page itself — a
     separate Alpine store from the per-trip toggle below, so the two never
     interfere with each other. --}}
<div x-data class="moments-segmented">
    <button type="button" class="moments-segment" :class="{ 'is-active': $store.overviewMoments.view === 'map' }"
            @click="$store.overviewMoments.view = 'map'">
        <i class="fa-solid fa-map-location-dot"></i> Map View
    </button>
    <button type="button" class="moments-segment" :class="{ 'is-active': $store.overviewMoments.view === 'timeline' }"
            @click="$store.overviewMoments.view = 'timeline'">
        <i class="fa-solid fa-timeline"></i> Timeline View
    </button>
</div>

<div x-data>
    <div x-show="$store.overviewMoments.view === 'map'" x-transition.opacity.duration.200ms>
        <div class="moments-overview-shell">
            <div
                class="moments-overview-map-el"
                wire:key="moments-overview-map"
                wire:ignore
                x-data
                x-init="initOverviewMap($el, $wire, {{ json_encode($this->overviewPins) }}, {{ json_encode($this->allMomentPins) }})"
            ></div>

            <div class="moments-float-chip moments-float-legend">
                <span style="display:flex;align-items:center;gap:5px;font-size:11px;color:#4f4441;font-weight:600;"><span style="width:8px;height:8px;border-radius:50%;background:#22C55E;display:inline-block;"></span> Ongoing</span>
                <span style="display:flex;align-items:center;gap:5px;font-size:11px;color:#4f4441;font-weight:600;"><span style="width:8px;height:8px;border-radius:50%;background:#3B82F6;display:inline-block;"></span> Upcoming</span>
                <span style="display:flex;align-items:center;gap:5px;font-size:11px;color:#4f4441;font-weight:600;"><span style="width:8px;height:8px;border-radius:50%;background:#6B7280;display:inline-block;"></span> Completed</span>
                <span style="display:flex;align-items:center;gap:5px;font-size:11px;color:#4f4441;font-weight:600;"><i class="fa-solid fa-camera" style="font-size:9px;color:#934B19;"></i> Memory</span>
            </div>

            <div class="moments-float-chip moments-float-hint">
                <i class="fa-solid fa-circle-info" style="color:#F5C97A;"></i>
                Click anywhere to post a Moment
            </div>
        </div>
    </div>

    {{-- All-trips Travel Story Timeline --}}
    <div x-show="$store.overviewMoments.view === 'timeline'" x-transition.opacity.duration.200ms x-cloak>
        @php $overviewTimelineGrouped = collect($this->allMomentsTimeline)->groupBy('trip_destination'); @endphp
        <div style="background:#fff;border:1.5px solid var(--border);border-radius:20px;padding:20px 24px;box-shadow:0 6px 28px rgba(45,27,20,.10);max-height:640px;overflow-y:auto;">
            @if ($overviewTimelineGrouped->isEmpty())
            <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;padding:56px 24px;text-align:center;">
                <div style="width:64px;height:64px;border-radius:16px;background:#FDF3EB;display:flex;align-items:center;justify-content:center;margin-bottom:20px;">
                    <i class="fa-solid fa-book-open-reader" style="font-size:26px;color:#934B19;"></i>
                </div>
                <div style="font-size:16px;font-weight:700;color:#1c1c19;margin-bottom:8px;">Your travel diary starts here</div>
                <div style="font-size:13px;color:#9B8EA0;max-width:320px;line-height:1.6;margin-bottom:18px;">
                    Switch to Map View and click anywhere to post your first Moment — it'll show up here as a timeline entry.
                </div>
                <button type="button" @click="$store.overviewMoments.view = 'map'" class="moments-btn-primary"
                        style="background:#934B19;color:#fff;border:none;border-radius:10px;padding:10px 20px;font-size:13px;font-weight:700;cursor:pointer;">
                    <i class="fa-solid fa-map-location-dot"></i> Go to Map View
                </button>
            </div>
            @else
            @foreach ($overviewTimelineGrouped as $destination => $destMoments)
            <div style="margin-bottom:28px;">
                <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:14px;padding-bottom:8px;border-bottom:1.5px solid #F0E8DF;">
                    <div style="font-size:14px;font-weight:800;color:#934B19;text-transform:uppercase;letter-spacing:.04em;">
                        <i class="fa-solid fa-plane"></i> {{ $destination }}
                    </div>
                    {{-- Grouped by destination name, so if the same place was
                         visited on two separate trips this shares whichever
                         one the first moment in the group belongs to — same
                         limitation the grouping itself already has. --}}
                    <button type="button" wire:click="openSharePicker({{ $destMoments->first()['trip_id'] }})" class="moments-share-trip-btn"
                            style="flex-shrink:0;display:inline-flex;align-items:center;gap:6px;background:#fff;color:#934B19;border:1.5px solid #F0E8DF;border-radius:8px;padding:5px 12px;font-size:11px;font-weight:700;cursor:pointer;">
                        <i class="fa-solid fa-share-nodes"></i> Share
                    </button>
                </div>
                <div style="position:relative;padding-left:28px;">
                    <div style="position:absolute;left:9px;top:6px;bottom:6px;width:2px;background:#EDE0D6;"></div>
                    @foreach ($destMoments as $moment)
                    <div class="timeline-entry" style="position:relative;margin-bottom:14px;">
                        <div style="position:absolute;left:-24px;top:18px;width:10px;height:10px;border-radius:50%;background:#C8874A;"></div>
                        <div class="timeline-card" style="background:#fff;border:1.5px solid #F0E8DF;border-radius:16px;padding:16px;">
                            <div class="timeline-card-inner" style="display:flex;gap:12px;">
                                @if (count($moment['photo_urls']))
                                <img src="{{ $moment['photo_urls'][0] }}" alt="{{ $moment['place_name'] }}"
                                     style="width:76px;height:76px;border-radius:10px;object-fit:cover;flex-shrink:0;">
                                @else
                                <div style="width:76px;height:76px;border-radius:10px;background:#FDF3EB;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                    <i class="fa-solid fa-camera" style="color:#934B19;font-size:20px;"></i>
                                </div>
                                @endif
                                <div style="flex:1;min-width:0;">
                                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:2px;">
                                        <span style="font-size:14px;font-weight:700;color:#1c1c19;">{{ $moment['place_name'] }}</span>
                                        <span style="font-size:10px;font-weight:700;color:#934B19;background:#FDF3EB;padding:2px 8px;border-radius:20px;">Day {{ $moment['day_number'] }}</span>
                                    </div>
                                    @if ($moment['description'])
                                    <div style="font-size:12.5px;color:#4f4441;line-height:1.5;margin-bottom:6px;">{{ $moment['description'] }}</div>
                                    @endif
                                    <div style="font-size:11px;color:#9B8EA0;">
                                        <i class="fa-regular fa-clock" style="font-size:10px;"></i> Posted {{ $moment['posted_at'] }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endforeach
            @endif
        </div>
    </div>
</div>

@else
<button wire:click="backToOverview" type="button" class="moments-back-btn"
        style="display:flex;align-items:center;gap:8px;background:none;border:none;padding:0 0 16px;font-size:13px;font-weight:600;color:#934B19;cursor:pointer;">
    <i class="fa-solid fa-arrow-left" style="font-size:11px;transition:transform .15s ease;"></i> All Destinations
</button>
<style>
    .moments-back-btn:hover i { transform: translateX(-3px); }
</style>

@if ($selectedTripId && $this->selectedTrip)
@php
    $trip      = $this->selectedTrip;
    $tripStart = $trip->start_date->copy()->startOfDay();
    $tripEnd   = $trip->end_date->copy()->startOfDay();
    $mc        = $this->mapCenter;
    $timelineGrouped = collect($this->timelineMoments)->groupBy('day_number');
@endphp

<style>
    .timeline-entry .timeline-card { transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease; }
    .timeline-entry:hover .timeline-card { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(45,27,20,.10); border-color: #934B19; }
    .timeline-entry.is-highlighted .timeline-card { animation: timelinePulse 1.4s ease; border-color: #934B19; }
    @keyframes timelinePulse {
        0%   { box-shadow: 0 0 0 0 rgba(147,75,25,.45); }
        70%  { box-shadow: 0 0 0 12px rgba(147,75,25,0); }
        100% { box-shadow: 0 0 0 0 rgba(147,75,25,0); }
    }
    .marker-pulse { animation: markerPulse 1.4s ease; }
    @keyframes markerPulse {
        0%   { filter: drop-shadow(0 0 2px rgba(147,75,25,.7)); transform: scale(1); }
        50%  { transform: scale(1.3); }
        100% { filter: drop-shadow(0 0 0 rgba(147,75,25,0)); transform: scale(1); }
    }
    .moments-segmented {
        display: inline-flex; background: var(--bg, #F8F5F2); border: 1px solid var(--border, #E5E7EB);
        border-radius: 999px; padding: 4px; gap: 2px; margin-bottom: 16px;
    }
    .moments-segment {
        display: inline-flex; align-items: center; gap: 7px; border: none; background: transparent;
        color: #817470; font-size: 13px; font-weight: 700; padding: 8px 18px; border-radius: 999px;
        cursor: pointer; transition: background .2s ease, color .2s ease, box-shadow .2s ease;
    }
    .moments-segment.is-active { background: #fff; color: #934B19; box-shadow: 0 2px 8px rgba(45,27,20,.10); }
    .moments-segment:not(.is-active):hover { color: #4f4441; }
    @media (max-width: 640px) {
        .timeline-card-inner { flex-direction: column; }
    }
</style>

{{-- Map / Timeline toggle — client-side only (a global Alpine store), so
     switching views never round-trips through Livewire and the map (which
     is wire:ignore'd) is never torn down or reinitialized. --}}
<div x-data class="moments-segmented">
    <button type="button" class="moments-segment" :class="{ 'is-active': $store.moments.view === 'map' }"
            @click="$store.moments.view = 'map'">
        <i class="fa-solid fa-map-location-dot"></i> Map View
    </button>
    <button type="button" class="moments-segment" :class="{ 'is-active': $store.moments.view === 'timeline' }"
            @click="$store.moments.view = 'timeline'">
        <i class="fa-solid fa-timeline"></i> Timeline View
    </button>
</div>

<div x-data>
    {{-- Single trip's personal travel-pin map — unchanged, just now shown/hidden
         via Alpine instead of always rendered. --}}
    <div x-show="$store.moments.view === 'map'" x-transition.opacity.duration.200ms>
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
    </div>

    {{-- Travel Story Timeline --}}
    <div x-show="$store.moments.view === 'timeline'" x-transition.opacity.duration.200ms x-cloak>
        <div style="background:#fff;border:1.5px solid var(--border);border-radius:16px;padding:20px 24px;box-shadow:0 2px 10px rgba(0,0,0,.04);max-height:620px;overflow-y:auto;">
            @if ($timelineGrouped->isEmpty())
            {{-- Empty state --}}
            <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;padding:56px 24px;text-align:center;">
                <div style="width:64px;height:64px;border-radius:16px;background:#FDF3EB;display:flex;align-items:center;justify-content:center;margin-bottom:20px;">
                    <i class="fa-solid fa-book-open-reader" style="font-size:26px;color:#934B19;"></i>
                </div>
                <div style="font-size:16px;font-weight:700;color:#1c1c19;margin-bottom:8px;">Your travel diary starts here</div>
                <div style="font-size:13px;color:#9B8EA0;max-width:320px;line-height:1.6;margin-bottom:18px;">
                    Switch to Map View and click anywhere to post your first Moment — it'll show up here as a timeline entry.
                </div>
                <button type="button" @click="$store.moments.view = 'map'" class="moments-btn-primary"
                        style="background:#934B19;color:#fff;border:none;border-radius:10px;padding:10px 20px;font-size:13px;font-weight:700;cursor:pointer;">
                    <i class="fa-solid fa-map-location-dot"></i> Go to Map View
                </button>
            </div>
            @else
            <div style="position:relative;padding-left:28px;">
                {{-- Connecting vertical line --}}
                <div style="position:absolute;left:9px;top:6px;bottom:6px;width:2px;background:#EDE0D6;"></div>

                @foreach ($timelineGrouped as $dayNumber => $dayMoments)
                <div style="margin-bottom:28px;">
                    <div style="position:relative;margin-bottom:14px;">
                        <div style="position:absolute;left:-28px;top:2px;width:20px;height:20px;border-radius:50%;background:#934B19;border:3px solid #fff;box-shadow:0 0 0 2px #934B19;"></div>
                        <div style="font-size:13px;font-weight:800;color:#934B19;text-transform:uppercase;letter-spacing:.04em;">
                            Day {{ $dayNumber }}
                            <span style="font-weight:500;color:#9B8EA0;text-transform:none;">— {{ \Carbon\Carbon::parse($dayMoments->first()['visited_date'])->format('l, M j') }}</span>
                        </div>
                    </div>

                    @foreach ($dayMoments as $moment)
                    <div id="moment-{{ $moment['id'] }}" class="timeline-entry" tabindex="0"
                         style="position:relative;margin-bottom:14px;cursor:pointer;"
                         onclick="focusMapOnMoment({{ $moment['id'] }})">
                        <div style="position:absolute;left:-24px;top:18px;width:10px;height:10px;border-radius:50%;background:#C8874A;"></div>
                        <div class="timeline-card" style="background:#fff;border:1.5px solid #F0E8DF;border-radius:16px;padding:16px;">
                            <div class="timeline-card-inner" style="display:flex;gap:12px;">
                                @if (count($moment['photo_urls']))
                                <img src="{{ $moment['photo_urls'][0] }}" alt="{{ $moment['place_name'] }}"
                                     style="width:76px;height:76px;border-radius:10px;object-fit:cover;flex-shrink:0;">
                                @else
                                <div style="width:76px;height:76px;border-radius:10px;background:#FDF3EB;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                    <i class="fa-solid fa-camera" style="color:#934B19;font-size:20px;"></i>
                                </div>
                                @endif
                                <div style="flex:1;min-width:0;">
                                    <div style="font-size:14px;font-weight:700;color:#1c1c19;margin-bottom:2px;">{{ $moment['place_name'] }}</div>
                                    @if ($moment['description'])
                                    <div style="font-size:12.5px;color:#4f4441;line-height:1.5;margin-bottom:6px;">{{ $moment['description'] }}</div>
                                    @endif
                                    <div style="font-size:11px;color:#9B8EA0;">
                                        <i class="fa-regular fa-clock" style="font-size:10px;"></i> Posted {{ $moment['posted_at'] }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
                @endforeach
            </div>
            @endif
        </div>
    </div>
</div>
@endif
@endif

{{-- Shared modal entrance animation — a plain CSS keyframe rather than an
     Alpine x-transition, since these modals are gated by a Blade @if (a
     real DOM insert on each open), not an Alpine x-show, so a CSS
     animation on mount is what actually replays it every time. --}}
<style>
    @keyframes momentsModalBackdropIn { from { opacity: 0; } to { opacity: 1; } }
    @keyframes momentsModalCardIn { from { opacity: 0; transform: scale(.96) translateY(10px); } to { opacity: 1; transform: scale(1) translateY(0); } }
    .moments-modal-backdrop { animation: momentsModalBackdropIn .18s ease both; }
    .moments-modal-card { animation: momentsModalCardIn .22s cubic-bezier(.2,.9,.3,1.1) both; }
    .moments-input { transition: border-color .15s ease, box-shadow .15s ease; }
    .moments-input:focus { outline: none; border-color: #934B19 !important; box-shadow: 0 0 0 3px rgba(147,75,25,.12); }
    .moments-btn-primary { transition: background .15s ease, transform .12s ease, box-shadow .15s ease; }
    .moments-btn-primary:hover:not(:disabled) { background: #7A3C12; transform: translateY(-1px); box-shadow: 0 6px 16px rgba(147,75,25,.25); }
    .moments-btn-outline { transition: background .15s ease, border-color .15s ease, color .15s ease; }
    .moments-btn-outline:hover:not(:disabled) { background: #F9FAFB; border-color: #D1D5DB; color: #374151; }
    .moments-btn-danger { transition: background .15s ease, transform .12s ease, box-shadow .15s ease; }
    .moments-btn-danger:hover:not(:disabled) { background: #B91C1C; transform: translateY(-1px); box-shadow: 0 6px 16px rgba(220,38,38,.25); }
</style>

{{-- ── Add/Edit Travel Pin modal ──────────────────────── --}}
@if ($showPinModal)
<div class="moments-modal-backdrop" style="position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:1000;display:flex;align-items:center;justify-content:center;padding:16px;" wire:click.self="closePinModal">
    <div class="moments-modal-card" style="background:#fff;border-radius:20px;width:100%;max-width:420px;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(45,27,20,.18);padding:24px 24px 20px;">

        {{-- Header --}}
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px;">
            <div style="width:36px;height:36px;border-radius:10px;background:#FDF3EB;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="fa-solid fa-map-pin" style="color:#934B19;font-size:15px;"></i>
            </div>
            <span style="font-size:17px;font-weight:800;color:#1c1c19;font-family:'Hanken Grotesk',sans-serif;">
                {{ $pinModalMode === 'edit' ? 'Edit Travel Pin' : 'Add Travel Pin' }}
            </span>
        </div>
        <p style="margin:0 0 6px;font-size:11px;color:#9B8EA0;">
            <i class="fa-solid fa-location-dot" style="color:#C8874A;"></i>
            {{ number_format((float) $pinLat, 5) }}, {{ number_format((float) $pinLng, 5) }}
        </p>
        @if ($momentsMode === 'overview' && $this->selectedTrip)
        {{-- Posted from the overview map, which spans every trip — make the
             auto-resolved (nearest-destination) trip assignment explicit. --}}
        <p style="margin:0 0 12px;font-size:11px;color:#934B19;font-weight:600;">
            <i class="fa-solid fa-suitcase-rolling"></i> For: {{ $this->selectedTrip->destination }}
        </p>
        @else
        <div style="margin-bottom:18px;"></div>
        @endif

        {{-- Place Name --}}
        <div style="margin-bottom:14px;">
            <label style="display:block;font-size:9px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#9B8EA0;margin-bottom:6px;">Place Name</label>
            <input type="text" wire:model="pinPlaceName" placeholder="e.g. Magellan's Cross" class="moments-input"
                   style="width:100%;background:#FAF6F2;border:1.5px solid #EDE5DC;border-radius:12px;padding:11px 14px;font-size:13px;font-weight:600;color:#1c1c19;box-sizing:border-box;">
            @error('pinPlaceName') <span style="display:block;font-size:11px;color:#DC2626;margin-top:4px;">{{ $message }}</span> @enderror
        </div>

        {{-- Description / memory --}}
        <div style="margin-bottom:14px;">
            <label style="display:block;font-size:9px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#9B8EA0;margin-bottom:6px;">Description / Memory</label>
            <textarea wire:model="pinDescription" rows="3" placeholder="What happened here?" class="moments-input"
                      style="width:100%;background:#FAF6F2;border:1.5px solid #EDE5DC;border-radius:12px;padding:11px 14px;font-size:13px;color:#1c1c19;box-sizing:border-box;resize:vertical;font-family:inherit;"></textarea>
            @error('pinDescription') <span style="display:block;font-size:11px;color:#DC2626;margin-top:4px;">{{ $message }}</span> @enderror
        </div>

        {{-- Date visited --}}
        <div style="margin-bottom:14px;">
            <label style="display:block;font-size:9px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#9B8EA0;margin-bottom:6px;">Date Visited</label>
            <input type="date" wire:model="pinVisitedDate" class="moments-input"
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
            <button wire:click="closePinModal" type="button" class="moments-btn-outline"
                    style="flex:1;background:transparent;color:#6B7280;border:1.5px solid #E5E7EB;border-radius:12px;padding:12px 0;font-size:13px;font-weight:600;cursor:pointer;">
                Cancel
            </button>
            <button wire:click="savePin" class="moments-btn-primary"
                    style="flex:2;background:#934B19;color:#fff;border:none;border-radius:12px;padding:12px 0;font-size:14px;font-weight:700;cursor:pointer;font-family:'Hanken Grotesk',sans-serif;"
                    wire:loading.attr="disabled" wire:target="savePin">
                <span wire:loading.remove wire:target="savePin">{{ $pinModalMode === 'edit' ? 'Save Changes' : 'Add Pin' }}</span>
                <span wire:loading wire:target="savePin"><i class="fa-solid fa-spinner fa-spin"></i> Saving…</span>
            </button>
        </div>

    </div>
</div>
@endif

{{-- ── Delete Pin confirmation modal ─────────────────────── --}}
@if ($pinToDelete)
<div class="moments-modal-backdrop" style="position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:2100;display:flex;align-items:center;justify-content:center;padding:20px;">
    <div class="moments-modal-card" style="background:#fff;border-radius:20px;width:100%;max-width:360px;box-shadow:0 20px 60px rgba(0,0,0,0.2);overflow:hidden;">
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
            <button wire:click="cancelDeletePin" class="moments-btn-outline"
                    style="flex:1;background:transparent;color:#6B7280;border:1.5px solid #E5E7EB;border-radius:10px;padding:11px 0;font-size:13px;font-weight:600;cursor:pointer;">
                Cancel
            </button>
            <button wire:click="deletePin" class="moments-btn-danger"
                    style="flex:1;background:#DC2626;color:#fff;border:none;border-radius:10px;padding:11px 0;font-size:13px;font-weight:600;cursor:pointer;"
                    wire:loading.attr="disabled" wire:target="deletePin">
                <span wire:loading.remove wire:target="deletePin">Delete</span>
                <span wire:loading wire:target="deletePin"><i class="fa-solid fa-spinner fa-spin"></i></span>
            </button>
        </div>
    </div>
</div>
@endif

{{-- ── Share Photos picker ────────────────────────────────
     Which photos are checked lives in local Alpine state, not a Livewire
     property — Livewire only ever supplies the initial photo list for
     whichever trip openSharePicker() was opened with; every click after
     that is instant, no round-trip. --}}
@if ($showSharePicker)
<div class="moments-modal-backdrop" style="position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:1000;display:flex;align-items:center;justify-content:center;padding:16px;" wire:click.self="closeSharePicker">
    <div class="moments-modal-card" x-data="{ selected: [], sharing: false }" style="background:#fff;border-radius:20px;width:100%;max-width:480px;max-height:85vh;overflow-y:auto;box-shadow:0 20px 60px rgba(45,27,20,.18);padding:24px 24px 20px;">

        <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px;">
            <div style="width:36px;height:36px;border-radius:10px;background:#FDF3EB;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="fa-solid fa-share-nodes" style="color:#934B19;font-size:15px;"></i>
            </div>
            <span style="font-size:17px;font-weight:800;color:#1c1c19;font-family:'Hanken Grotesk',sans-serif;">Choose Photos to Share</span>
        </div>
        <p style="margin:0 0 16px;font-size:12px;color:#9B8EA0;">Tap the ones you want, then share.</p>

        @if (empty($this->sharePickerPhotos))
        <div style="padding:32px 0;text-align:center;color:#9B8EA0;font-size:13px;">
            <i class="fa-solid fa-camera" style="font-size:24px;color:#D8CFC5;display:block;margin-bottom:10px;"></i>
            No photos logged for this trip yet.
        </div>
        @else
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:18px;">
            @foreach ($this->sharePickerPhotos as $i => $photo)
            <button type="button"
                    @click="selected.includes({{ $i }}) ? selected = selected.filter(x => x !== {{ $i }}) : selected.push({{ $i }})"
                    :style="selected.includes({{ $i }}) ? 'border-color:#934B19;' : 'border-color:transparent;'"
                    style="position:relative;padding:0;border:2px solid transparent;border-radius:10px;overflow:hidden;cursor:pointer;aspect-ratio:1;background:none;">
                <img src="{{ $photo['url'] }}" alt="{{ $photo['place_name'] }}" style="width:100%;height:100%;object-fit:cover;display:block;">
                <div x-show="selected.includes({{ $i }})" x-cloak
                     style="position:absolute;top:5px;right:5px;width:20px;height:20px;border-radius:50%;background:#934B19;color:#fff;display:flex;align-items:center;justify-content:center;box-shadow:0 1px 4px rgba(0,0,0,.3);">
                    <i class="fa-solid fa-check" style="font-size:10px;"></i>
                </div>
            </button>
            @endforeach
        </div>
        @endif

        <div style="display:flex;gap:10px;">
            <button type="button" wire:click="closeSharePicker" class="moments-btn-outline"
                    style="flex:1;background:transparent;color:#6B7280;border:1.5px solid #E5E7EB;border-radius:12px;padding:12px 0;font-size:13px;font-weight:600;cursor:pointer;">
                Cancel
            </button>
            <button type="button" class="moments-btn-primary"
                    x-bind:disabled="selected.length === 0 || sharing"
                    @click="sharing = true; shareSelectedPhotos(selected, {{ json_encode($this->sharePickerPhotos) }}).finally(() => sharing = false)"
                    :style="(selected.length === 0 || sharing) ? 'opacity:.5;cursor:not-allowed;' : ''"
                    style="flex:2;background:#934B19;color:#fff;border:none;border-radius:12px;padding:12px 0;font-size:14px;font-weight:700;cursor:pointer;font-family:'Hanken Grotesk',sans-serif;">
                <span x-show="!sharing" x-text="'Share' + (selected.length ? ' (' + selected.length + ')' : '')"></span>
                <span x-show="sharing" x-cloak><i class="fa-solid fa-spinner fa-spin"></i> Preparing…</span>
            </button>
        </div>

    </div>
</div>
@endif

<script>
    // Map/Timeline toggle state — global Alpine stores rather than a
    // Livewire property, so switching views is instant (no network
    // round-trip) and never tears down the wire:ignore'd map. Two separate
    // stores: 'overviewMoments' for the all-destinations page, 'moments'
    // for a single selected trip — kept apart so toggling one never
    // affects the other.
    (function () {
        // Stores must exist before Alpine evaluates any $store.* binding on
        // this page. On a hard refresh Alpine hasn't booted yet, so we wait
        // for 'alpine:init'. But on a wire:navigate SPA hop into this page
        // from elsewhere, Alpine already booted long ago and 'alpine:init'
        // will never fire again — so if Alpine is already present, register
        // the stores immediately instead of waiting for an event that's
        // already happened.
        function initMomentsStores() {
            if (!Alpine.store('moments')) Alpine.store('moments', { view: 'map' });
            if (!Alpine.store('overviewMoments')) Alpine.store('overviewMoments', { view: 'map' });
        }
        if (window.Alpine) {
            initMomentsStores();
        } else {
            document.addEventListener('alpine:init', initMomentsStores);
        }
    })();

    // "Share" in the trip photo picker — fetches each selected photo, then
    // hands them all to the browser's own native share sheet
    // (navigator.share) so the traveler can post them to whichever app
    // they actually have installed. No social platform API involved;
    // Budgetra never talks to Instagram/Facebook/etc. directly. Returns a
    // promise so the caller's Alpine "sharing" state resolves correctly
    // whichever path is taken.
    window.shareSelectedPhotos = function (selectedIndexes, allPhotos) {
        if (!selectedIndexes.length) return Promise.resolve();

        var urls = selectedIndexes.map(function (i) { return allPhotos[i].url; });

        return Promise.all(urls.map(function (url, idx) {
            return fetch(url)
                .then(function (r) { return r.blob(); })
                .then(function (blob) {
                    var ext = (blob.type.split('/')[1] || 'jpg').replace('jpeg', 'jpg');
                    return new File([blob], 'moment-' + (idx + 1) + '.' + ext, { type: blob.type });
                });
        })).then(function (files) {
            if (navigator.canShare && navigator.canShare({ files: files })) {
                return navigator.share({ files: files, title: 'My travel moments — made with Budgetra' });
            }

            // Can't share files on this browser — download them instead so
            // the traveler still gets something out of the click.
            files.forEach(function (f) {
                var link = document.createElement('a');
                link.href = URL.createObjectURL(f);
                link.download = f.name;
                document.body.appendChild(link);
                link.click();
                link.remove();
                URL.revokeObjectURL(link.href);
            });
        }).catch(function (err) {
            // AbortError just means the traveler closed the native share
            // sheet without picking anything — not a real failure.
            if (err && err.name !== 'AbortError') {
                console.error('Share failed', err);
            }
        });
    };

    // Timeline card clicked → jump to Map View, fly to that moment's pin,
    // open its popup, and give the marker a brief highlight pulse.
    window.focusMapOnMoment = function (momentId) {
        if (typeof Alpine === 'undefined' || !Alpine.store('moments')) return;
        Alpine.store('moments').view = 'map';

        setTimeout(function () {
            var map    = window.__momentsMapInstance;
            var marker = window.__momentsMarkersById && window.__momentsMarkersById[momentId];
            if (!map || !marker) return;

            map.resize();
            map.flyTo({ center: marker.getLngLat(), zoom: 15, essential: true });
            marker.togglePopup();

            var el = marker.getElement();
            el.classList.add('marker-pulse');
            setTimeout(function () { el.classList.remove('marker-pulse'); }, 1400);
        }, 60);
    };

    // Map marker clicked → jump to Timeline View, scroll to and briefly
    // highlight the matching timeline card.
    window.focusTimelineOnMoment = function (momentId) {
        if (typeof Alpine === 'undefined' || !Alpine.store('moments')) return;
        Alpine.store('moments').view = 'timeline';

        setTimeout(function () {
            var el = document.getElementById('moment-' + momentId);
            if (!el) return;
            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            el.classList.add('is-highlighted');
            setTimeout(function () { el.classList.remove('is-highlighted'); }, 1600);
        }, 60);
    };

    // Defined globally so it survives Livewire re-renders; called via x-init
    // on a wire:ignore + wire:key-ed div, so a trip switch always gets a
    // fresh element (and therefore a fresh map + pin state).
    //
    // Uses MapLibre GL JS (vector tiles) rather than Leaflet + raster tiles.
    // Raster tiles are pre-rendered images — the label language is baked
    // into the picture, so there's no way to keep a raster basemap and also
    // translate its labels. Vector tiles ship each place's name as data, so
    // the style's label layers can be patched (below, in the 'load' handler)
    // to always prefer the English name before anything is drawn.
    window.initMomentsMap = function (el, wire, lat, lng, zoom, label, initialPins) {
        if (typeof maplibregl === 'undefined' || !el) return;

        // Livewire.on(...) listeners are global, not scoped to this element.
        // A trip switch runs this function again on a brand-new element (via
        // wire:key), so without this the previous map's listeners would keep
        // firing forever, stacking up one extra set per switch.
        if (window.__momentsMapUnsub) {
            window.__momentsMapUnsub.forEach(function (off) { off(); });
        }
        window.__momentsMapUnsub = [];
        if (window.__momentsMapInstance) {
            window.__momentsMapInstance.remove();
            window.__momentsMapInstance = null;
        }

        var map = new maplibregl.Map({
            container: el,
            style: 'https://tiles.openfreemap.org/styles/liberty', // free, no API key
            center: [lng, lat], // MapLibre uses [lng, lat], not [lat, lng]
            zoom: zoom,
            attributionControl: false,
        });
        map.addControl(new maplibregl.AttributionControl({
            compact: true,
            customAttribution: '&copy; OpenStreetMap contributors &copy; OpenFreeMap',
        }));
        map.addControl(new maplibregl.NavigationControl(), 'top-right');
        window.__momentsMapInstance = map;

        var pinsById    = {};
        var markersById = {};
        // Exposed so a Timeline card click (outside this closure) can reach
        // a specific marker — same live object, mutated in place below.
        window.__momentsMarkersById = markersById;

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
            } else {
                var noPhoto = document.createElement('div');
                noPhoto.style.cssText = 'width:70px;height:70px;border-radius:6px;background:#FDF3EB;display:flex;align-items:center;justify-content:center;margin-bottom:8px;';
                noPhoto.innerHTML = '<i class="fa-solid fa-camera" style="font-size:18px;color:#934B19;"></i>';
                wrap.appendChild(noPhoto);
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

        var ROUTE_ID = 'moments-route';

        function rebuildPolyline() {
            var ordered = Object.values(pinsById).sort(function (a, b) {
                return a.visited_date_sort < b.visited_date_sort ? -1 : (a.visited_date_sort > b.visited_date_sort ? 1 : 0);
            });
            var coords = ordered.map(function (p) { return [p.lng, p.lat]; });

            if (coords.length < 2) {
                if (map.getLayer(ROUTE_ID))  map.removeLayer(ROUTE_ID);
                if (map.getSource(ROUTE_ID)) map.removeSource(ROUTE_ID);
                return;
            }
            var geojson = { type: 'Feature', properties: {}, geometry: { type: 'LineString', coordinates: coords } };
            if (map.getSource(ROUTE_ID)) {
                map.getSource(ROUTE_ID).setData(geojson);
            } else {
                map.addSource(ROUTE_ID, { type: 'geojson', data: geojson });
                map.addLayer({
                    id: ROUTE_ID, type: 'line', source: ROUTE_ID,
                    paint: { 'line-color': '#934B19', 'line-width': 3, 'line-opacity': 0.7, 'line-dasharray': [2, 2] },
                });
            }
        }

        function renderPin(pin) {
            pinsById[pin.id] = pin;
            var popup  = new maplibregl.Popup({ offset: 25 }).setDOMContent(buildPopup(pin));
            var marker = markersById[pin.id];
            if (marker) {
                marker.setLngLat([pin.lng, pin.lat]).setPopup(popup);
            } else {
                marker = new maplibregl.Marker().setLngLat([pin.lng, pin.lat]).setPopup(popup).addTo(map);
                markersById[pin.id] = marker;
                // Jump to the matching Timeline entry — pin.id is a plain
                // number, captured by value, so this stays correct even if
                // the moment is later edited (its id never changes).
                marker.getElement().addEventListener('click', function () {
                    if (window.focusTimelineOnMoment) window.focusTimelineOnMoment(pin.id);
                });
            }
            rebuildPolyline();
        }

        function removePin(pinId) {
            var marker = markersById[pinId];
            if (marker) { marker.remove(); delete markersById[pinId]; }
            delete pinsById[pinId];
            rebuildPolyline();
        }

        map.on('load', function () {
            // Force every label layer to prefer the English name. OpenFreeMap's
            // "liberty" style otherwise renders each place in its own local
            // script — this is the actual fix for the original problem.
            map.getStyle().layers.forEach(function (layer) {
                if (layer.layout && layer.layout['text-field']) {
                    map.setLayoutProperty(layer.id, 'text-field', ['coalesce', ['get', 'name_en'], ['get', 'name']]);
                }
            });

            new maplibregl.Marker()
                .setLngLat([lng, lat])
                .setPopup(new maplibregl.Popup().setText(label))
                .addTo(map)
                .togglePopup();

            (initialPins || []).forEach(renderPin);

            // A marker's own DOM element captures the click, so this only
            // fires when clicking empty map area — never an existing pin.
            map.on('click', function (e) {
                wire.call('openAddPinModal', e.lngLat.lat, e.lngLat.lng);
            });

            window.__momentsMapUnsub.push(
                Livewire.on('pin-saved', function (payload) { renderPin(payload.pin); }),
                Livewire.on('pin-deleted', function (payload) { removePin(payload.id); })
            );

            setTimeout(function () { map.resize(); }, 200);
        });
    };

    // Destination overview map — trip-status pins (visual only) plus a
    // separate layer of clickable memory markers, one per posted Moment
    // across every trip. Separate MapLibre instance from initMomentsMap
    // above (mutually exclusive: only one of the two map divs exists in the
    // DOM at a time, switched via wire:key when momentsMode changes).
    window.initOverviewMap = function (el, wire, pins, allMoments) {
        if (typeof maplibregl === 'undefined' || !el) return;

        if (window.__momentsOverviewMapUnsub) {
            window.__momentsOverviewMapUnsub.forEach(function (off) { off(); });
        }
        window.__momentsOverviewMapUnsub = [];

        if (window.__momentsOverviewMapInstance) {
            window.__momentsOverviewMapInstance.remove();
            window.__momentsOverviewMapInstance = null;
        }

        var map = new maplibregl.Map({
            container: el,
            style: 'https://tiles.openfreemap.org/styles/liberty',
            center: [121.7740, 12.8797],
            zoom: 5,
            attributionControl: false,
        });
        map.addControl(new maplibregl.AttributionControl({
            compact: true,
            customAttribution: '&copy; OpenStreetMap contributors &copy; OpenFreeMap',
        }));
        map.addControl(new maplibregl.NavigationControl(), 'top-right');
        window.__momentsOverviewMapInstance = map;

        var STATUS_COLORS = { active: '#22C55E', upcoming: '#3B82F6', past: '#6B7280' };
        var STATUS_LABELS = { active: 'Ongoing', upcoming: 'Upcoming', past: 'Completed' };

        // Pure visual indicator — hover tooltip only, no click, no popup.
        function buildTripMarkerElement(pin) {
            var color = STATUS_COLORS[pin.status] || '#6B7280';
            var statusLabel = STATUS_LABELS[pin.status] || pin.status;

            var el = document.createElement('div');
            el.style.cssText = 'width:22px;height:22px;border-radius:50% 50% 50% 0;background:' + color + ';transform:rotate(-45deg);border:2px solid #fff;box-shadow:0 2px 6px rgba(0,0,0,.3);';
            el.title = pin.destination + ' · ' + pin.start_date + ' – ' + pin.end_date + ' · ' + statusLabel;

            return el;
        }

        // Memory markers: a small round pin showing the first photo (or a
        // camera icon if none), clickable to view/edit/delete that Moment.
        function buildMemoryMarkerElement(pin) {
            var hasPhoto = pin.photo_urls && pin.photo_urls.length > 0;
            var el = document.createElement('div');
            el.style.cssText = 'width:30px;height:30px;border-radius:50%;border:3px solid #934B19;box-shadow:0 2px 6px rgba(0,0,0,.35);cursor:pointer;background-color:#FDF3EB;background-size:cover;background-position:center;'
                + (hasPhoto ? "background-image:url('" + pin.photo_urls[0] + "');" : '');
            if (!hasPhoto) {
                el.innerHTML = '<div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;"><i class="fa-solid fa-camera" style="font-size:12px;color:#934B19;"></i></div>';
            }
            return el;
        }

        function buildMemoryPopup(pin) {
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
            } else {
                var noPhoto = document.createElement('div');
                noPhoto.style.cssText = 'width:70px;height:70px;border-radius:6px;background:#FDF3EB;display:flex;align-items:center;justify-content:center;margin-bottom:8px;';
                noPhoto.innerHTML = '<i class="fa-solid fa-camera" style="font-size:18px;color:#934B19;"></i>';
                wrap.appendChild(noPhoto);
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
            editBtn.addEventListener('click', function () { wire.call('openEditPinModalFromOverview', pin.id); });

            var delBtn = document.createElement('button');
            delBtn.type = 'button';
            delBtn.textContent = 'Delete';
            delBtn.style.cssText = 'flex:1;background:#FEF2F2;color:#DC2626;border:none;border-radius:8px;padding:6px 0;font-size:11px;font-weight:700;cursor:pointer;';
            delBtn.addEventListener('click', function () { wire.call('confirmDeletePinFromOverview', pin.id); });

            actions.appendChild(editBtn);
            actions.appendChild(delBtn);
            wrap.appendChild(actions);
            return wrap;
        }

        var memoryMarkersById = {};
        var momentsById = {};
        var ROUTES_ID = 'moments-overview-routes';

        // One dashed line per trip, connecting that trip's own memory markers
        // in visited-date order — mirrors initMomentsMap's rebuildPolyline,
        // just grouped by trip_id since this map spans every destination.
        function rebuildOverviewRoutes() {
            var byTrip = {};
            Object.values(momentsById).forEach(function (p) {
                (byTrip[p.trip_id] = byTrip[p.trip_id] || []).push(p);
            });

            var features = [];
            Object.keys(byTrip).forEach(function (tripId) {
                var ordered = byTrip[tripId].sort(function (a, b) {
                    return a.visited_date_sort < b.visited_date_sort ? -1 : (a.visited_date_sort > b.visited_date_sort ? 1 : 0);
                });
                if (ordered.length < 2) return;
                features.push({
                    type: 'Feature',
                    properties: {},
                    geometry: { type: 'LineString', coordinates: ordered.map(function (p) { return [p.lng, p.lat]; }) },
                });
            });

            var geojson = { type: 'FeatureCollection', features: features };
            if (map.getSource(ROUTES_ID)) {
                map.getSource(ROUTES_ID).setData(geojson);
            } else {
                map.addSource(ROUTES_ID, { type: 'geojson', data: geojson });
                map.addLayer({
                    id: ROUTES_ID, type: 'line', source: ROUTES_ID,
                    paint: { 'line-color': '#934B19', 'line-width': 3, 'line-opacity': 0.7, 'line-dasharray': [2, 2] },
                });
            }
        }

        function renderMemory(pin) {
            momentsById[pin.id] = pin;
            var popup  = new maplibregl.Popup({ offset: 20 }).setDOMContent(buildMemoryPopup(pin));
            var marker = memoryMarkersById[pin.id];
            if (marker) {
                marker.setLngLat([pin.lng, pin.lat]).setPopup(popup);
            } else {
                marker = new maplibregl.Marker({ element: buildMemoryMarkerElement(pin) })
                    .setLngLat([pin.lng, pin.lat])
                    .setPopup(popup)
                    .addTo(map);
                memoryMarkersById[pin.id] = marker;
            }
            rebuildOverviewRoutes();
        }

        function removeMemory(pinId) {
            var marker = memoryMarkersById[pinId];
            if (marker) { marker.remove(); delete memoryMarkersById[pinId]; }
            delete momentsById[pinId];
            rebuildOverviewRoutes();
        }

        map.on('load', function () {
            // Same English-label fix as initMomentsMap — this is a separate
            // style load, so it needs the same layout-property patch applied.
            map.getStyle().layers.forEach(function (layer) {
                if (layer.layout && layer.layout['text-field']) {
                    map.setLayoutProperty(layer.id, 'text-field', ['coalesce', ['get', 'name_en'], ['get', 'name']]);
                }
            });

            var bounds = new maplibregl.LngLatBounds();
            var seenCoords = [];
            function trackCoord(lng, lat) {
                var isDup = seenCoords.some(function (c) {
                    return Math.abs(c[0] - lng) < 1e-9 && Math.abs(c[1] - lat) < 1e-9;
                });
                if (!isDup) seenCoords.push([lng, lat]);
            }

            (pins || []).forEach(function (pin) {
                new maplibregl.Marker({ element: buildTripMarkerElement(pin) })
                    .setLngLat([pin.lng, pin.lat])
                    .addTo(map);
                bounds.extend([pin.lng, pin.lat]);
                trackCoord(pin.lng, pin.lat);
            });
            (allMoments || []).forEach(function (pin) {
                renderMemory(pin);
                bounds.extend([pin.lng, pin.lat]);
                trackCoord(pin.lng, pin.lat);
            });

            if (seenCoords.length === 1) {
                // fitBounds() on a zero-area bounds (every pin at the exact
                // same spot — the common case being just one trip with no
                // photos logged yet) is unreliable across MapLibre versions:
                // it can leave the view at whatever the map's initial
                // center/zoom was instead of actually moving to the pin,
                // which is what made a brand-new single-trip overview show
                // a random default location instead of that trip's own
                // destination. Jumping straight to the one known point
                // sidesteps the degenerate-bounds case entirely.
                map.jumpTo({ center: seenCoords[0], zoom: 8 });
            } else if (pins && pins.length) {
                map.fitBounds(bounds, { padding: 60, maxZoom: 8 });
            }

            // A marker's own DOM element captures the click, so this only
            // fires when clicking empty map area — never an existing pin.
            map.on('click', function (e) {
                wire.call('openAddPinModalFromOverview', e.lngLat.lat, e.lngLat.lng);
            });

            window.__momentsOverviewMapUnsub.push(
                Livewire.on('pin-saved', function (payload) { renderMemory(payload.pin); }),
                Livewire.on('pin-deleted', function (payload) { removeMemory(payload.id); })
            );

            setTimeout(function () { map.resize(); }, 200);
        });
    };
</script>
