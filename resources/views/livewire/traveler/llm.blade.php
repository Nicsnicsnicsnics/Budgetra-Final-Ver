<div>

{{-- ═══════════════════════════════════════════════════════════════
     AI PLANNER — results
═══════════════════════════════════════════════════════════════ --}}
@if ($aiStep === 'results' && !empty($aiPackage))
@php
    $pkg    = $aiPackage;
    $total  = $pkg['total']  ?? 0;
    $budget = $pkg['budget'] ?? ($aiBudgetMax ?: $aiBudgetMin ?: 0);
    $pct    = $budget > 0 ? min(100, round($total / $budget * 100)) : 0;
@endphp
<div style="padding-bottom:110px;">

    {{-- YOUR REQUEST --}}
    <div style="margin-bottom:24px;">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;">
            <div style="width:28px;height:28px;border-radius:50%;background:#F5F0EB;display:flex;align-items:center;justify-content:center;">
                <i class="fa-solid fa-user" style="font-size:11px;color:#934B19;"></i>
            </div>
            <span style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.8px;color:var(--muted);">Your Request</span>
        </div>
        <div style="background:#934B19;color:#fff;border-radius:12px;padding:16px 22px;font-size:14px;font-weight:600;line-height:1.5;">
            {{ $aiFrom }} to {{ $aiTo }}
            @if ($aiBudgetMin || $aiBudgetMax)
                &nbsp;·&nbsp;
                @if ($aiBudgetMin && $aiBudgetMax && $aiBudgetMin !== $aiBudgetMax)
                    ₱{{ number_format($aiBudgetMin) }}–₱{{ number_format($aiBudgetMax) }}
                @else
                    ₱{{ number_format($aiBudgetMax ?: $aiBudgetMin) }}
                @endif
            @endif
            @if ($aiDateFrom && $aiDateTo)
                &nbsp;·&nbsp; {{ $aiDateFrom }} – {{ $aiDateTo }}
            @endif
        </div>
    </div>

    {{-- Heading --}}
    <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin:0 0 20px;">
        <h2 style="font-size:20px;font-weight:800;color:var(--dark);margin:0;display:flex;align-items:center;gap:10px;">
            <span style="width:32px;height:32px;background:#F5F0EB;border-radius:8px;display:inline-flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0;">✦</span>
            Your trip package for {{ $aiTo }}
        </h2>
        <button wire:click="editWithWizard" wire:loading.attr="disabled" wire:target="editWithWizard"
                style="background:#fff;border:1.5px solid var(--border);color:#934B19;border-radius:8px;padding:7px 14px;font-size:12px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:6px;white-space:nowrap;flex-shrink:0;"
                onmouseenter="this.style.background='#F5F0EB'"
                onmouseleave="this.style.background='#fff'">
            <i class="fa-solid fa-pen"></i> Edit
        </button>
    </div>

    {{-- Package cards --}}
    <div style="display:flex;flex-direction:column;gap:12px;margin-bottom:32px;">

        @php
        $cards = [
            ['key'=>'transport',     'label'=>'Transportation', 'icon'=>'fa-solid fa-plane',    'name_field'=>null,    'name_fallback'=>($pkg['transport']['from_code'] ?? 'MNL').' → '.($pkg['transport']['to_code'] ?? '')],
            ['key'=>'accommodation', 'label'=>'Accommodation',  'icon'=>'fa-solid fa-bed',      'name_field'=>'name',  'name_fallback'=>''],
            ['key'=>'food',          'label'=>'Food & Dining',  'icon'=>'fa-solid fa-utensils', 'name_field'=>'name',  'name_fallback'=>''],
            ['key'=>'attractions',   'label'=>'Attractions',    'icon'=>'fa-solid fa-building-columns', 'name_field'=>null, 'name_fallback'=>''],
        ];
        @endphp

        @foreach ($cards as $card)
        @php $sec = $pkg[$card['key']] ?? []; @endphp
        @if (!empty($sec))
        <div style="background:#fff;border:1.5px solid var(--border);border-radius:14px;padding:20px 22px;display:flex;align-items:flex-start;gap:16px;">

            {{-- Icon --}}
            <div style="width:44px;height:44px;border-radius:10px;background:#F5F0EB;display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:2px;">
                <i class="{{ $card['icon'] }}" style="color:#934B19;font-size:17px;"></i>
            </div>

            {{-- Content --}}
            <div style="flex:1;min-width:0;">
                <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.8px;color:var(--muted);margin-bottom:5px;">{{ $card['label'] }}</div>

                @if ($card['key'] === 'transport')
                    <div style="font-size:16px;font-weight:800;color:var(--dark);margin-bottom:4px;">
                        {{ $sec['from_code'] ?? 'MNL' }} → {{ $sec['to_code'] ?? '' }}
                    </div>
                    <div style="font-size:12px;color:var(--muted);">{{ $sec['detail'] ?? '' }}</div>

                @elseif ($card['key'] === 'accommodation')
                    <div style="font-size:16px;font-weight:800;color:var(--dark);margin-bottom:4px;display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
                        {{ $sec['name'] ?? '' }}
                        @if (!empty($sec['stars']))
                            <span>@for ($s=0;$s<$sec['stars'];$s++)<i class="fa-solid fa-star" style="font-size:10px;color:#F59E0B;"></i>@endfor</span>
                        @endif
                    </div>
                    <div style="font-size:12px;color:var(--muted);">{{ $sec['detail'] ?? '' }}</div>

                @elseif ($card['key'] === 'food')
                    <div style="font-size:16px;font-weight:800;color:var(--dark);margin-bottom:4px;">{{ $sec['name'] ?? '' }}</div>
                    <div style="font-size:12px;color:var(--muted);">{{ $sec['detail'] ?? '' }}</div>

                @elseif ($card['key'] === 'attractions')
                    <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:2px;">
                        @foreach ($sec['items'] ?? [] as $att)
                        <span style="display:inline-flex;align-items:center;background:#F5F0EB;border-radius:20px;padding:4px 12px;font-size:12px;font-weight:600;color:#934B19;">
                            {{ $att[0] }} ({{ $att[1] }})
                        </span>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Cost --}}
            <div style="text-align:right;flex-shrink:0;min-width:80px;">
                <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.6px;color:var(--muted);margin-bottom:4px;">Est. Cost</div>
                <div style="font-size:18px;font-weight:800;color:var(--dark);">₱{{ number_format($sec['cost'] ?? 0) }}</div>
            </div>

        </div>
        @endif
        @endforeach

    </div>
</div>

{{-- ── Bottom bar ── --}}
<div style="position:fixed;bottom:0;left:var(--sidebar-width,220px);right:0;background:#fff;border-top:1.5px solid var(--border);padding:14px 28px;display:flex;align-items:center;gap:20px;z-index:100;">
    <div style="flex:1;min-width:0;">
        <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.7px;color:var(--muted);margin-bottom:5px;">Estimated Cost (Total)</div>
        <div style="font-size:18px;font-weight:800;color:var(--dark);margin-bottom:6px;">
            ₱{{ number_format($total) }} of ₱{{ number_format($budget) }} budget
        </div>
        <div style="display:flex;align-items:center;gap:10px;">
            <div style="flex:1;height:6px;background:#EDE8E3;border-radius:99px;overflow:hidden;">
                <div style="height:100%;background:#934B19;border-radius:99px;width:{{ $pct }}%;"></div>
            </div>
            <span style="font-size:12px;font-weight:600;color:var(--muted);">{{ $pct }}%</span>
        </div>
    </div>
    <button wire:click="regeneratePackage" wire:loading.attr="disabled" wire:target="regeneratePackage"
            style="background:#fff;border:1.5px solid var(--border);color:var(--dark);border-radius:10px;padding:11px 20px;font-size:13px;font-weight:700;cursor:pointer;white-space:nowrap;display:inline-flex;align-items:center;gap:8px;"
            onmouseenter="this.style.background='#F5F0EB'"
            onmouseleave="this.style.background='#fff'">
        <span wire:loading.remove wire:target="regeneratePackage"><i class="fa-solid fa-rotate"></i> Regenerate</span>
        <span wire:loading wire:target="regeneratePackage"><i class="fa-solid fa-spinner fa-spin"></i> Regenerating…</span>
    </button>
    <button wire:click="saveAiTrip" wire:loading.attr="disabled" wire:target="saveAiTrip"
            style="background:#934B19;color:#fff;border:none;border-radius:10px;padding:11px 28px;font-size:13px;font-weight:700;cursor:pointer;white-space:nowrap;"
            onmouseenter="this.style.background='#7A3C12'"
            onmouseleave="this.style.background='#934B19'">
        <span wire:loading.remove wire:target="saveAiTrip">Next</span>
        <span wire:loading wire:target="saveAiTrip"><i class="fa-solid fa-spinner fa-spin"></i> Saving…</span>
    </button>
</div>
@endif

{{-- ═══════════════════════════════════════════════════════════════
     AI PLANNER — loading / building package
═══════════════════════════════════════════════════════════════ --}}
@if ($aiStep === 'loading')
<style>
    @keyframes aiSpin{to{transform:rotate(360deg)}}
    @keyframes llmShimmer{0%{background-position:-400px 0;}100%{background-position:400px 0;}}
    @keyframes llmTypingDot{0%,60%,100%{opacity:.25;transform:translateY(0);}30%{opacity:1;transform:translateY(-3px);}}
    .llm-skel{background:linear-gradient(90deg,#F0EBE4 0%,#FAF7F3 50%,#F0EBE4 100%);background-size:800px 100%;animation:llmShimmer 1.4s ease-in-out infinite;border-radius:10px;}
</style>
<div style="display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:70vh;text-align:center;padding:40px 24px;">
    <svg style="width:44px;height:44px;animation:aiSpin 1s linear infinite;margin-bottom:20px;" viewBox="0 0 24 24" fill="none" stroke="#934B19" stroke-width="2.5" stroke-linecap="round">
        <path d="M12 2a10 10 0 1 0 10 10" />
    </svg>
    <h2 style="font-size:20px;font-weight:800;color:var(--dark);margin:0 0 8px;">Building your trip package…</h2>
    <p style="font-size:14px;color:var(--muted);margin:0 0 6px;display:flex;align-items:center;gap:6px;">
        TARA is thinking
        <span style="display:inline-flex;gap:3px;">
            <span style="width:5px;height:5px;border-radius:50%;background:#934B19;display:inline-block;animation:llmTypingDot 1.2s ease-in-out infinite;animation-delay:0s;"></span>
            <span style="width:5px;height:5px;border-radius:50%;background:#934B19;display:inline-block;animation:llmTypingDot 1.2s ease-in-out infinite;animation-delay:.2s;"></span>
            <span style="width:5px;height:5px;border-radius:50%;background:#934B19;display:inline-block;animation:llmTypingDot 1.2s ease-in-out infinite;animation-delay:.4s;"></span>
        </span>
    </p>

    {{-- Skeleton preview of the results cards about to appear --}}
    <div style="width:100%;max-width:520px;margin-top:28px;display:flex;flex-direction:column;gap:12px;">
        @for ($i = 0; $i < 4; $i++)
        <div style="background:#fff;border:1.5px solid var(--border);border-radius:14px;padding:20px 22px;display:flex;align-items:center;gap:16px;">
            <div class="llm-skel" style="width:44px;height:44px;border-radius:10px;flex-shrink:0;"></div>
            <div style="flex:1;display:flex;flex-direction:column;gap:8px;">
                <div class="llm-skel" style="height:10px;width:35%;"></div>
                <div class="llm-skel" style="height:14px;width:65%;"></div>
            </div>
            <div class="llm-skel" style="width:60px;height:16px;flex-shrink:0;"></div>
        </div>
        @endfor
    </div>
</div>
@script
<script>
    setTimeout(() => $wire.call('showResults'), 3000);
</script>
@endscript
@endif

{{-- ═══════════════════════════════════════════════════════════════
     AI PLANNER — landing / prompt input
═══════════════════════════════════════════════════════════════ --}}
@if ($aiStep === '')
@php
    $greeting = 'Good day, ' . (auth()->user()->first_name ?? '') . '!';
    $hour = now()->hour;
    $unusedGreeting = match(true) {
        $hour >= 5 && $hour < 12  => 'Good morning',
        $hour >= 12 && $hour < 17 => 'Good afternoon',
        $hour >= 17 && $hour < 21 => 'Good evening',
        default                   => 'Hello, night owl',
    };
@endphp
<div style="min-height:calc(100vh - 80px);display:flex;flex-direction:column;align-items:center;justify-content:center;padding:32px 24px 60px;position:relative;">

    <style>
        @keyframes llmFadeUp{from{opacity:0;transform:translateY(10px);}to{opacity:1;transform:translateY(0);}}
        @keyframes llmMsgIn{from{opacity:0;transform:translateY(6px);}to{opacity:1;transform:translateY(0);}}
        .llm-fade-up{animation:llmFadeUp .5s ease both;}
        .llm-back-link{display:inline-flex;align-items:center;gap:6px;color:#934B19;font-size:13px;font-weight:600;text-decoration:none;}
        .llm-greeting{font-size:clamp(24px,3.2vw,32px);font-weight:600;color:var(--dark);margin:0 0 16px;font-family:Georgia,'Times New Roman',serif;letter-spacing:-.01em;}
        .llm-interest-pill{display:inline-flex;align-items:center;gap:6px;background:#fff;border:1.5px solid #F0DCC5;color:#934B19;border-radius:999px;padding:6px 15px;font-size:12px;font-weight:700;box-shadow:0 2px 6px rgba(147,75,25,0.06);transition:border-color .18s,box-shadow .18s,transform .18s;}
        .llm-interest-pill:hover{border-color:#934B19;box-shadow:0 4px 12px rgba(147,75,25,0.12);transform:translateY(-1px);}
        .llm-interest-edit{color:#934B19;font-size:12px;font-weight:700;text-decoration:none;display:inline-flex;align-items:center;gap:5px;}
        .llm-interest-edit:hover{text-decoration:underline;}
        .llm-composer{width:100%;max-width:600px;background:#fff;border:1.5px solid var(--border);border-radius:24px;box-shadow:0 8px 32px rgba(45,27,20,.07);padding:20px 22px;transition:box-shadow .2s ease,border-color .2s ease;}
        .llm-composer:focus-within{border-color:#934B19;box-shadow:0 12px 40px rgba(147,75,25,.14);}
        .llm-composer textarea{width:100%;border:none;outline:none;resize:none;font-family:inherit;font-size:15px;color:var(--dark);background:transparent;line-height:1.5;max-height:120px;}
        .llm-composer-footer{display:flex;align-items:center;justify-content:flex-end;margin-top:12px;}
        .llm-send-btn{width:40px;height:40px;border-radius:50%;background:#934B19;color:#fff;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;box-shadow:0 6px 16px rgba(147,75,25,0.28);transition:transform .15s ease,background .15s ease;}
        .llm-send-btn:hover{background:#6A3500;transform:scale(1.08);}
        .llm-send-btn:active{transform:scale(.92);}
        .llm-thread{width:100%;max-width:600px;margin:0 auto;flex:1;overflow-y:auto;padding:80px 4px 16px;display:flex;flex-direction:column;gap:12px;}
        .llm-msg{max-width:80%;padding:11px 16px;border-radius:16px;font-size:14px;line-height:1.5;animation:llmMsgIn .3s ease both;}
        .llm-msg-user{align-self:flex-end;background:#934B19;color:#fff;border-bottom-right-radius:4px;}
        .llm-msg-assistant{align-self:flex-start;background:#fff;border:1.5px solid var(--border);color:var(--dark);border-bottom-left-radius:4px;}
        @media (max-width:640px){
            .llm-composer{border-radius:18px;padding:14px 16px;}
        }
    </style>

    @if (empty($messages))
        {{-- First-time welcome state: greeting + composer + quick pills --}}
        <div class="llm-fade-up" style="text-align:center;width:100%;max-width:600px;">

            <h1 class="llm-greeting">✦ {{ $greeting }}</h1>
            <p style="font-size:14px;color:var(--muted);margin:0 0 20px;">Plan your trip with TARA</p>

            @if (!empty($this->profileInterests))
                <div style="display:flex;flex-wrap:wrap;gap:9px;justify-content:center;align-items:center;margin-bottom:28px;">
                    @foreach (array_slice($this->profileInterests, 0, 4) as $interest)
                        <span class="llm-interest-pill"><i class="fa-solid fa-tag" style="font-size:9px;"></i>{{ $interest }}</span>
                    @endforeach
                    <a href="{{ route('profile.setup', ['step' => 4, 'return' => 'trips.plan.ai']) }}" wire:navigate class="llm-interest-edit"><i class="fa-regular fa-pen-to-square" style="font-size:11px;"></i>Edit</a>
                </div>
            @else
                <div style="margin-bottom:28px;">
                    <a href="{{ route('profile.setup', ['step' => 4, 'return' => 'trips.plan.ai']) }}" wire:navigate class="llm-interest-edit">+ Add your travel interests</a>
                </div>
            @endif

            <div class="llm-composer">
                <style>#llm-ai-prompt::placeholder{color:#B7A99B;opacity:1;}</style>
                <textarea id="llm-ai-prompt" wire:model="aiPrompt"
                          placeholder="Describe your dream trip... destination, budget, travel dates, or interests."
                          rows="2"
                          x-data
                          x-on:input="$el.style.height='auto';$el.style.height=$el.scrollHeight+'px'"
                          x-on:keydown.enter.prevent="if (!$event.shiftKey) $wire.automateTrip()"></textarea>
                <div class="llm-composer-footer">
                    <button type="button" wire:click="automateTrip" wire:loading.attr="disabled" wire:target="automateTrip" class="llm-send-btn" aria-label="Send">
                        <span wire:loading.remove wire:target="automateTrip"><i class="fa-solid fa-paper-plane" style="font-size:13px;"></i></span>
                        <span wire:loading wire:target="automateTrip"><i class="fa-solid fa-spinner fa-spin" style="font-size:13px;"></i></span>
                    </button>
                </div>
            </div>

        </div>
    @else
        {{-- Conversation is underway: chat thread + composer pinned below --}}
        <div style="width:100%;height:calc(100vh - 80px);display:flex;flex-direction:column;align-items:center;">

            <div id="llm-thread" class="llm-thread">
                @foreach ($messages as $msg)
                    <div class="llm-msg llm-msg-{{ $msg['role'] }}">{{ $msg['text'] }}</div>
                @endforeach
            </div>

            <div class="llm-composer" style="margin-top:auto;">
                <style>#llm-ai-prompt::placeholder{color:#B7A99B;opacity:1;}</style>
                <textarea id="llm-ai-prompt" wire:model="aiPrompt"
                          placeholder="Type your reply…"
                          rows="1"
                          x-data
                          x-on:input="$el.style.height='auto';$el.style.height=$el.scrollHeight+'px'"
                          x-on:keydown.enter.prevent="if (!$event.shiftKey) $wire.automateTrip()"></textarea>
                <div class="llm-composer-footer">
                    <button type="button" wire:click="automateTrip" wire:loading.attr="disabled" wire:target="automateTrip" class="llm-send-btn" aria-label="Send">
                        <span wire:loading.remove wire:target="automateTrip"><i class="fa-solid fa-paper-plane" style="font-size:13px;"></i></span>
                        <span wire:loading wire:target="automateTrip"><i class="fa-solid fa-spinner fa-spin" style="font-size:13px;"></i></span>
                    </button>
                </div>
            </div>

        </div>
    @endif
</div>
@script
<script>
    const scrollThread = () => {
        const el = document.getElementById('llm-thread');
        if (el) el.scrollTop = el.scrollHeight;
    };
    $wire.on('message-added', () => $nextTick(() => scrollThread()));
    scrollThread();
</script>
@endscript
@endif

</div>
