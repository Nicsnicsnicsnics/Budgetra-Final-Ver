@extends('layouts.app')
@section('title', 'Settings')

@push('styles')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Sora:wght@600;700&display=swap" rel="stylesheet">
<style>
    /* Theme colors themselves (daylight = :root default, plus
       [data-theme="nightflight|terracotta|retro-wanderlust|sakura-bloom"])
       live globally in style.css so this page's own background/sidebar/etc.
       — not just the swatches below — reskin along with the rest of the app. */
    .appearance-page {
        --space-3: 12px; --space-4: 16px; --space-5: 24px; --space-6: 32px;
        --font-display: 'Sora', system-ui, sans-serif;
        --font-body: 'Inter', system-ui, sans-serif;
        --ease: 180ms cubic-bezier(.4,0,.2,1);
        font-family: var(--font-body);
    }
    .appearance-page h1, .appearance-page .section-label { font-family: var(--font-display); }

    .appearance-page .page-head { margin-bottom: var(--space-6); }
    .appearance-page .page-head h1 { margin: 0 0 4px; font-size: 22px; color: var(--dark); }
    .appearance-page .page-head p { margin: 0; color: var(--muted); font-size: 14px; }

    .appearance-page .section-label {
        font-size: 13px; font-weight: 600; color: var(--muted);
        text-transform: uppercase; letter-spacing: .04em; margin-bottom: var(--space-3);
    }

    .theme-swatch-grid { display: flex; flex-wrap: wrap; gap: 14px; }
    .theme-swatch {
        position: relative;
        width: 56px; height: 56px;
        border-radius: 16px;
        border: 2px solid var(--border);
        background: var(--bg-white);
        cursor: pointer;
        padding: 0;
        overflow: hidden;
        transition: transform 140ms ease, border-color var(--ease), box-shadow var(--ease);
    }
    .theme-swatch:hover { transform: translateY(-2px); }
    .theme-swatch[aria-checked="true"] { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-light); }

    .theme-swatch.split { display: flex; }
    .theme-swatch.split .swatch-half { width: 50%; height: 100%; }

    .theme-swatch-check {
        position: absolute; top: -6px; right: -6px;
        width: 18px; height: 18px; border-radius: 50%;
        background: var(--primary); color: #fff;
        display: flex; align-items: center; justify-content: center;
        opacity: 0; transform: scale(.6);
        transition: opacity var(--ease), transform var(--ease);
    }
    .theme-swatch[aria-checked="true"] .theme-swatch-check { opacity: 1; transform: scale(1); }
    .theme-swatch-check svg { width: 10px; height: 10px; stroke: currentColor; stroke-width: 3.5; fill: none; }

    .theme-swatch-caption { margin-top: var(--space-3); font-size: 13px; color: var(--muted); }
    .theme-swatch-caption strong { color: var(--dark); font-weight: 600; }

    @media (prefers-reduced-motion: reduce) {
        .theme-swatch, .theme-swatch:hover { transition: none; transform: none; }
    }
</style>
@endpush

@section('content')
<div class="appearance-page">

    <div class="page-head">
        <h1>Appearance</h1>
        <p>Pick how Budgetra looks. This only affects your own account.</p>
    </div>

    @if (session('success'))
    <div class="alert alert-success mb-16">{{ session('success') }}</div>
    @endif

    <div class="section-label">Theme</div>
    <div class="theme-swatch-grid" role="radiogroup" aria-label="Theme" id="themeGrid" data-current-theme="{{ $user->theme ?? 'daylight' }}">

        @foreach ([
            'daylight'          => 'Daylight — bright, sky-blue, easy on the eyes outdoors',
            'nightflight'       => 'Nightflight — deep navy, easy on the eyes at night',
            'terracotta'        => 'Terracotta Trail — warm clay and sand, adventure-trip feel',
            'retro-wanderlust'  => 'Retro Wanderlust — aged-paper cream and petrol teal, a vintage travel-poster feel',
            'sakura-bloom'      => 'Sakura Bloom — soft blossom pink and warm ink charcoal, delicate and seasonal',
        ] as $value => $label)
        <button class="theme-swatch" role="radio" title="{{ $label }}" aria-label="{{ $label }}"
                aria-checked="{{ ($user->theme ?? 'daylight') === $value ? 'true' : 'false' }}"
                data-theme-choice="{{ $value }}" data-theme="{{ $value }}" type="button">
            <span class="theme-swatch-check"><svg viewBox="0 0 24 24"><path d="M4 12l5 5L20 6"/></svg></span>
        </button>
        @endforeach

        <button class="theme-swatch split" role="radio" title="Auto — follows your device's light/dark setting" aria-label="Auto — follows your device's light/dark setting"
                aria-checked="{{ ($user->theme ?? 'daylight') === 'auto' ? 'true' : 'false' }}"
                data-theme-choice="auto" type="button">
            <span class="swatch-half" data-theme="daylight" style="background:var(--bg-white)"></span>
            <span class="swatch-half" data-theme="nightflight" style="background:var(--bg-white)"></span>
            <span class="theme-swatch-check"><svg viewBox="0 0 24 24"><path d="M4 12l5 5L20 6"/></svg></span>
        </button>

    </div>

    <div class="theme-swatch-caption" id="themeCaption"></div>
</div>

<script>
(function () {
    var grid = document.getElementById('themeGrid');
    var caption = document.getElementById('themeCaption');
    if (!grid) return;

    var cards = grid.querySelectorAll('[data-theme-choice]');
    var media = window.matchMedia('(prefers-color-scheme: dark)');
    var selection = grid.dataset.currentTheme || 'daylight';

    function resolve(choice) {
        if (choice !== 'auto') return choice;
        return media.matches ? 'nightflight' : 'daylight';
    }

    function reflect(choice) {
        document.body.setAttribute('data-theme', resolve(choice));
        cards.forEach(function (c) {
            c.setAttribute('aria-checked', String(c.dataset.themeChoice === choice));
        });
        var active = grid.querySelector('[data-theme-choice="' + choice + '"]');
        if (caption && active) caption.innerHTML = active.getAttribute('title');
    }

    function save(choice) {
        fetch('{{ route('settings.theme') }}', {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify({ theme: choice }),
        });
    }

    reflect(selection);

    cards.forEach(function (card) {
        card.addEventListener('click', function () {
            selection = card.dataset.themeChoice;
            reflect(selection);
            save(selection);
        });
    });

    media.addEventListener('change', function () {
        if (selection === 'auto') reflect('auto');
    });
})();
</script>
@endsection
