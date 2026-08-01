<div style="display:flex;flex-direction:column;flex:1;">

<style>
.st-panel-pop{animation:stPanelPop .22s ease;transform-origin:left center;}
@keyframes stPanelPop{from{opacity:0;transform:scaleX(.9);}to{opacity:1;transform:scaleX(1);}}
@keyframes stModalPop{from{opacity:0;transform:scale(.94) translateY(8px);}to{opacity:1;transform:scale(1) translateY(0);}}
.st-thin-scroll{scrollbar-width:none;-ms-overflow-style:none;}
.st-thin-scroll::-webkit-scrollbar{width:0;height:0;background:transparent;}
.st-sum-row{transition:background .15s;}
.st-sum-row:hover{background:#FBF6F1;}
</style>

    @if ($trips->isEmpty())
    <div class="empty-state-center" style="min-height:80vh;">
        <div style="width:64px;height:64px;border-radius:18px;background:#934B19;display:flex;align-items:center;justify-content:center;margin-bottom:24px;box-shadow:0 10px 26px rgba(147,75,25,0.24);">
            <i class="fa-solid fa-suitcase-rolling" style="font-size:28px;color:#fff;"></i>
        </div>
        @if (!auth()->user()?->userProfile)
        <h2 style="font-weight:700;font-size:22px;margin-bottom:10px;color:#1A0A00;">Set up your profile first</h2>
        <p style="color:#9B8EA0;margin-bottom:28px;font-size:14px;max-width:320px;line-height:1.6;">Complete your travel profile before planning a trip.</p>
        <a href="{{ route('profile.setup') }}" style="display:inline-flex;align-items:center;gap:10px;background:#934B19;color:#fff;border-radius:30px;padding:14px 32px;font-size:13px;font-weight:700;letter-spacing:.06em;text-decoration:none;text-transform:uppercase;box-shadow:0 8px 22px rgba(147,75,25,0.24);transition:background .18s;"
           onmouseenter="this.style.background='#6A3500'" onmouseleave="this.style.background='#934B19'">
            <i class="fa-solid fa-user"></i> Set Up Your Profile First
        </a>
        @else
        <h2 style="font-weight:700;font-size:22px;margin-bottom:10px;color:#1A0A00;">No saved trips yet</h2>
        <p style="color:#9B8EA0;margin-bottom:28px;font-size:14px;max-width:320px;line-height:1.6;">Plan a trip first to see your saved and draft trips.</p>
        <a href="{{ route('trips.plan') }}" style="display:inline-flex;align-items:center;gap:10px;background:#934B19;color:#fff;border-radius:30px;padding:14px 32px;font-size:13px;font-weight:700;letter-spacing:.06em;text-decoration:none;text-transform:uppercase;box-shadow:0 8px 22px rgba(147,75,25,0.24);transition:background .18s;"
           onmouseenter="this.style.background='#6A3500'" onmouseleave="this.style.background='#934B19'">
            <i class="fa-solid fa-plane"></i> Plan Your First Trip
        </a>
        @endif
    </div>
    @else
    @php
        $draftTrips  = $trips->where('status', '=', 'draft');
        $activeTrips = $trips->whereNotIn('status', ['past', 'draft']);
        $pastTrips   = $trips->where('status', '=', 'past');
        $stGroups = [
            ['key' => 'active', 'label' => 'Active Trips', 'icon' => 'fa-solid fa-plane-departure',  'items' => $activeTrips, 'noun' => 'active trips'],
            ['key' => 'draft',  'label' => 'Draft Trips',  'icon' => 'fa-regular fa-file-lines',      'items' => $draftTrips,  'noun' => 'draft trips'],
            ['key' => 'past',   'label' => 'Past Trips',   'icon' => 'fa-solid fa-clock-rotate-left', 'items' => $pastTrips,   'noun' => 'past trips'],
        ];
    @endphp

    @foreach ($stGroups as $stGroup)
    <div x-data="{ open: false }" style="margin-bottom:20px;background:#fff;border:1.5px solid var(--border);border-radius:999px;transition:border-radius .2s,border-color .2s,box-shadow .2s;"
         :style="open ? 'border-radius:22px;border-color:#934B19;box-shadow:0 8px 24px rgba(147,75,25,.12);' : ''">
        <button @click="open=!open" type="button" style="width:100%;display:flex;align-items:center;justify-content:space-between;padding:14px 20px;background:none;border:none;cursor:pointer;">
            <div style="display:flex;align-items:center;gap:12px;">
                <div style="width:32px;height:32px;border-radius:10px;background:#934B19;display:flex;align-items:center;justify-content:center;flex-shrink:0;box-shadow:0 4px 10px rgba(147,75,25,0.22);">
                    <i class="{{ $stGroup['icon'] }}" style="color:#fff;font-size:13px;"></i>
                </div>
                <span style="font-size:15px;font-weight:700;color:#1A0A00;">{{ $stGroup['label'] }}</span>
                @php $stCount = $stGroup['items']->count(); @endphp
                <span style="font-size:11px;font-weight:800;{{ $stCount > 0 ? 'color:#fff;background:linear-gradient(135deg,#B0662A,#934B19);box-shadow:0 3px 8px rgba(147,75,25,0.3);' : 'color:#B3A69A;background:#F3EEE8;' }}border-radius:99px;min-width:22px;height:22px;padding:0 7px;display:inline-flex;align-items:center;justify-content:center;line-height:1;">{{ $stCount }}</span>
            </div>
            <i class="fa-solid fa-chevron-down" style="font-size:12px;color:var(--muted);transition:.2s;" :style="open?'transform:rotate(180deg)':''"></i>
        </button>
        <div x-show="open" x-transition style="padding:16px 18px 20px;border-top:1px solid var(--border);">
            @if ($stGroup['items']->isEmpty())
            <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:40px 20px;">
                <div style="width:56px;height:56px;border-radius:16px;background:#934B19;display:flex;align-items:center;justify-content:center;margin-bottom:18px;box-shadow:0 8px 20px rgba(147,75,25,0.2);">
                    <i class="{{ $stGroup['icon'] }}" style="font-size:24px;color:#fff;"></i>
                </div>
                <h3 style="font-weight:700;font-size:17px;margin:0 0 6px;color:#1A0A00;">No {{ $stGroup['label'] }} yet</h3>
                <p style="color:#9B8EA0;font-size:13px;max-width:280px;line-height:1.6;margin:0;">Plan a trip first to see your {{ $stGroup['noun'] }}.</p>
            </div>
            @else
            <div style="display:flex;flex-wrap:wrap;gap:28px 32px;justify-content:center;align-items:flex-start;">
                @foreach ($stGroup['items'] as $trip)
        <div style="display:flex;align-items:stretch;">
        @php
            // A never-finished draft (saved before a destination was picked)
            // stores the literal placeholder 'Draft' in the destination
            // column — show a clearer label instead of that raw string,
            // which otherwise reads like an actual place name right next
            // to the "Draft" status badge above it.
            $dest     = $trip->trip_name ?: (($trip->destination && $trip->destination !== 'Draft') ? $trip->destination : 'No destination set');
            $fromCode = $trip->origin_code ?? 'MNL';
            $toCode   = $trip->destination_code ?? '';
            $tType    = strtoupper($trip->travel_type ?? 'SOLO');
            $dateFrom = $trip->start_date->format('M j');
            $dateTo   = $trip->end_date->format('M j, Y');
            $days     = $trip->days;
            $displayCost = $trip->total_cost ?? $trip->budget_limit ?? 0;
            $cover    = $trip->cover_image;
            $statusColor = match($trip->status) {
                'active'   => '#22C55E',
                'upcoming' => '#3B82F6',
                default    => '#6B7280',
            };
            $spendColor = $trip->spend_pct >= 80 ? '#DC2626' : ($trip->spend_pct >= 50 ? '#D97706' : '#1A0A00');
        @endphp
        <div wire:key="trip-{{ $trip->id }}" style="background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 4px 18px rgba(45,27,20,0.08);display:flex;flex-direction:column;width:420px;flex-shrink:0;transition:box-shadow .2s,transform .2s;"
             onmouseenter="this.style.boxShadow='0 14px 36px rgba(45,27,20,0.14)';this.style.transform='translateY(-3px)'"
             onmouseleave="this.style.boxShadow='0 4px 18px rgba(45,27,20,0.08)';this.style.transform='none'">
            {{-- Cover image --}}
            <div style="position:relative;height:200px;background:linear-gradient(135deg,#934B19,#C8874A);overflow:hidden;">
                @if($cover)
                <img src="{{ $cover }}" alt="{{ $dest }}"
                     style="width:100%;height:100%;object-fit:cover;display:block;"
                     onerror="this.style.display='none'">
                @endif
                <div style="position:absolute;inset:0;background:linear-gradient(to bottom,rgba(0,0,0,0.15),rgba(0,0,0,0.55));"></div>
                {{-- Stacked badges top-left --}}
                <div style="position:absolute;top:14px;left:14px;display:flex;flex-direction:column;gap:6px;">
                    @php $typeColor = $tType === 'GROUP' ? '#A855F7' : '#14B8A6'; @endphp
                    <span style="background:{{ $typeColor }};color:#fff;font-size:11px;font-weight:700;letter-spacing:0.5px;padding:4px 12px;border-radius:20px;display:inline-block;text-align:center;">{{ $tType }}</span>
                    @php $statusLabel = match($trip->status) { 'active' => 'Ongoing', 'upcoming' => 'Upcoming', 'past' => 'Finished', default => ucfirst($trip->status) }; @endphp
                    <span style="background:{{ $statusColor }};color:#fff;font-size:11px;font-weight:600;padding:4px 12px;border-radius:20px;text-transform:uppercase;display:inline-block;">{{ $statusLabel }}</span>
                </div>
                {{-- Kebab menu top-right --}}
                <div x-data="{ open: false }" style="position:absolute;top:12px;right:12px;">
                    <button @click.stop="open = !open" @click.away="open = false"
                            style="width:34px;height:34px;border-radius:50%;background:rgba(0,0,0,0.35);border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;color:#fff;backdrop-filter:blur(4px);transition:background .18s;"
                            onmouseenter="this.style.background='rgba(0,0,0,0.55)'" onmouseleave="this.style.background='rgba(0,0,0,0.35)'">
                        <i class="fa-solid fa-ellipsis-vertical" style="font-size:14px;"></i>
                    </button>
                    <div x-show="open" x-transition
                         style="position:absolute;top:40px;right:0;background:#fff;border:1px solid #e8ddd4;border-radius:12px;box-shadow:0 10px 30px rgba(45,27,20,.18);min-width:160px;z-index:100;overflow:hidden;">
                        <button wire:click="openEditName({{ $trip->id }})" @click="open=false"
                                style="width:100%;background:none;border:none;padding:11px 16px;font-size:13px;font-weight:500;color:#1c1c19;cursor:pointer;display:flex;align-items:center;gap:8px;text-align:left;border-bottom:1px solid #f5f0eb;font-family:'Hanken Grotesk',sans-serif;"
                                onmouseenter="this.style.background='#f5f0eb'" onmouseleave="this.style.background='none'">
                            <i class="fa-regular fa-pen-to-square" style="font-size:12px;color:#817470;"></i> Edit Trip
                        </button>
                        <button wire:click="confirmDelete({{ $trip->id }})" @click="open=false"
                                style="width:100%;background:none;border:none;padding:11px 16px;font-size:13px;font-weight:500;color:#ba1a1a;cursor:pointer;display:flex;align-items:center;gap:8px;text-align:left;font-family:'Hanken Grotesk',sans-serif;"
                                onmouseenter="this.style.background='#fff5f5'" onmouseleave="this.style.background='none'">
                            <i class="fa-regular fa-trash-can" style="font-size:12px;"></i> Delete Trip
                        </button>
                    </div>
                </div>
                <div style="position:absolute;bottom:12px;left:16px;right:16px;">
                    <div style="font-size:19px;font-weight:700;color:#fff;line-height:1.3;margin-bottom:8px;">
                        {{ $dest }}
                    </div>
                    @php
                        $leg2From = $trip->is_multi_city && $trip->leg2_destination ? $toCode : null;
                        $leg2To   = $trip->is_multi_city && $trip->leg2_destination ? ($trip->leg2_destination_code ?? '') : null;
                        $leg2Sd   = $trip->is_multi_city && $trip->leg2_destination ? $trip->leg2_start_date?->format('M j') : null;
                        $leg2Ed   = $trip->is_multi_city && $trip->leg2_destination ? $trip->leg2_end_date?->format('M j, Y') : null;
                    @endphp
                    <div style="display:flex;flex-direction:column;gap:5px;">
                        @if($fromCode && $toCode)
                        <div style="display:flex;align-items:center;gap:14px;font-size:12px;color:rgba(255,255,255,0.9);flex-wrap:wrap;">
                            <span style="display:flex;align-items:center;gap:6px;"><i class="fa-solid fa-plane" style="font-size:11px;color:#F5C97A;"></i>{{ $fromCode }} to {{ $toCode }}</span>
                            @if($leg2From && $leg2To)
                            <span style="display:flex;align-items:center;gap:6px;"><i class="fa-solid fa-plane" style="font-size:11px;color:#F5C97A;"></i>{{ $leg2From }} to {{ $leg2To }}</span>
                            @endif
                        </div>
                        @endif
                        <div style="display:flex;align-items:center;gap:14px;font-size:12px;color:rgba(255,255,255,0.9);flex-wrap:wrap;">
                            <span style="display:flex;align-items:center;gap:6px;"><i class="fa-regular fa-calendar-days" style="font-size:11px;color:#F5C97A;"></i>{{ $dateFrom }} - {{ $dateTo }}</span>
                            @if($leg2Sd && $leg2Ed)
                            <span style="display:flex;align-items:center;gap:6px;"><i class="fa-regular fa-calendar-days" style="font-size:11px;color:#F5C97A;"></i>{{ $leg2Sd }} - {{ $leg2Ed }}</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div style="padding:20px;">
                <div style="margin-bottom:16px;">
                    <div style="display:flex;align-items:baseline;justify-content:space-between;margin-bottom:5px;">
                        <div style="font-size:11px;font-weight:600;color:#9B8EA0;text-transform:uppercase;letter-spacing:0.5px;">Spent</div>
                        <div style="font-size:12px;font-weight:700;color:{{ $spendColor }};">{{ $trip->spend_pct }}%</div>
                    </div>
                    <div style="height:7px;background:#F3F4F6;border-radius:99px;overflow:hidden;margin-bottom:9px;">
                        <div style="height:100%;border-radius:99px;background:{{ $spendColor }};width:{{ $trip->spend_pct }}%;transition:width .4s ease;"></div>
                    </div>
                    <div style="font-size:23px;font-weight:700;color:#C8874A;">
                        PHP {{ number_format($trip->actual_spent, 0) }}
                        <span style="font-size:13px;font-weight:500;color:#9B8EA0;">/ {{ number_format($displayCost, 0) }}</span>
                    </div>
                </div>

                <div style="display:flex;gap:10px;">
                    <button wire:click="showDetail({{ $trip->id }})"
                            style="flex:1;background:{{ $detailTripId === $trip->id ? '#6A3500' : '#934B19' }};color:#fff;border:none;border-radius:10px;padding:12px 8px;font-size:13px;font-weight:600;cursor:pointer;white-space:nowrap;display:flex;align-items:center;justify-content:center;gap:6px;box-shadow:0 4px 12px rgba(147,75,25,0.18);transition:background .18s;"
                            onmouseenter="this.style.background='#6A3500'" onmouseleave="this.style.background='{{ $detailTripId === $trip->id ? '#6A3500' : '#934B19' }}'">
                        <i class="fa-regular fa-eye" style="font-size:13px;"></i> {{ $detailTripId === $trip->id ? 'Hide Details' : 'View Details' }}
                    </button>
                    <a href="{{ route('expenses.index') }}?trip_id={{ $trip->id }}"
                       style="flex:1;background:transparent;color:#934B19;border:1.5px solid #934B19;border-radius:10px;padding:12px 6px;font-size:13px;font-weight:600;cursor:pointer;text-decoration:none;display:flex;align-items:center;justify-content:center;gap:5px;white-space:nowrap;transition:background .18s;"
                       onmouseenter="this.style.background='#FDF3EB'" onmouseleave="this.style.background='transparent'">
                        <i class="fa-solid fa-receipt" style="font-size:11px;"></i>Add Expense
                    </a>
                </div>
            </div>
        </div>

        @if ($detailTripId === $trip->id)
        @php
            $dt      = $trip;
            $dtDest  = $dt->trip_name ?? $dt->destination;
            $dtTotal = $dt->total_cost ?? $dt->budget_limit ?? 0;
            $sd      = $dt->summary_data ?? [];
            $dtSpendColor = $dt->spend_pct >= 80 ? '#DC2626' : ($dt->spend_pct >= 50 ? '#D97706' : '#1A0A00');
            $rows = [
                'transportation' => ['label' => 'Transportation',  'icon' => 'fa-solid fa-plane',           'color' => '#2563EB'],
                'accommodation'  => ['label' => 'Accommodation',   'icon' => 'fa-solid fa-bed',             'color' => '#934B19'],
                'food'           => ['label' => 'Food & Dining',   'icon' => 'fa-solid fa-utensils',        'color' => '#EA7A1F'],
                'attractions'    => ['label' => 'Attractions',     'icon' => 'fa-solid fa-landmark',        'color' => '#D4A70C'],
                'emergency_fund' => ['label' => 'Emergency Fund',  'icon' => 'fa-solid fa-shield-halved',   'color' => '#2563EB'],
            ];
        @endphp
        <div class="st-panel-pop st-thin-scroll" wire:key="detail-{{ $trip->id }}"
             style="width:320px;flex-shrink:0;margin-left:16px;background:#FDFAF7;border-radius:20px;box-shadow:0 8px 28px rgba(45,27,20,0.10);overflow-y:auto;max-height:{{ 200 + 20 + 62 + 44 + 42 }}px;">

            <div style="padding:18px 20px 6px;display:flex;align-items:center;gap:9px;">
                <div style="width:26px;height:26px;border-radius:8px;background:#F5EBDF;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="fa-solid fa-receipt" style="color:#934B19;font-size:11px;"></i>
                </div>
                <span style="font-size:15px;font-weight:800;color:#1A0A00;">Trip Summary</span>
            </div>

            {{-- Summary rows --}}
            <div style="margin:14px 16px;display:flex;flex-direction:column;gap:8px;">
                @if(empty($sd))
                <div style="padding:24px 14px;text-align:center;background:#fff;border:1px solid #F0E8DF;border-radius:14px;">
                    <i class="fa-solid fa-circle-info" style="font-size:20px;color:#D4C5B5;margin-bottom:8px;display:block;"></i>
                    <div style="font-size:12px;color:#9B8EA0;">Detailed breakdown not available for this trip.</div>
                </div>
                @else
                @foreach ($rows as $key => $meta)
                @php
                    $rowData   = $sd[$key] ?? null;
                    $rowCost   = $rowData['cost'] ?? 0;
                    $rowDetail = $rowData['detail'] ?? null;
                    $rowExtra  = $rowData['extra'] ?? 0;
                    if (!$rowCost && !$rowDetail) continue;
                @endphp
                <div class="st-sum-row" style="padding:12px 13px;background:#fff;border:1px solid #F0E8DF;border-radius:14px;display:flex;align-items:flex-start;gap:11px;">
                    <div style="width:32px;height:32px;border-radius:10px;background:{{ $meta['color'] }}1A;display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:1px;">
                        <i class="{{ $meta['icon'] }}" style="color:{{ $meta['color'] }};font-size:12px;"></i>
                    </div>
                    <div style="flex:1;min-width:0;">
                        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:8px;">
                            <div style="font-size:12px;font-weight:700;color:#1A0A00;margin-bottom:2px;">{{ $meta['label'] }}</div>
                            <div style="font-size:12px;font-weight:700;color:#1A0A00;white-space:nowrap;flex-shrink:0;">PHP {{ number_format($rowCost, 0) }}</div>
                        </div>
                        @if($rowDetail)
                        <div style="font-size:10px;color:#9B8EA0;line-height:1.4;">{{ $rowDetail }}</div>
                        @endif
                        @if($rowExtra > 0)
                        <div style="font-size:10px;color:#934B19;font-weight:600;line-height:1.4;margin-top:2px;">+{{ $rowExtra }} more from suggested itinerary</div>
                        @endif
                    </div>
                </div>
                @endforeach
                @endif
            </div>

            {{-- Total --}}
            <div style="margin:0 16px 18px;padding:14px 16px;background:linear-gradient(135deg,#FDF3EB,#FBEADC);border-radius:14px;">
                <div style="font-size:10px;font-weight:700;color:#9B8EA0;text-transform:uppercase;letter-spacing:0.6px;margin-bottom:4px;">Total Cost</div>
                <div style="font-size:23px;font-weight:800;color:#934B19;">PHP {{ number_format($dtTotal, 0) }}</div>
            </div>
        </div>
        @endif
        </div>
                @endforeach
            </div>
            @endif
        </div>
    </div>
    @endforeach
    @endif

    {{-- Delete Confirmation Modal --}}
    @if ($deleteTripId)
    <div style="position:fixed;inset:0;background:rgba(20,10,4,0.6);backdrop-filter:blur(4px);z-index:2100;display:flex;align-items:center;justify-content:center;padding:20px;">
        <div style="background:#fff;border-radius:24px;width:100%;max-width:380px;box-shadow:0 30px 80px rgba(0,0,0,0.32);overflow:hidden;animation:stModalPop .22s cubic-bezier(.34,1.56,.64,1);">
            {{-- Icon header --}}
            <div style="position:relative;background:radial-gradient(circle at 50% -10%,#FEE2E2,#FEF2F2 70%);padding:36px 28px 24px;text-align:center;overflow:hidden;">
                <div style="width:64px;height:64px;border-radius:18px;background:linear-gradient(135deg,#EF4444,#DC2626);display:flex;align-items:center;justify-content:center;margin:0 auto 18px;box-shadow:0 10px 24px rgba(220,38,38,0.28);position:relative;">
                    <i class="fa-solid fa-trash-can" style="font-size:24px;color:#fff;"></i>
                </div>
                <div style="font-size:19px;font-weight:800;color:#1A0A00;margin-bottom:8px;">Delete Trip?</div>
                <div style="font-size:13px;color:#6B7280;line-height:1.6;max-width:280px;margin:0 auto;">
                    <strong style="color:#1A0A00;">{{ $deleteTripName }}</strong> will be permanently deleted.<br>This action cannot be undone.
                </div>
            </div>
            {{-- Actions --}}
            <div style="display:flex;gap:10px;padding:20px 22px 22px;">
                <button wire:click="cancelDelete"
                        style="flex:1;background:transparent;color:#4B5563;border:1.5px solid #E5E7EB;border-radius:12px;padding:12px 0;font-size:13px;font-weight:700;cursor:pointer;transition:background .18s,border-color .18s;"
                        onmouseenter="this.style.background='#F9FAFB';this.style.borderColor='#D1D5DB'" onmouseleave="this.style.background='transparent';this.style.borderColor='#E5E7EB'">
                    Cancel
                </button>
                <button wire:click="deleteTrip"
                        style="flex:1;background:#DC2626;color:#fff;border:none;border-radius:12px;padding:12px 0;font-size:13px;font-weight:700;cursor:pointer;box-shadow:0 6px 16px rgba(220,38,38,0.28);transition:background .18s,transform .12s;"
                        onmouseenter="this.style.background='#B91C1C'" onmouseleave="this.style.background='#DC2626'"
                        onmousedown="this.style.transform='scale(.97)'" onmouseup="this.style.transform='scale(1)'">
                    <span wire:loading.remove wire:target="deleteTrip"><i class="fa-solid fa-trash-can" style="font-size:11px;margin-right:6px;"></i>Delete</span>
                    <span wire:loading wire:target="deleteTrip"><i class="fa-solid fa-spinner fa-spin"></i></span>
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- Edit Trip Modal --}}
    @if ($editNameTripId)
    <div style="position:fixed;inset:0;background:rgba(20,10,4,0.6);backdrop-filter:blur(4px);z-index:2100;display:flex;align-items:center;justify-content:center;padding:20px;">
        <div x-data="{ type: '{{ $editType }}' }" style="background:#fff;border-radius:24px;width:100%;max-width:400px;box-shadow:0 30px 80px rgba(0,0,0,0.32);padding:26px;max-height:90vh;overflow-y:auto;animation:stModalPop .22s cubic-bezier(.34,1.56,.64,1);">

            {{-- Header --}}
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:24px;">
                <div style="width:42px;height:42px;border-radius:13px;background:linear-gradient(135deg,#B0662A,#934B19);display:flex;align-items:center;justify-content:center;flex-shrink:0;box-shadow:0 6px 16px rgba(147,75,25,0.28);">
                    <i class="fa-regular fa-pen-to-square" style="color:#fff;font-size:16px;"></i>
                </div>
                <span style="font-size:18px;font-weight:800;color:#1c1c19;">Edit Trip</span>
            </div>

            {{-- Trip Name --}}
            <div style="margin-bottom:16px;">
                <div style="font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#817470;margin-bottom:7px;">Trip Name</div>
                <input wire:model="editNameValue" type="text" placeholder="e.g. Solo Getaway to Siargao"
                       style="width:100%;background:#FBF8F5;border:1.5px solid #d3c3be;border-radius:12px;padding:12px 15px;font-size:14px;font-family:'Hanken Grotesk',sans-serif;color:#1c1c19;outline:none;box-sizing:border-box;transition:border-color .18s,background .18s,box-shadow .18s;"
                       onfocus="this.style.borderColor='#934b19';this.style.background='#fff';this.style.boxShadow='0 0 0 4px rgba(147,75,25,0.08)'" onblur="this.style.borderColor='#d3c3be';this.style.background='#FBF8F5';this.style.boxShadow='none'">
            </div>

            {{-- Travel Type --}}
            <div style="margin-bottom:16px;">
                <div style="font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#817470;margin-bottom:7px;">Type</div>
                <div style="display:flex;gap:8px;">
                    @foreach(['Solo','Group'] as $typeOpt)
                    <button type="button"
                            @click="type = '{{ $typeOpt }}'" wire:click="$set('editType', '{{ $typeOpt }}')"
                            :style="type === '{{ $typeOpt }}'
                                ? 'flex:1;padding:10px 4px;border-radius:10px;font-size:12px;font-weight:700;cursor:pointer;font-family:Hanken Grotesk,sans-serif;border:1.5px solid #934b19;background:#FDF3EB;color:#934b19;transition:.18s;'
                                : 'flex:1;padding:10px 4px;border-radius:10px;font-size:12px;font-weight:700;cursor:pointer;font-family:Hanken Grotesk,sans-serif;border:1.5px solid #e8ddd4;background:#fff;color:#817470;transition:.18s;'">
                        {{ $typeOpt }}
                    </button>
                    @endforeach
                </div>
            </div>

            {{-- Group members (only when Group type) --}}
            <div x-show="type === 'Group'" x-transition style="margin-bottom:16px;">
                <div style="font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#817470;margin-bottom:8px;">Group Members</div>

                {{-- Already-saved members --}}
                @foreach($savedMembers as $sm)
                <div style="display:flex;align-items:center;gap:10px;background:#F8F5F2;border-radius:12px;padding:9px 12px;margin-bottom:7px;">
                    <div style="width:30px;height:30px;border-radius:50%;background:#c3b3a6;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:12px;font-weight:700;color:#fff;">{{ strtoupper(substr($sm['name'],0,1)) }}</div>
                    <div style="flex:1;min-width:0;">
                        <div style="font-size:13px;font-weight:600;color:#1c1c19;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $sm['name'] }}</div>
                        <div style="font-size:11px;color:#9B8EA0;">{{ $sm['email'] }}</div>
                    </div>
                    <button wire:click="removeSavedMember({{ $sm['id'] }})" type="button"
                            style="flex-shrink:0;width:28px;height:28px;border:none;background:#FEE2E2;border-radius:8px;cursor:pointer;display:flex;align-items:center;justify-content:center;color:#DC2626;transition:background .18s;"
                            onmouseenter="this.style.background='#FECACA'" onmouseleave="this.style.background='#FEE2E2'">
                        <i class="fa-solid fa-xmark" style="font-size:11px;"></i>
                    </button>
                </div>
                @endforeach

                {{-- Pending (newly added) members --}}
                @foreach($pendingMembers as $pi => $pm)
                <div style="display:flex;align-items:center;gap:10px;background:#FDF3EB;border-radius:12px;padding:9px 12px;margin-bottom:7px;border:1px solid #e8ddd4;">
                    <div style="width:30px;height:30px;border-radius:50%;background:#934b19;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:12px;font-weight:700;color:#fff;">{{ strtoupper(substr($pm['name'],0,1)) }}</div>
                    <div style="flex:1;min-width:0;">
                        <div style="font-size:13px;font-weight:600;color:#1c1c19;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $pm['name'] }}</div>
                        <div style="font-size:11px;color:#9B8EA0;">{{ $pm['email'] }}</div>
                    </div>
                    <button wire:click="removePendingMember({{ $pi }})" type="button"
                            style="flex-shrink:0;width:28px;height:28px;border:none;background:#FEE2E2;border-radius:8px;cursor:pointer;display:flex;align-items:center;justify-content:center;color:#DC2626;transition:background .18s;"
                            onmouseenter="this.style.background='#FECACA'" onmouseleave="this.style.background='#FEE2E2'">
                        <i class="fa-solid fa-xmark" style="font-size:11px;"></i>
                    </button>
                </div>
                @endforeach

                {{-- Email lookup input --}}
                <div style="display:flex;gap:8px;margin-top:6px;">
                    <input wire:model="memberEmail" type="email" placeholder="Enter member's email address"
                           wire:keydown.enter.prevent="lookupMember"
                           style="flex:1;background:#FBF8F5;border:1.5px solid {{ $memberError ? '#DC2626' : '#d3c3be' }};border-radius:11px;padding:10px 13px;font-size:13px;font-family:'Hanken Grotesk',sans-serif;color:#1c1c19;outline:none;box-sizing:border-box;transition:border-color .18s,background .18s;"
                           onfocus="this.style.borderColor='#934b19';this.style.background='#fff'" onblur="this.style.borderColor='{{ $memberError ? '#DC2626' : '#d3c3be' }}';this.style.background='#FBF8F5'">
                    <button wire:click="lookupMember" type="button"
                            style="flex-shrink:0;background:#934b19;color:#fff;border:none;border-radius:11px;padding:10px 16px;font-size:12px;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:5px;font-family:'Hanken Grotesk',sans-serif;box-shadow:0 4px 12px rgba(147,75,25,0.2);transition:background .18s;"
                            onmouseenter="this.style.background='#6A3500'" onmouseleave="this.style.background='#934b19'">
                        <i class="fa-solid fa-plus" style="font-size:10px;"></i> Add
                    </button>
                </div>
                @if($memberError)
                <div style="margin-top:6px;font-size:12px;color:#DC2626;">{{ $memberError }}</div>
                @endif
                <div style="margin-top:6px;font-size:11px;color:#9B8EA0;">Only registered users can be added as members.</div>
            </div>

            {{-- Status --}}
            <div style="margin-bottom:24px;">
                <div style="font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#817470;margin-bottom:7px;">Status</div>
                <div style="display:flex;gap:8px;">
                    @foreach(['upcoming'=>'Upcoming','active'=>'Ongoing','past'=>'Finished'] as $statusVal => $statusLabel)
                    <button type="button" wire:click="$set('editStatus', '{{ $statusVal }}')"
                            style="flex:1;padding:10px 4px;border-radius:10px;font-size:12px;font-weight:700;cursor:pointer;font-family:'Hanken Grotesk',sans-serif;transition:.18s;border:1.5px solid {{ $editStatus === $statusVal ? '#934b19' : '#e8ddd4' }};background:{{ $editStatus === $statusVal ? '#FDF3EB' : '#fff' }};color:{{ $editStatus === $statusVal ? '#934b19' : '#817470' }};">
                        {{ $statusLabel }}
                    </button>
                    @endforeach
                </div>
            </div>

            {{-- Actions --}}
            <div style="display:flex;gap:10px;">
                <button wire:click="cancelEditName"
                        style="flex:1;background:transparent;color:#4B5563;border:1.5px solid #E5E7EB;border-radius:12px;padding:12px 0;font-size:13px;font-weight:700;cursor:pointer;font-family:'Hanken Grotesk',sans-serif;transition:background .18s,border-color .18s;"
                        onmouseenter="this.style.background='#F9FAFB';this.style.borderColor='#D1D5DB'" onmouseleave="this.style.background='transparent';this.style.borderColor='#E5E7EB'">
                    Cancel
                </button>
                <button wire:click="saveEditName"
                        style="flex:1;background:#934B19;color:#fff;border:none;border-radius:12px;padding:12px 0;font-size:13px;font-weight:700;cursor:pointer;font-family:'Hanken Grotesk',sans-serif;box-shadow:0 6px 16px rgba(147,75,25,0.26);transition:background .18s,transform .12s;"
                        onmouseenter="this.style.background='#6A3500'" onmouseleave="this.style.background='#934B19'"
                        onmousedown="this.style.transform='scale(.97)'" onmouseup="this.style.transform='scale(1)'">
                    Save
                </button>
            </div>

        </div>
    </div>
    @endif
</div>
