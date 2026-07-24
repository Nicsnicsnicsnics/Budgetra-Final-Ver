<div style="display:flex;flex-direction:column;flex:1;">

    @if ($trips->isEmpty())
    <div class="empty-state-center" style="min-height:80vh;">
        <div style="width:64px;height:64px;border-radius:16px;background:#934B19;display:flex;align-items:center;justify-content:center;margin-bottom:24px;">
            <i class="fa-solid fa-suitcase-rolling" style="font-size:28px;color:#fff;"></i>
        </div>
        @if (!auth()->user()?->userProfile)
        <h2 style="font-weight:700;font-size:22px;margin-bottom:10px;color:#1A0A00;">Set up your profile first</h2>
        <p style="color:#9B8EA0;margin-bottom:28px;font-size:14px;max-width:320px;line-height:1.6;">Complete your travel profile before planning a trip.</p>
        <a href="{{ route('profile.setup') }}" style="display:inline-flex;align-items:center;gap:10px;background:#934B19;color:#fff;border-radius:30px;padding:14px 32px;font-size:13px;font-weight:700;letter-spacing:.06em;text-decoration:none;text-transform:uppercase;">
            <i class="fa-solid fa-user"></i> Set Up Your Profile First
        </a>
        @else
        <h2 style="font-weight:700;font-size:22px;margin-bottom:10px;color:#1A0A00;">No saved trips yet</h2>
        <p style="color:#9B8EA0;margin-bottom:28px;font-size:14px;max-width:320px;line-height:1.6;">Plan a trip first to see your saved and draft trips.</p>
        <a href="{{ route('trips.plan') }}" style="display:inline-flex;align-items:center;gap:10px;background:#934B19;color:#fff;border-radius:30px;padding:14px 32px;font-size:13px;font-weight:700;letter-spacing:.06em;text-decoration:none;text-transform:uppercase;">
            <i class="fa-solid fa-plane"></i> Plan Your First Trip
        </a>
        @endif
    </div>
    @else
    <div style="display:flex;flex-wrap:wrap;gap:20px;justify-content:center;">
        @foreach ($trips as $trip)
        @php
            $dest     = $trip->trip_name ?? $trip->destination ?? 'Unknown';
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
        @endphp
        <div wire:key="trip-{{ $trip->id }}" style="background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,0.08);display:flex;flex-direction:column;width:360px;flex-shrink:0;">
            {{-- Cover image --}}
            <div style="position:relative;height:160px;background:linear-gradient(135deg,#934B19,#C8874A);overflow:hidden;">
                @if($cover)
                <img src="{{ $cover }}" alt="{{ $dest }}"
                     style="width:100%;height:100%;object-fit:cover;display:block;"
                     onerror="this.style.display='none'">
                @endif
                <div style="position:absolute;inset:0;background:linear-gradient(to bottom,rgba(0,0,0,0.15),rgba(0,0,0,0.55));"></div>
                {{-- Stacked badges top-left --}}
                <div style="position:absolute;top:12px;left:12px;display:flex;flex-direction:column;gap:5px;">
                    @php $typeColor = $tType === 'GROUP' ? '#A855F7' : '#14B8A6'; @endphp
                    <span style="background:{{ $typeColor }};color:#fff;font-size:10px;font-weight:700;letter-spacing:0.5px;padding:3px 10px;border-radius:20px;display:inline-block;text-align:center;">{{ $tType }}</span>
                    @php $statusLabel = match($trip->status) { 'active' => 'Ongoing', 'upcoming' => 'Upcoming', default => ucfirst($trip->status) }; @endphp
                    <span style="background:{{ $statusColor }};color:#fff;font-size:10px;font-weight:600;padding:3px 10px;border-radius:20px;text-transform:uppercase;display:inline-block;">{{ $statusLabel }}</span>
                </div>
                {{-- Kebab menu top-right --}}
                <div x-data="{ open: false }" style="position:absolute;top:10px;right:10px;">
                    <button @click.stop="open = !open" @click.away="open = false"
                            style="width:30px;height:30px;border-radius:50%;background:rgba(0,0,0,0.35);border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;color:#fff;backdrop-filter:blur(4px);">
                        <i class="fa-solid fa-ellipsis-vertical" style="font-size:13px;"></i>
                    </button>
                    <div x-show="open" x-transition
                         style="position:absolute;top:36px;right:0;background:#fff;border:1px solid #e8ddd4;border-radius:10px;box-shadow:0 8px 24px rgba(45,27,20,.15);min-width:160px;z-index:100;overflow:hidden;">
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
                <div style="position:absolute;bottom:10px;left:14px;right:14px;">
                    <div style="font-size:16px;font-weight:700;color:#fff;line-height:1.3;margin-bottom:6px;">
                        {{ $dest }}
                    </div>
                    <div style="display:flex;flex-direction:column;gap:4px;">
                        @if($fromCode)
                        <div style="display:flex;align-items:center;gap:5px;font-size:11px;color:rgba(255,255,255,0.9);">
                            <i class="fa-solid fa-plane" style="font-size:10px;color:#F5C97A;"></i>
                            <span>{{ $fromCode }} to {{ $toCode }}</span>
                        </div>
                        @endif
                        <div style="display:flex;align-items:center;gap:5px;font-size:11px;color:rgba(255,255,255,0.9);">
                            <i class="fa-regular fa-calendar-days" style="font-size:10px;color:#F5C97A;"></i>
                            <span>{{ $dateFrom }} - {{ $dateTo }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div style="padding:16px;">
                <div style="margin-bottom:14px;">
                    <div style="font-size:10px;font-weight:600;color:#9B8EA0;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:2px;">Total Cost</div>
                    <div style="font-size:20px;font-weight:700;color:#C8874A;">PHP {{ number_format($displayCost, 0) }}</div>
                </div>

                <div style="display:flex;gap:8px;">
                    <button wire:click="showDetail({{ $trip->id }})"
                            style="flex:1;background:#934B19;color:#fff;border:none;border-radius:10px;padding:10px 8px;font-size:12px;font-weight:600;cursor:pointer;white-space:nowrap;display:flex;align-items:center;justify-content:center;gap:6px;">
                        <i class="fa-regular fa-eye" style="font-size:12px;"></i> View Details
                    </button>
                    <a href="{{ route('expenses.index') }}?trip_id={{ $trip->id }}"
                       style="flex:1;background:transparent;color:#934B19;border:1.5px solid #934B19;border-radius:10px;padding:10px 6px;font-size:12px;font-weight:600;cursor:pointer;text-decoration:none;display:flex;align-items:center;justify-content:center;gap:4px;white-space:nowrap;">
                        <i class="fa-solid fa-receipt" style="font-size:10px;"></i>Add Expense
                    </a>
                </div>
            </div>
        </div>
        @endforeach
    </div>
    @endif

    {{-- Trip Summary Modal --}}
    @if ($detailTrip)
    @php
        $dt      = $detailTrip;
        $dtDays  = $dt->days;
        $dtDest  = $dt->trip_name ?? $dt->destination;
        $dtType  = ucfirst(strtolower($dt->travel_type ?? 'Solo'));
        $dtFrom  = $dt->start_date->format('M j');
        $dtTo    = $dt->end_date->format('M j, Y');
        $dtTotal = $dt->total_cost ?? $dt->budget_limit ?? 0;
        $sd      = $dt->summary_data ?? [];

        $rows = [
            'transportation' => ['label' => 'Transportation',  'icon' => 'fa-solid fa-plane'],
            'accommodation'  => ['label' => 'Accommodation',   'icon' => 'fa-solid fa-bed'],
            'food'           => ['label' => 'Food & Dining',   'icon' => 'fa-solid fa-utensils'],
            'attractions'    => ['label' => 'Attractions',     'icon' => 'fa-solid fa-landmark'],
            'emergency_fund' => ['label' => 'Emergency Fund',  'icon' => 'fa-solid fa-shield-halved'],
        ];
    @endphp
    <div wire:click.self="closeDetail"
         style="position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:2000;display:flex;align-items:center;justify-content:center;padding:20px;">
        <div style="background:#FDFAF7;border-radius:20px;width:100%;max-width:420px;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,0.2);">

            {{-- Modal header --}}
            <div style="padding:20px 22px 16px;">
                <span style="font-size:16px;font-weight:700;color:#1A0A00;">Trip Summary</span>
            </div>

            {{-- Trip title row --}}
            <div style="padding:0 22px 18px;display:flex;align-items:center;gap:12px;">
                <div style="width:42px;height:42px;border-radius:12px;background:#FFF3E0;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="fa-solid fa-plane-departure" style="color:#C8874A;font-size:18px;"></i>
                </div>
                <div>
                    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                        <span style="font-size:15px;font-weight:700;color:#1A0A00;">{{ $dtDest }}</span>
                    </div>
                </div>
            </div>

            {{-- Summary rows --}}
            <div style="margin:0 16px 16px;background:#fff;border-radius:14px;overflow:hidden;border:1px solid #F0E8DF;">
                <div style="padding:12px 16px 6px;font-size:11px;font-weight:700;color:#9B8EA0;letter-spacing:0.6px;text-transform:uppercase;border-bottom:1px solid #F5EFE8;">Summary</div>
                @if(empty($sd))
                <div style="padding:20px 16px;text-align:center;">
                    <i class="fa-solid fa-circle-info" style="font-size:22px;color:#D4C5B5;margin-bottom:8px;display:block;"></i>
                    <div style="font-size:13px;color:#9B8EA0;">Detailed breakdown not available for this trip.</div>
                </div>
                @else
                @foreach ($rows as $key => $meta)
                @php
                    $rowData   = $sd[$key] ?? null;
                    $rowCost   = $rowData['cost'] ?? 0;
                    $rowDetail = $rowData['detail'] ?? null;
                    if (!$rowCost && !$rowDetail) continue;
                @endphp
                <div style="padding:14px 16px;border-bottom:1px solid #F5EFE8;display:flex;align-items:flex-start;justify-content:space-between;gap:12px;">
                    <div style="flex:1;min-width:0;">
                        <div style="font-size:13px;font-weight:600;color:#1A0A00;margin-bottom:2px;">{{ $meta['label'] }}</div>
                        @if($rowDetail)
                        <div style="font-size:11px;color:#9B8EA0;line-height:1.4;">{{ $rowDetail }}</div>
                        @endif
                    </div>
                    <div style="font-size:13px;font-weight:600;color:#1A0A00;white-space:nowrap;flex-shrink:0;">
                        PHP {{ number_format($rowCost, 0) }}
                    </div>
                </div>
                @endforeach
                @endif
            </div>

            {{-- Total + Close --}}
            <div style="padding:0 22px 24px;display:flex;align-items:center;justify-content:space-between;gap:12px;">
                <div>
                    <div style="font-size:11px;font-weight:700;color:#9B8EA0;text-transform:uppercase;letter-spacing:0.6px;margin-bottom:4px;">Total Cost</div>
                    <div style="font-size:26px;font-weight:800;color:#C8874A;">PHP {{ number_format($dtTotal, 0) }}</div>
                </div>
                <button wire:click="closeDetail"
                        style="background:#934B19;color:#fff;border:none;border-radius:10px;padding:10px 22px;font-size:13px;font-weight:600;cursor:pointer;white-space:nowrap;">
                    Close
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- Delete Confirmation Modal --}}
    @if ($deleteTripId)
    <div style="position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:2100;display:flex;align-items:center;justify-content:center;padding:20px;">
        <div style="background:#fff;border-radius:20px;width:100%;max-width:360px;box-shadow:0 20px 60px rgba(0,0,0,0.2);overflow:hidden;">
            {{-- Icon header --}}
            <div style="background:#FEF2F2;padding:28px 24px 20px;text-align:center;">
                <div style="width:52px;height:52px;border-radius:50%;background:#FEE2E2;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;">
                    <i class="fa-solid fa-trash-can" style="font-size:22px;color:#DC2626;"></i>
                </div>
                <div style="font-size:17px;font-weight:700;color:#1A0A00;margin-bottom:6px;">Delete Trip?</div>
                <div style="font-size:13px;color:#6B7280;line-height:1.5;">
                    <strong>{{ $deleteTripName }} trip</strong> will be permanently deleted.<br>This action cannot be undone.
                </div>
            </div>
            {{-- Actions --}}
            <div style="display:flex;gap:10px;padding:18px 20px;">
                <button wire:click="cancelDelete"
                        style="flex:1;background:transparent;color:#6B7280;border:1.5px solid #E5E7EB;border-radius:10px;padding:11px 0;font-size:13px;font-weight:600;cursor:pointer;">
                    Cancel
                </button>
                <button wire:click="deleteTrip"
                        style="flex:1;background:#DC2626;color:#fff;border:none;border-radius:10px;padding:11px 0;font-size:13px;font-weight:600;cursor:pointer;">
                    <span wire:loading.remove wire:target="deleteTrip">Delete</span>
                    <span wire:loading wire:target="deleteTrip"><i class="fa-solid fa-spinner fa-spin"></i></span>
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- Edit Trip Modal --}}
    @if ($editNameTripId)
    <div style="position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:2100;display:flex;align-items:center;justify-content:center;padding:20px;">
        <div x-data="{ type: '{{ $editType }}' }" style="background:#fff;border-radius:20px;width:100%;max-width:400px;box-shadow:0 20px 60px rgba(0,0,0,0.2);padding:24px;max-height:90vh;overflow-y:auto;">

            {{-- Header --}}
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:20px;">
                <div style="width:36px;height:36px;border-radius:10px;background:#FDF3EB;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="fa-regular fa-pen-to-square" style="color:#934B19;font-size:14px;"></i>
                </div>
                <span style="font-size:16px;font-weight:700;color:#1c1c19;">Edit Trip</span>
            </div>

            {{-- Trip Name --}}
            <div style="margin-bottom:14px;">
                <div style="font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#817470;margin-bottom:6px;">Trip Name</div>
                <input wire:model="editNameValue" type="text" placeholder="e.g. Solo Getaway to Siargao"
                       style="width:100%;border:1.5px solid #d3c3be;border-radius:10px;padding:11px 14px;font-size:14px;font-family:'Hanken Grotesk',sans-serif;color:#1c1c19;outline:none;box-sizing:border-box;"
                       onfocus="this.style.borderColor='#934b19'" onblur="this.style.borderColor='#d3c3be'">
            </div>

            {{-- Travel Type --}}
            <div style="margin-bottom:14px;">
                <div style="font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#817470;margin-bottom:6px;">Type</div>
                <div style="display:flex;gap:6px;">
                    @foreach(['Solo','Group'] as $typeOpt)
                    <button type="button"
                            @click="type = '{{ $typeOpt }}'" wire:click="$set('editType', '{{ $typeOpt }}')"
                            :style="type === '{{ $typeOpt }}'
                                ? 'flex:1;padding:8px 4px;border-radius:8px;font-size:11px;font-weight:600;cursor:pointer;font-family:Hanken Grotesk,sans-serif;border:1.5px solid #934b19;background:#FDF3EB;color:#934b19;'
                                : 'flex:1;padding:8px 4px;border-radius:8px;font-size:11px;font-weight:600;cursor:pointer;font-family:Hanken Grotesk,sans-serif;border:1.5px solid #e8ddd4;background:#fff;color:#817470;'">
                        {{ $typeOpt }}
                    </button>
                    @endforeach
                </div>
            </div>

            {{-- Group members (only when Group type) --}}
            <div x-show="type === 'Group'" x-transition style="margin-bottom:14px;">
                <div style="font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#817470;margin-bottom:8px;">Group Members</div>

                {{-- Already-saved members --}}
                @foreach($savedMembers as $sm)
                <div style="display:flex;align-items:center;gap:10px;background:#F5F5F5;border-radius:10px;padding:8px 12px;margin-bottom:6px;">
                    <div style="width:28px;height:28px;border-radius:50%;background:#d3c3be;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:12px;font-weight:700;color:#fff;">{{ strtoupper(substr($sm['name'],0,1)) }}</div>
                    <div style="flex:1;min-width:0;">
                        <div style="font-size:13px;font-weight:600;color:#1c1c19;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $sm['name'] }}</div>
                        <div style="font-size:11px;color:#9B8EA0;">{{ $sm['email'] }}</div>
                    </div>
                    <button wire:click="removeSavedMember({{ $sm['id'] }})" type="button"
                            style="flex-shrink:0;width:26px;height:26px;border:none;background:#FEE2E2;border-radius:7px;cursor:pointer;display:flex;align-items:center;justify-content:center;color:#DC2626;">
                        <i class="fa-solid fa-xmark" style="font-size:11px;"></i>
                    </button>
                </div>
                @endforeach

                {{-- Pending (newly added) members --}}
                @foreach($pendingMembers as $pi => $pm)
                <div style="display:flex;align-items:center;gap:10px;background:#FDF3EB;border-radius:10px;padding:8px 12px;margin-bottom:6px;border:1px solid #e8ddd4;">
                    <div style="width:28px;height:28px;border-radius:50%;background:#934b19;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:12px;font-weight:700;color:#fff;">{{ strtoupper(substr($pm['name'],0,1)) }}</div>
                    <div style="flex:1;min-width:0;">
                        <div style="font-size:13px;font-weight:600;color:#1c1c19;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $pm['name'] }}</div>
                        <div style="font-size:11px;color:#9B8EA0;">{{ $pm['email'] }}</div>
                    </div>
                    <button wire:click="removePendingMember({{ $pi }})" type="button"
                            style="flex-shrink:0;width:26px;height:26px;border:none;background:#FEE2E2;border-radius:7px;cursor:pointer;display:flex;align-items:center;justify-content:center;color:#DC2626;">
                        <i class="fa-solid fa-xmark" style="font-size:11px;"></i>
                    </button>
                </div>
                @endforeach

                {{-- Email lookup input --}}
                <div style="display:flex;gap:6px;margin-top:4px;">
                    <input wire:model="memberEmail" type="email" placeholder="Enter member's email address"
                           wire:keydown.enter.prevent="lookupMember"
                           style="flex:1;border:1.5px solid {{ $memberError ? '#DC2626' : '#d3c3be' }};border-radius:10px;padding:9px 12px;font-size:13px;font-family:'Hanken Grotesk',sans-serif;color:#1c1c19;outline:none;box-sizing:border-box;"
                           onfocus="this.style.borderColor='#934b19'" onblur="this.style.borderColor='{{ $memberError ? '#DC2626' : '#d3c3be' }}'">
                    <button wire:click="lookupMember" type="button"
                            style="flex-shrink:0;background:#934b19;color:#fff;border:none;border-radius:10px;padding:9px 14px;font-size:12px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:5px;font-family:'Hanken Grotesk',sans-serif;">
                        <i class="fa-solid fa-plus" style="font-size:10px;"></i> Add
                    </button>
                </div>
                @if($memberError)
                <div style="margin-top:5px;font-size:12px;color:#DC2626;">{{ $memberError }}</div>
                @endif
                <div style="margin-top:5px;font-size:11px;color:#9B8EA0;">Only registered users can be added as members.</div>
            </div>

            {{-- Status --}}
            <div style="margin-bottom:22px;">
                <div style="font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#817470;margin-bottom:6px;">Status</div>
                <div style="display:flex;gap:6px;">
                    @foreach(['upcoming'=>'Upcoming','active'=>'Ongoing'] as $statusVal => $statusLabel)
                    <button type="button" wire:click="$set('editStatus', '{{ $statusVal }}')"
                            style="flex:1;padding:8px 4px;border-radius:8px;font-size:11px;font-weight:600;cursor:pointer;font-family:'Hanken Grotesk',sans-serif;border:1.5px solid {{ $editStatus === $statusVal ? '#934b19' : '#e8ddd4' }};background:{{ $editStatus === $statusVal ? '#FDF3EB' : '#fff' }};color:{{ $editStatus === $statusVal ? '#934b19' : '#817470' }};">
                        {{ $statusLabel }}
                    </button>
                    @endforeach
                </div>
            </div>

            {{-- Actions --}}
            <div style="display:flex;gap:10px;">
                <button wire:click="cancelEditName"
                        style="flex:1;background:transparent;color:#6B7280;border:1.5px solid #E5E7EB;border-radius:10px;padding:11px 0;font-size:13px;font-weight:600;cursor:pointer;font-family:'Hanken Grotesk',sans-serif;">
                    Cancel
                </button>
                <button wire:click="saveEditName"
                        style="flex:1;background:#934B19;color:#fff;border:none;border-radius:10px;padding:11px 0;font-size:13px;font-weight:700;cursor:pointer;font-family:'Hanken Grotesk',sans-serif;"
                        onmouseenter="this.style.background='#783603'" onmouseleave="this.style.background='#934B19'">
                    Save
                </button>
            </div>

        </div>
    </div>
    @endif
</div>
