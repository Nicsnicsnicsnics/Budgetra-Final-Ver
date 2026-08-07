<div style="display:flex;flex-direction:column;flex:1;">
    @php $fmtDate = fn ($d, $f) => str_replace('Sep', 'Sept', $d->format($f)); @endphp
    @if ($trips->isEmpty() && !$search)
    {{-- Pure empty state — no header, no stats --}}
    <div class="empty-state-center" style="min-height:80vh;">
        <div style="width:64px;height:64px;border-radius:16px;background:var(--primary);display:flex;align-items:center;justify-content:center;margin-bottom:24px;">
            <i class="fa-solid fa-layer-group" style="font-size:28px;color:#fff;"></i>
        </div>
        @if (!auth()->user()?->userProfile)
        <h2 style="font-weight:700;font-size:22px;margin-bottom:10px;color:var(--dark);">Set up your profile first</h2>
        <p style="color:var(--muted);margin-bottom:28px;font-size:14px;max-width:320px;line-height:1.6;">Complete your travel profile before planning a trip.</p>
        <a href="{{ route('profile.setup') }}" style="display:inline-flex;align-items:center;gap:10px;background:var(--primary);color:#fff;border-radius:30px;padding:14px 32px;font-size:13px;font-weight:700;letter-spacing:.06em;text-decoration:none;text-transform:uppercase;">
            <i class="fa-solid fa-user"></i> Set Up Your Profile First
        </a>
        @else
        <h2 style="font-weight:700;font-size:22px;margin-bottom:10px;color:var(--dark);">No trips planned yet</h2>
        <p style="color:var(--muted);margin-bottom:28px;font-size:14px;max-width:320px;line-height:1.6;">Plan a trip first to view active and past trips, and compare trips.</p>
        <a href="{{ route('trips.plan') }}" style="display:inline-flex;align-items:center;gap:10px;background:var(--primary);color:#fff;border-radius:30px;padding:14px 32px;font-size:13px;font-weight:700;letter-spacing:.06em;text-decoration:none;text-transform:uppercase;">
            <i class="fa-solid fa-plane"></i> Plan Your First Trip
        </a>
        @endif
    </div>
    @else

    {{-- Trips accordion --}}
    @php
        $mthActiveTrips = $trips->whereIn('status', ['active', 'upcoming']);
        $mthPastTrips   = $trips->where('status', 'past');
        $mthGroups = [
            ['key' => 'active', 'label' => 'Active Trips', 'icon' => 'fa-solid fa-suitcase-rolling',  'items' => $mthActiveTrips],
            ['key' => 'past',   'label' => 'Past Trips',   'icon' => 'fa-solid fa-clock-rotate-left', 'items' => $mthPastTrips],
        ];
    @endphp
    @foreach ($mthGroups as $mthGroup)
    <div x-data="{ open: {{ $mthGroup['key'] === 'active' ? 'true' : 'false' }} }" style="margin-bottom:20px;background:var(--bg-white);border:1.5px solid var(--border);border-radius:999px;transition:border-radius .2s,border-color .2s;"
         :style="open ? 'border-radius:22px;border-color:var(--primary);' : ''">
        <button @click="open=!open" type="button" style="width:100%;display:flex;align-items:center;justify-content:space-between;padding:14px 20px;background:none;border:none;cursor:pointer;">
            <div style="display:flex;align-items:center;gap:12px;">
                <div style="width:32px;height:32px;border-radius:10px;background:var(--primary);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="{{ $mthGroup['icon'] }}" style="color:#fff;font-size:13px;"></i>
                </div>
                <span style="font-size:15px;font-weight:700;color:var(--dark);">{{ $mthGroup['label'] }}</span>
                @php $mthCount = $mthGroup['items']->count(); @endphp
                <span style="font-size:11px;font-weight:800;{{ $mthCount > 0 ? 'color:#fff;background:var(--primary);' : 'color:var(--muted);background:var(--bg);' }}border-radius:99px;min-width:22px;height:22px;padding:0 7px;display:inline-flex;align-items:center;justify-content:center;line-height:1;">{{ $mthCount }}</span>
            </div>
            <i class="fa-solid fa-chevron-down" style="font-size:12px;color:var(--muted);transition:.2s;" :style="open?'transform:rotate(180deg)':''"></i>
        </button>
        <div x-show="open" x-transition style="padding:16px 18px 20px;border-top:1px solid var(--border);">
            @if ($mthGroup['items']->isEmpty())
            <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:40px 20px;">
                <div style="width:56px;height:56px;border-radius:16px;background:var(--primary);display:flex;align-items:center;justify-content:center;margin-bottom:18px;">
                    <i class="{{ $mthGroup['icon'] }}" style="font-size:24px;color:#fff;"></i>
                </div>
                <h3 style="font-weight:700;font-size:17px;margin:0 0 6px;color:var(--dark);">No {{ $mthGroup['label'] }} yet</h3>
                <p style="color:var(--muted);font-size:13px;max-width:280px;line-height:1.6;margin:0;">
                    @if ($mthGroup['key'] === 'active')
                    Plan a trip first to see your active trips.
                    @else
                    Plan a trip first to see your past trips.
                    @endif
                </p>
            </div>
            @else
            <div style="display:flex;flex-wrap:wrap;gap:20px;justify-content:center;">
                @foreach ($mthGroup['items'] as $trip)
                @php
                    $tType      = strtoupper($trip->travel_type ?? 'Solo');
                    $pct        = min(100, $trip->pct_used);
                    $typeColor  = $tType === 'GROUP' ? '#A855F7' : '#14B8A6';
                    $statusColor = match ($trip->status) {
                        'active'   => '#22C55E',
                        'upcoming' => '#3B82F6',
                        default    => 'var(--muted)',
                    };
                    $statusLabel = match ($trip->status) { 'active' => 'Ongoing', 'upcoming' => 'Upcoming', 'past' => 'Finished', default => ucfirst($trip->status) };
                    $isPickedForCompare = in_array($trip->id, $compareIds, true);
                @endphp
                <div class="card" style="overflow:hidden;border-radius:14px;width:340px;flex-shrink:0;{{ $isPickedForCompare ? 'border-color:var(--primary);box-shadow:0 0 0 2px var(--primary);' : '' }}">
                    <div style="position:relative;height:200px;background:linear-gradient(135deg,var(--primary),#C8874A);">
                        @if ($trip->cover_image)
                        <img src="{{ $trip->cover_image }}" style="width:100%;height:100%;object-fit:cover;display:block;" alt="{{ $trip->destination }}">
                        @endif
                        <div style="position:absolute;inset:0;background:linear-gradient(to bottom,rgba(0,0,0,0.15),rgba(0,0,0,0.55));"></div>

                        {{-- Stacked badges top-left --}}
                        <div style="position:absolute;top:14px;left:14px;display:flex;flex-direction:column;gap:6px;">
                            <span style="background:{{ $typeColor }};color:#fff;font-size:11px;font-weight:700;letter-spacing:0.5px;padding:4px 12px;border-radius:20px;display:inline-block;text-align:center;">{{ $tType }}</span>
                            <span style="background:{{ $statusColor }};color:#fff;font-size:11px;font-weight:600;padding:4px 12px;border-radius:20px;text-transform:uppercase;display:inline-block;">{{ $statusLabel }}</span>
                        </div>

                        {{-- Trip info overlay --}}
                        <div style="position:absolute;bottom:12px;left:16px;right:16px;">
                            <div style="font-size:19px;font-weight:700;color:#fff;line-height:1.3;margin-bottom:8px;">
                                {{ $trip->trip_name ?? $trip->destination }}
                            </div>
                            <div style="display:flex;flex-direction:column;gap:5px;">
                                <div style="display:flex;align-items:center;gap:14px;font-size:12px;color:rgba(255,255,255,0.9);flex-wrap:wrap;">
                                    <span style="display:flex;align-items:center;gap:6px;"><i class="fa-solid fa-plane" style="font-size:11px;color:#F5C97A;"></i>{{ $trip->origin_code ?? 'MNL' }} to {{ $trip->destination_code ?? '—' }}</span>
                                </div>
                                <div style="display:flex;align-items:center;gap:14px;font-size:12px;color:rgba(255,255,255,0.9);flex-wrap:wrap;">
                                    <span style="display:flex;align-items:center;gap:6px;"><i class="fa-regular fa-calendar-days" style="font-size:11px;color:#F5C97A;"></i>{{ $fmtDate($trip->start_date, 'M j') }} - {{ $fmtDate($trip->end_date, 'M j, Y') }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div style="padding:18px 20px;">
                        <div style="display:flex;justify-content:space-between;align-items:baseline;margin-bottom:6px;">
                            <span class="text-muted" style="font-size:12px;">Budget Used</span>
                            <span style="font-weight:700;font-size:13px;color:var(--dark);">{{ $trip->pct_used }}%</span>
                        </div>
                        <div style="height:6px;background:var(--border-light);border-radius:99px;overflow:hidden;margin-bottom:12px;">
                            <div style="height:100%;width:{{ $pct }}%;background:var(--primary);border-radius:99px;"></div>
                        </div>
                        <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:18px;">
                            <span>Spent: <strong style="color:var(--primary);">{{ currency_symbol() }}{{ number_format($trip->total_spent, 2) }}</strong></span>
                            <span class="text-muted">Allocated Budget: {{ currency_symbol() }}{{ number_format($trip->budget_limit, 2) }}</span>
                        </div>

                        <div style="display:flex;gap:10px;">
                            <button type="button" class="btn btn-secondary" style="flex:1;text-transform:uppercase;letter-spacing:.05em;font-size:12px;" wire:click="showDetail({{ $trip->id }})">Details</button>
                            <button type="button" class="btn {{ $isPickedForCompare ? '' : 'btn-primary' }}"
                                    style="flex:1;text-transform:uppercase;letter-spacing:.05em;font-size:12px;display:flex;align-items:center;justify-content:center;gap:6px;{{ $isPickedForCompare ? 'background:var(--primary-light);color:var(--primary);border:1.5px solid var(--primary);' : '' }}"
                                    wire:click="toggleCompare({{ $trip->id }})">
                                <i class="fa-solid {{ $isPickedForCompare ? 'fa-check' : 'fa-code-compare' }}" style="font-size:11px;"></i>
                                {{ $isPickedForCompare ? 'Selected' : 'Compare' }}
                            </button>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
            @endif
        </div>
    </div>
    @endforeach

    @endif {{-- end @else (has trips) --}}

    {{-- Floating compare bar — appears once two cards are picked --}}
    @if (count($compareIds) === 2)
    <div style="position:fixed;left:50%;bottom:24px;transform:translateX(-50%);z-index:1000;background:var(--bg-white);border:1.5px solid var(--primary);border-radius:16px;box-shadow:0 8px 28px rgba(0,0,0,.18);padding:12px 16px;display:flex;align-items:center;gap:14px;">
        <span style="font-size:13px;font-weight:600;color:var(--dark);white-space:nowrap;">
            <i class="fa-solid fa-code-compare" style="color:var(--primary);margin-right:6px;"></i> 2 trips selected
        </span>
        <button type="button" wire:click="clearCompareSelection" style="background:none;border:none;color:var(--muted);font-size:13px;font-weight:600;cursor:pointer;padding:8px 4px;">Clear</button>
        <button type="button" wire:click="runComparison" class="btn btn-primary" style="white-space:nowrap;">Compare Trips</button>
    </div>
    @endif

    {{-- Details Modal --}}
    @if ($detailTrip)
    @php $dt = $detailTrip; @endphp
    <div style="position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:2000;display:flex;align-items:center;justify-content:center;padding:20px;" wire:click.self="closeDetail">
        <div style="background:var(--bg-white);border-radius:20px;max-width:480px;width:100%;max-height:90vh;overflow-y:auto;padding:28px;">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:4px;">
                <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                    <h2 style="margin:0;">{{ $dt->trip_name ?? $dt->destination }}</h2>
                    <span style="background:#EFF6FF;color:#1D4ED8;font-size:11px;font-weight:700;padding:3px 10px;border-radius:20px;text-transform:uppercase;white-space:nowrap;">{{ $dt->status }}</span>
                </div>
                <button wire:click="closeDetail" style="background:none;border:none;font-size:20px;cursor:pointer;color:var(--muted);line-height:1;">&times;</button>
            </div>
            <div class="text-muted" style="font-size:13px;margin-bottom:16px;">{{ $fmtDate($dt->start_date, 'M j') }} - {{ $fmtDate($dt->end_date, 'M j, Y') }}</div>

            <div style="height:170px;border-radius:14px;overflow:hidden;margin-bottom:18px;background:linear-gradient(135deg,var(--primary),#C8874A);">
                @if ($dt->cover_image)
                <img src="{{ $dt->cover_image }}" style="width:100%;height:100%;object-fit:cover;display:block;" alt="{{ $dt->destination }}">
                @endif
            </div>

            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:18px;">
                <div>
                    <div class="text-muted" style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;margin-bottom:3px;">Start Date</div>
                    <div style="font-size:13px;font-weight:600;">{{ $fmtDate($dt->start_date, 'M j') }}</div>
                </div>
                <div>
                    <div class="text-muted" style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;margin-bottom:3px;">End Date</div>
                    <div style="font-size:13px;font-weight:600;">{{ $fmtDate($dt->end_date, 'M j') }}</div>
                </div>
                <div>
                    <div class="text-muted" style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;margin-bottom:3px;">Duration</div>
                    <div style="font-size:13px;font-weight:600;">{{ $dt->days }} days</div>
                </div>
                <div>
                    <div class="text-muted" style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;margin-bottom:3px;">Group</div>
                    <div style="font-size:13px;font-weight:600;">{{ $dt->travel_type ?? 'Solo' }}</div>
                </div>
            </div>

            @php
                $spent     = $dt->total_spent;
                $budget    = (float) $dt->budget_limit;
                $remaining = $budget - $spent;
            @endphp
            <div style="background:var(--border-light);border-radius:12px;padding:16px;margin-bottom:18px;">
                <div style="display:flex;justify-content:space-between;margin-bottom:8px;">
                    <span style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:var(--muted);">Budget Usage</span>
                    <span style="font-size:12px;font-weight:700;">{{ $dt->pct_used }}% Expended</span>
                </div>
                <div style="height:6px;background:var(--border-light);border-radius:99px;overflow:hidden;margin-bottom:12px;">
                    <div style="height:100%;width:{{ min(100,$dt->pct_used) }}%;background:var(--primary);border-radius:99px;"></div>
                </div>
                <div style="display:flex;justify-content:space-between;">
                    <div>
                        <div class="text-muted" style="font-size:11px;">Spent</div>
                        <div style="font-weight:700;">{{ currency_symbol() }}{{ number_format($spent, 2) }}</div>
                    </div>
                    <div style="text-align:right;">
                        <div class="text-muted" style="font-size:11px;">Total Budget</div>
                        <div style="font-weight:700;">{{ currency_symbol() }}{{ number_format($budget, 2) }}</div>
                    </div>
                </div>
                <div class="text-muted" style="font-size:12px;margin-top:10px;">Remaining: {{ currency_symbol() }}{{ number_format($remaining, 2) }}</div>
            </div>

            <div style="display:flex;gap:10px;">
                <a href="{{ route('expenses.index') }}?trip_id={{ $dt->id }}" class="btn btn-secondary" style="flex:1;text-align:center;">Expenses</a>
                <a href="{{ route('itinerary.index') }}" class="btn btn-primary" style="flex:1;text-align:center;">Itinerary</a>
            </div>
        </div>
    </div>
    @endif

    {{-- Compare Modal --}}
    @if ($showComparison && count($compareData) === 2)
    @php [$a, $b] = $compareData; @endphp
    <div style="position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:2000;display:flex;align-items:center;justify-content:center;padding:20px;" wire:click.self="closeComparison">
        <div style="background:var(--bg-white);border-radius:20px;max-width:700px;width:100%;max-height:90vh;overflow-y:auto;padding:28px;">
            <h2 style="margin-bottom:4px;">Compare Trips</h2>
            <p class="text-muted" style="margin-bottom:20px;font-size:13px;">
                Comparing your recent trips to {{ $a['trip']->destination }} and {{ $b['trip']->destination }} to identify spending patterns.
            </p>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:28px;">
                @foreach ([$a, $b] as $d)
                @php
                    $t    = $d['trip'];
                    $diff = (float) $t->budget_limit - $t->total_spent;
                    $over = $diff < 0;
                    $color = $over ? '#DC2626' : '#16A34A';
                @endphp
                <div style="border:1px solid var(--border);border-radius:14px;padding:16px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;gap:8px;">
                        <span style="font-size:11px;font-weight:700;padding:4px 10px;border-radius:20px;background:{{ $over ? '#FEF2F2' : '#F0FDF4' }};color:{{ $color }};white-space:nowrap;">
                            {{ $over ? 'Over budget · overspent' : 'Under budget · saved' }} {{ currency_symbol() }}{{ number_format(abs($diff), 0) }}
                        </span>
                        <div style="width:32px;height:32px;flex-shrink:0;border-radius:50%;background:{{ $color }};display:flex;align-items:center;justify-content:center;">
                            <i class="fa-solid {{ $over ? 'fa-triangle-exclamation' : 'fa-rocket' }}" style="color:#fff;font-size:12px;"></i>
                        </div>
                    </div>
                    <div style="font-weight:700;">{{ $t->origin_code ?? 'MNL' }} &rarr; {{ $t->destination_code ?? '—' }}</div>
                    <div class="text-muted" style="font-size:12px;margin-bottom:14px;">{{ $t->destination }} · {{ $fmtDate($t->start_date, 'M j') }} - {{ $fmtDate($t->end_date, 'M j, Y') }}</div>
                    <div style="display:flex;justify-content:space-between;font-size:11px;margin-bottom:4px;">
                        <span class="text-muted">Budget</span><span class="text-muted">Actual Spend</span>
                    </div>
                    <div style="display:flex;justify-content:space-between;font-weight:700;margin-bottom:8px;">
                        <span>{{ currency_symbol() }}{{ number_format($t->budget_limit, 0) }}</span>
                        <span style="color:{{ $color }};">{{ currency_symbol() }}{{ number_format($t->total_spent, 0) }}</span>
                    </div>
                    <div style="height:6px;background:var(--border-light);border-radius:99px;overflow:hidden;">
                        <div style="height:100%;width:{{ min(100,$t->pct_used) }}%;background:{{ $color }};border-radius:99px;"></div>
                    </div>
                    <div class="text-muted" style="font-size:11px;margin-top:4px;">{{ $t->pct_used }}% of budget used</div>
                </div>
                @endforeach
            </div>

            <h3 style="margin-bottom:10px;">Spending by Category</h3>
            <div style="display:flex;gap:16px;font-size:12px;margin-bottom:16px;">
                <span><span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#16A34A;margin-right:5px;"></span>{{ $a['trip']->destination }}</span>
                <span><span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#F97316;margin-right:5px;"></span>{{ $b['trip']->destination }}</span>
            </div>

            @foreach ($a['categories'] as $cat => $aVal)
            @php
                $bVal    = $b['categories'][$cat] ?? 0;
                $max     = max($aVal, $bVal, 1);
                $diffPct = $aVal > 0 ? round((($bVal - $aVal) / $aVal) * 100) : ($bVal > 0 ? 100 : 0);
                $label   = $cat === 'Tourist Attractions' ? 'Attractions' : $cat;
            @endphp
            <div style="margin-bottom:14px;">
                <div style="font-size:12px;font-weight:600;margin-bottom:6px;">{{ $label }}</div>
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
                    <div style="flex:1;height:8px;background:var(--border-light);border-radius:99px;overflow:hidden;">
                        <div style="height:100%;width:{{ $max > 0 ? min(100, $aVal / $max * 100) : 0 }}%;background:#16A34A;border-radius:99px;"></div>
                    </div>
                    <span style="font-size:11px;width:80px;text-align:right;flex-shrink:0;">{{ currency_symbol() }}{{ number_format($aVal, 0) }}</span>
                </div>
                <div style="display:flex;align-items:center;gap:8px;">
                    <div style="flex:1;height:8px;background:var(--border-light);border-radius:99px;overflow:hidden;">
                        <div style="height:100%;width:{{ $max > 0 ? min(100, $bVal / $max * 100) : 0 }}%;background:#F97316;border-radius:99px;"></div>
                    </div>
                    <span style="font-size:11px;width:80px;text-align:right;flex-shrink:0;">
                        {{ currency_symbol() }}{{ number_format($bVal, 0) }}
                        @if ($diffPct !== 0)
                        <span class="text-muted">({{ $diffPct > 0 ? '+' : '' }}{{ $diffPct }}%)</span>
                        @endif
                    </span>
                </div>
            </div>
            @endforeach

            <button class="btn btn-secondary" style="width:100%;margin-top:8px;" wire:click="closeComparison">Back</button>
        </div>
    </div>
    @endif
</div>
