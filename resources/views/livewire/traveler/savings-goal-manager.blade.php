<div style="background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,0.08);display:flex;flex-direction:column;width:360px;flex-shrink:0;">

    @php
        $trip       = $goal->trip;
        $cover      = $trip?->cover_image;
        $dest       = $goal->goal_name;
        $tType      = strtoupper($trip?->travel_type ?? 'SOLO');
        $typeColor  = $tType === 'GROUP' ? '#A855F7' : '#14B8A6';
        $fromCode   = $trip?->origin_code ?? 'MNL';
        $toCode     = $trip?->destination_code ?? '';
        $dateFrom   = $trip?->start_date?->format('M j');
        $dateTo     = $trip?->end_date?->format('M j, Y');
        $targetCost = $trip?->total_cost ?? $goal->target_amount;
    @endphp

    {{-- Cover image --}}
    <div style="position:relative;height:150px;background:linear-gradient(135deg,#934B19,#C8874A);overflow:hidden;flex-shrink:0;">
        @if($cover)
        <img src="{{ $cover }}" alt="{{ $dest }}"
             style="width:100%;height:100%;object-fit:cover;display:block;"
             onerror="this.style.display='none'">
        @endif
        <div style="position:absolute;inset:0;background:linear-gradient(to bottom,rgba(0,0,0,0.1),rgba(0,0,0,0.55));"></div>

        {{-- Type pill --}}
        <span style="position:absolute;top:12px;left:12px;background:{{ $typeColor }};color:#fff;font-size:10px;font-weight:700;padding:3px 10px;border-radius:20px;letter-spacing:0.5px;">{{ $tType }}</span>

        {{-- Trip info overlay --}}
        <div style="position:absolute;bottom:12px;left:14px;right:14px;">
            <div style="font-size:15px;font-weight:700;color:#fff;margin-bottom:4px;line-height:1.3;">{{ $dest }}</div>
            @if($trip)
            <div style="display:flex;flex-direction:column;gap:3px;">
                @if($fromCode)
                <div style="display:flex;align-items:center;gap:5px;font-size:11px;color:rgba(255,255,255,0.9);">
                    <i class="fa-solid fa-plane" style="font-size:10px;color:#F5C97A;"></i>
                    <span>{{ $fromCode }}{{ $toCode ? ' to '.$toCode : '' }}</span>
                </div>
                @endif
                @if($dateFrom)
                <div style="display:flex;align-items:center;gap:5px;font-size:11px;color:rgba(255,255,255,0.9);">
                    <i class="fa-regular fa-calendar-days" style="font-size:10px;color:#F5C97A;"></i>
                    <span>{{ $dateFrom }} – {{ $dateTo }}</span>
                </div>
                @endif
            </div>
            @endif
        </div>
    </div>

    {{-- Body --}}
    <div style="padding:16px 18px 18px;display:flex;flex-direction:column;flex:1;">

        @php $cardPct = $targetCost > 0 ? min(100, round($goal->current_savings / $targetCost * 100, 1)) : 0; @endphp
        @php $cardDone = $goal->current_savings >= $targetCost; @endphp

        {{-- Progress label + pct --}}
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
            <span style="font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#9B8EA0;">Savings Progress</span>
            @if($cardDone)
            <span style="font-size:11px;font-weight:700;color:#16A34A;">Completed</span>
            @else
            <span style="font-size:13px;font-weight:700;color:#1A0A00;">{{ $cardPct }}%</span>
            @endif
        </div>

        {{-- Progress bar --}}
        <div style="height:5px;background:#F3F4F6;border-radius:99px;overflow:hidden;margin-bottom:14px;">
            <div style="height:100%;width:{{ $cardPct }}%;background:{{ $cardDone ? '#16A34A' : '#934B19' }};border-radius:99px;transition:width 0.3s;"></div>
        </div>

        {{-- Saved / Target --}}
        <div style="display:flex;justify-content:space-between;align-items:flex-end;margin-bottom:16px;">
            <div>
                <div style="font-size:10px;font-weight:600;color:#9B8EA0;text-transform:uppercase;letter-spacing:.05em;margin-bottom:2px;">Saved Amount</div>
                <div style="font-size:20px;font-weight:800;color:#C8874A;">PHP {{ number_format($goal->current_savings, 2) }}</div>
            </div>
            <div style="text-align:right;">
                <div style="font-size:10px;font-weight:600;color:#9B8EA0;text-transform:uppercase;letter-spacing:.05em;margin-bottom:2px;">Target Goal</div>
                <div style="font-size:20px;font-weight:800;color:#1A0A00;">PHP {{ number_format($targetCost, 2) }}</div>
            </div>
        </div>

        {{-- Button --}}
        <div style="margin-top:auto;">
            @if($cardDone)
            <div style="width:100%;padding:12px;border-radius:10px;background:#F0FDF4;color:#16A34A;font-size:13px;font-weight:700;text-align:center;display:flex;align-items:center;justify-content:center;gap:6px;">
                <i class="fa-solid fa-check"></i> Goal Reached!
            </div>
            @else
            <button wire:click="openDeposit"
                    style="width:100%;background:#934B19;color:#fff;border:none;border-radius:10px;padding:12px;font-size:13px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:7px;font-family:'Hanken Grotesk',sans-serif;"
                    onmouseenter="this.style.background='#783603'" onmouseleave="this.style.background='#934B19'">
                <i class="fa-solid fa-circle-plus" style="font-size:14px;"></i> Add Savings
            </button>
            @endif
        </div>
    </div>

    {{-- Deposit modal --}}
    @if ($showDeposit)
    <div style="position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1000;display:flex;align-items:center;justify-content:center;padding:16px;" wire:click.self="closeDeposit">
        <div style="background:#FDFAF7;border-radius:20px;width:100%;max-width:380px;box-shadow:0 24px 64px rgba(0,0,0,.2);padding:28px;">

            <div style="font-size:18px;font-weight:700;color:#1A0A00;margin-bottom:22px;">Add Savings</div>

            <div style="margin-bottom:6px;">
                <div style="font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#9B8EA0;margin-bottom:8px;">Amount to Save</div>
                <div x-data="{
                        display: '',
                        update(e) {
                            let raw = e.target.value.replace(/[^0-9.]/g, '');
                            let parts = raw.split('.');
                            parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                            this.display = parts.join('.');
                            $wire.set('depositAmount', parseFloat(raw) || 0);
                        }
                    }"
                     style="display:flex;align-items:center;gap:10px;background:#fff;border:1.5px solid #d3c3be;border-radius:12px;padding:12px 16px;"
                     onfocusin="this.style.borderColor='#934B19'" onfocusout="this.style.borderColor='#d3c3be'">
                    <span style="font-size:14px;font-weight:600;color:#9B8EA0;flex-shrink:0;">PHP</span>
                    <input type="text" inputmode="decimal"
                           x-model="display" @input="update($event)"
                           style="flex:1;border:none;outline:none;font-size:15px;font-weight:600;color:#1A0A00;background:transparent;font-family:'Hanken Grotesk',sans-serif;"
                           placeholder="0.00">
                </div>
                @error('depositAmount')<div style="color:#DC2626;font-size:12px;margin-top:5px;">{{ $message }}</div>@enderror
            </div>

            <div style="font-size:12px;color:#9B8EA0;margin-bottom:24px;">
                This will be added to your '{{ $goal->goal_name }}' goal.
            </div>

            <button wire:click="submitDeposit"
                    style="width:100%;background:#934B19;color:#fff;border:none;border-radius:10px;padding:14px;font-size:13px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;cursor:pointer;font-family:'Hanken Grotesk',sans-serif;margin-bottom:12px;"
                    onmouseenter="this.style.background='#783603'" onmouseleave="this.style.background='#934B19'">
                Confirm Savings
            </button>

            <button wire:click="closeDeposit"
                    style="width:100%;background:transparent;border:none;font-size:13px;font-weight:600;color:#6B7280;cursor:pointer;font-family:'Hanken Grotesk',sans-serif;padding:4px;">
                Cancel
            </button>

        </div>
    </div>
    @endif
</div>
