<div class="mb-24">
    @if (session('success'))
        <div class="alert alert-success mb-16">{{ session('success') }}</div>
    @endif

    @if (!$previewTripId)
        <div style="display:flex;align-items:center;justify-content:space-between;" class="mb-16">
            <div>
                <h2 style="margin:0;">Shared Itineraries</h2>
                <p class="text-muted" style="margin:2px 0 0;">Trips other travelers have shared — click one to copy its itinerary into your own trip.</p>
            </div>
            <a href="#" wire:click.prevent="toggleCodeInput" style="font-size:13px;font-weight:600;color:var(--primary);text-decoration:none;white-space:nowrap;">
                {{ $showCodeInput ? 'Browse instead' : 'Have a code instead?' }}
            </a>
        </div>

        @if ($showCodeInput)
        <div class="card mb-16"><div class="card-body" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
            <input type="text" wire:model="code" wire:keydown.enter="lookupCode"
                   placeholder="e.g. AB3D9F2K" maxlength="8"
                   class="form-control" style="max-width:220px;text-transform:uppercase;">
            <button type="button" class="btn btn-primary" wire:click="lookupCode">Find Trip</button>
        </div></div>
        @endif

        @if ($error)
            <div class="alert alert-danger mb-16">{{ $error }}</div>
        @endif

        @if ($showCodeInput)
            {{-- Code entry is the focus; gallery stays hidden until back to browsing. --}}
        @elseif ($galleryTrips->isEmpty())
        <div class="card"><div class="card-body" style="text-align:center;color:var(--muted);">
            No one has shared a trip yet. Be the first — hit "Share" on one of your saved trips.
        </div></div>
        @else
        <div class="attraction-grid">
            @foreach ($galleryTrips as $trip)
            <div class="card" style="overflow:hidden;border-radius:14px;">
                <div style="position:relative;height:200px;background:linear-gradient(135deg,#934B19,#C8874A);">
                    @if ($trip->cover_image)
                    <img src="{{ $trip->cover_image }}" style="width:100%;height:100%;object-fit:cover;display:block;" alt="{{ $trip->destination }}">
                    @endif
                    <div style="position:absolute;inset:0;background:linear-gradient(to bottom,rgba(0,0,0,0.05) 40%,rgba(0,0,0,0.55));"></div>

                    <span style="position:absolute;top:12px;left:12px;background:#FDF3EB;color:#934B19;font-size:11px;font-weight:700;padding:4px 10px;border-radius:20px;">
                        {{ strtoupper($trip->travel_type ?? 'Solo') }}
                    </span>

                    <div style="position:absolute;bottom:12px;left:12px;right:12px;color:#fff;">
                        <div style="font-size:16px;font-weight:700;margin-bottom:2px;">{{ $trip->destination }}</div>
                        <div style="font-size:12px;opacity:0.9;">{{ $trip->start_date->format('M j') }} – {{ $trip->end_date->format('M j, Y') }}</div>
                    </div>
                </div>
                <div class="card-body" style="padding:14px 16px;">
                    <div class="text-muted" style="font-size:12px;margin-bottom:10px;">Shared by {{ $trip->user->full_name ?? 'a traveler' }}</div>
                    <button type="button" class="btn btn-primary" style="width:100%;" wire:click="previewTrip({{ $trip->id }})">
                        View & Import
                    </button>
                </div>
            </div>
            @endforeach
        </div>
        @endif
    @else
        <h3 style="margin-bottom:4px;">{{ $previewDestination }}</h3>
        <p class="text-muted" style="margin-bottom:16px;font-size:13px;">{{ $previewDates }}</p>

        @if (count($previewItinerary) === 0)
            <p class="text-muted">This trip has no itinerary items yet.</p>
        @else
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                <span style="font-size:13px;font-weight:600;">Select what to copy:</span>
                <span style="font-size:12px;">
                    <a href="#" wire:click.prevent="selectAll" style="margin-right:10px;">Select all</a>
                    <a href="#" wire:click.prevent="selectNone">Select none</a>
                </span>
            </div>
            <div style="max-height:260px;overflow-y:auto;border:1px solid var(--border);border-radius:10px;padding:8px 12px;margin-bottom:16px;">
                @foreach ($previewItinerary as $item)
                    <label style="display:flex;align-items:flex-start;gap:10px;padding:8px 0;border-bottom:1px solid #F3F4F6;cursor:pointer;">
                        <input type="checkbox" wire:model="selectedItineraryIds" value="{{ $item['id'] }}" style="margin-top:4px;">
                        <span>
                            <span style="font-weight:600;font-size:13px;">{{ $item['title'] }}</span>
                            <span style="font-size:11px;color:var(--muted);text-transform:uppercase;margin-left:6px;">{{ $item['type'] }}</span>
                            <br>
                            <span style="font-size:12px;color:var(--muted);">
                                {{ $item['when'] }}@if($item['location']) &middot; {{ $item['location'] }} @endif
                            </span>
                        </span>
                    </label>
                @endforeach
            </div>
        @endif

        @if ($error)
            <div class="alert alert-danger" style="margin-bottom:12px;">{{ $error }}</div>
        @endif

        <div style="display:flex;gap:10px;">
            <button type="button" class="btn btn-primary"
                    wire:click="confirmImport"
                    @if(count($previewItinerary) > 0 && count($selectedItineraryIds) === 0) disabled @endif>
                Import Trip
            </button>
            <button type="button" class="btn btn-secondary" wire:click="cancel">← Back to browse</button>
        </div>
    @endif
</div>
