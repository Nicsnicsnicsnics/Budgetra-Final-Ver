@extends('layouts.app')
@section('title', $attraction->name)

@push('styles')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,700&family=IBM+Plex+Mono:wght@500;600&display=swap" rel="stylesheet">
@endpush

@section('content')

@php
    $categoryMeta = [
        'Culture'   => ['icon' => 'fa-landmark',      'color' => '#C084FC'],
        'Nature'    => ['icon' => 'fa-leaf',          'color' => '#4ADE80'],
        'Adventure' => ['icon' => 'fa-person-hiking', 'color' => '#FB923C'],
        'Shopping'  => ['icon' => 'fa-bag-shopping',  'color' => '#60A5FA'],
    ][$attraction->category] ?? ['icon' => 'fa-map-pin', 'color' => 'var(--primary)'];
@endphp

<a href="{{ route('attractions.index') }}" class="atd-back">
    <i class="fa-solid fa-arrow-left"></i> All Attractions
</a>

{{-- Hero photo --}}
<div class="atd-hero">
    @if ($attraction->image)
    <img src="{{ asset('storage/' . $attraction->image) }}" alt="{{ $attraction->name }}" class="atd-hero-img">
    @else
    <div class="atd-hero-noimg"><i class="fa-solid fa-image"></i></div>
    @endif

    <div class="atd-hero-overlay">
        <div class="atd-hero-eyebrow">Attraction · {{ $attraction->destination }}</div>
        <h1 class="atd-hero-title">{{ $attraction->name }}</h1>
    </div>

    @if ($attraction->category)
    <span class="atd-chip atd-chip-category" style="--chip-color:{{ $categoryMeta['color'] }};">
        <i class="fa-solid {{ $categoryMeta['icon'] }}"></i> {{ $attraction->category }}
    </span>
    @endif
</div>

@php
    $ratingCounts = $reviews->countBy('rating');
    $totalReviews = $reviews->count();
@endphp

{{-- Rating summary — floats up over the bottom of the hero photo --}}
@if ($totalReviews > 0)
<div class="atd-rev-summary">
    <div class="atd-rev-score">
        <div class="atd-rev-score-num">{{ number_format($avgRating, 1) }}</div>
        <div class="atd-review-stars" style="font-size:16px;">
            @for ($i = 1; $i <= 5; $i++)
                <i class="fa-{{ $i <= round($avgRating) ? 'solid' : 'regular' }} fa-star"></i>
            @endfor
        </div>
        <div class="atd-rev-score-count">{{ $totalReviews }} {{ \Illuminate\Support\Str::plural('rating', $totalReviews) }}</div>
    </div>
    <div class="atd-rev-bars">
        @for ($star = 5; $star >= 1; $star--)
        @php
            $count = $ratingCounts[$star] ?? 0;
            $pct   = $totalReviews > 0 ? round($count / $totalReviews * 100) : 0;
        @endphp
        <div class="atd-rev-bar-row">
            <span>{{ $star }}</span>
            <div class="atd-rev-bar-track"><div class="atd-rev-bar-fill" style="width:{{ $pct }}%;"></div></div>
            <span>{{ $count }}</span>
        </div>
        @endfor
    </div>
    @if ($costAccuracyPct !== null)
    <div class="atd-rev-accuracy">
        <div class="atd-rev-accuracy-label">Cost matched estimate</div>
        <div class="atd-rev-accuracy-value">{{ $costAccuracyPct }}%</div>
        <div class="atd-rev-accuracy-sub">of {{ $reviewsWithSpend->count() }} {{ \Illuminate\Support\Str::plural('review', $reviewsWithSpend->count()) }} with spend data</div>
        <div class="atd-rev-tier-badge">Est. ₱{{ number_format($attraction->estimated_cost, 0) }}/person</div>
    </div>
    @endif
</div>
@endif

{{-- Description — name/location now live in the hero overlay above --}}
<div class="atd-caption mb-24">
    @if ($attraction->description)
    <p class="atd-caption-text">{{ $attraction->description }}</p>
    @else
    <p class="atd-caption-text atd-about-empty">No description available yet for this attraction.</p>
    @endif
</div>

{{-- Reviews --}}
<div x-data="{ showReviewForm: false }">
<div class="mb-24" id="reviews">
    <div class="atd-rev-controls">
        <h2 class="atd-section-title" style="margin:0;">Reviews</h2>
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
            @if ($totalReviews > 1)
            <select class="atd-rev-sort" id="atdReviewSort" onchange="atdSortReviews(this.value)">
                <option value="recent">Sort: Most recent</option>
                <option value="highest">Highest rated</option>
                <option value="lowest">Lowest rated</option>
            </select>
            <select class="atd-rev-sort" id="atdReviewFilter" onchange="atdFilterReviews(this.value)">
                <option value="">Trip type: All</option>
                <option value="Solo">Solo</option>
                <option value="Couple">Couple</option>
                <option value="Family">Family</option>
                <option value="Barkada">Barkada / Group</option>
            </select>
            @endif
            @auth
            @if (!$hasReviewed)
            <button type="button" class="atd-rev-write-btn" @click="showReviewForm = true">
                Write a review
            </button>
            @endif
            @endauth
        </div>
    </div>

    @if (session('success'))
    <div class="alert alert-success mb-16">{{ session('success') }}</div>
    @endif

    <div class="atd-reviews-list" id="atdReviewsList">
    @forelse ($reviews as $review)
    @php
        $initials = collect(explode(' ', $review->user->full_name ?? 'Traveler'))->filter()->map(fn($p) => mb_substr($p, 0, 1))->take(2)->implode('');
        $isMine = $review->user_id === auth()->id();
        $whoLabel = trim($review->user->full_name ?? 'Traveler');
        $perPerson = ($review->spent_amount !== null && $review->pax_count) ? (float) $review->spent_amount / max(1, $review->pax_count) : null;
        $estTotal  = $attraction->estimated_cost ? (float) $attraction->estimated_cost * max(1, $review->pax_count ?? 1) : null;
        $isUnder   = $perPerson !== null && $attraction->estimated_cost && $perPerson <= (float) $attraction->estimated_cost;
    @endphp
    <div class="atd-review-row" x-data="{ editing: false, expanded: false }"
         data-rating="{{ $review->rating }}" data-time="{{ $review->created_at->timestamp }}" data-trip-type="{{ $review->trip_type }}">
        @if ($review->user?->profile_photo)
        <img src="{{ Illuminate\Support\Facades\Storage::url($review->user->profile_photo) }}" alt="{{ $review->user->full_name }}" class="atd-review-avatar atd-review-avatar-img">
        @else
        <div class="atd-review-avatar">{{ strtoupper($initials) }}</div>
        @endif

        <div style="min-width:0;">
            <div class="atd-review-row-head">
                <div>
                    <span class="atd-review-name">{{ $whoLabel }}</span>
                    @if ($review->trip_type)
                    <span class="atd-trip-badge">{{ $review->trip_type }}{{ $review->pax_count ? ' · ' . $review->pax_count . ' pax' : '' }}</span>
                    @endif
                </div>
                <span class="atd-review-date">{{ $review->created_at->format('M j, Y') }}</span>
            </div>
            <div class="atd-review-stars" x-show="!editing" @if ($isMine) x-cloak @endif style="margin:4px 0 8px;">
                @for ($i = 1; $i <= 5; $i++)
                    <i class="fa-{{ $i <= $review->rating ? 'solid' : 'regular' }} fa-star"></i>
                @endfor
            </div>
            @if ($perPerson !== null)
            <div class="atd-spend-chip {{ $isUnder ? 'is-under' : '' }}">
                {{ $isUnder ? '✓ ' : '' }}Spent ₱{{ number_format($review->spent_amount, 0) }}
                @if ($estTotal !== null)
                · est. ₱{{ number_format($estTotal, 0) }}
                @endif
            </div>
            @endif

            @if ($isMine)
            <div x-show="!editing">
                <p class="atd-review-body" :class="{ 'atd-review-body-clamped': !expanded }">{{ $review->body }}</p>
                <button type="button" class="atd-review-more" @click="expanded = !expanded" x-show="!expanded" x-text="'Read more'"></button>
            </div>
            <form method="POST" action="{{ route('reviews.update', $review) }}" x-show="editing" x-cloak class="atd-review-edit-form">
                @csrf
                @method('PUT')
                <input type="hidden" name="attraction_id" value="{{ $attraction->id }}">
                <div class="atd-star-input atd-star-input-sm">
                    @for ($i = 5; $i >= 1; $i--)
                    <input type="radio" name="rating" id="edit-star{{ $review->id }}-{{ $i }}" value="{{ $i }}" {{ $review->rating == $i ? 'checked' : '' }}>
                    <label for="edit-star{{ $review->id }}-{{ $i }}" title="{{ $i }} star"><i class="fa-solid fa-star"></i></label>
                    @endfor
                </div>
                <div class="atd-trip-type-picker">
                    @foreach (['Solo', 'Couple', 'Family', 'Barkada'] as $type)
                    <label class="atd-trip-type-option">
                        <input type="radio" name="trip_type" value="{{ $type }}" {{ $review->trip_type === $type ? 'checked' : '' }}>
                        <span>{{ $type }}</span>
                    </label>
                    @endforeach
                </div>
                <div class="atd-form-row-2">
                    <input type="number" name="pax_count" min="1" max="50" value="{{ $review->pax_count ?? 1 }}" placeholder="Travelers" class="form-control atd-textinput">
                    <input type="number" step="0.01" min="0" name="spent_amount" value="{{ $review->spent_amount }}" placeholder="Amount spent (₱)" class="form-control atd-textinput">
                </div>
                <textarea name="body" class="form-control atd-textarea" rows="3" minlength="10" required>{{ $review->body }}</textarea>
                <button type="submit" class="btn btn-primary btn-sm">Save Changes</button>
            </form>
            @else
            <p class="atd-review-body" :class="{ 'atd-review-body-clamped': !expanded }">{{ $review->body }}</p>
            <button type="button" class="atd-review-more" @click="expanded = !expanded" x-show="!expanded" x-text="'Read more'"></button>
            @endif

            <div class="atd-review-foot">
                <form method="POST" action="{{ route('reviews.helpful', $review) }}" style="margin:0;">
                    @csrf
                    <button type="submit" class="atd-helpful-btn {{ $review->helpfulVotes->contains('user_id', auth()->id()) ? 'is-marked' : '' }}">
                        <i class="fa-regular fa-thumbs-up"></i> Helpful @if ($review->helpful_count > 0)<span>({{ $review->helpful_count }})</span>@endif
                    </button>
                </form>
                @if ($isMine)
                <button type="button" class="atd-foot-link" @click="editing = !editing" x-text="editing ? 'Cancel' : 'Edit'"></button>
                @else
                    @if ($review->flagged_by === auth()->id())
                    <span class="atd-review-flagged" title="You flagged this review"><i class="fa-solid fa-flag"></i> Flagged</span>
                    @else
                    <details class="atd-review-flag">
                        <summary class="atd-foot-link" title="Report this review">Report</summary>
                        <div class="atd-review-flag-menu">
                            <form method="POST" action="{{ route('reviews.flag', $review) }}">
                                @csrf
                                <input type="hidden" name="reason" value="inappropriate">
                                <button type="submit">Inappropriate</button>
                            </form>
                            <form method="POST" action="{{ route('reviews.flag', $review) }}">
                                @csrf
                                <input type="hidden" name="reason" value="improvement">
                                <button type="submit">Suggest info update</button>
                            </form>
                        </div>
                    </details>
                    @endif
                @endif
            </div>
        </div>
    </div>
    @empty
    <div class="atd-empty">
        <div class="atd-empty-icon"><i class="fa-regular fa-comment-dots"></i></div>
        <p>No reviews yet. Be the first!</p>
    </div>
    @endforelse
    </div>
</div>

{{-- Write-a-review modal — hidden until the button above is clicked --}}
@if (!$hasReviewed)
<div class="atd-modal-backdrop" x-show="showReviewForm" x-cloak style="display:none;"
     @click="if (event.target === $el) showReviewForm = false" @keydown.escape.window="showReviewForm = false">
    <div class="atd-modal-card">
        <h2 class="atd-section-title mb-16" style="margin:0 0 18px;">Leave a Review</h2>
        @if ($errors->any())
        <div class="alert alert-danger mb-16">{{ $errors->first() }}</div>
        @endif
        <form method="POST" action="{{ route('reviews.store') }}">
            @csrf
            <input type="hidden" name="destination"   value="{{ $attraction->destination }}">
            <input type="hidden" name="attraction_id" value="{{ $attraction->id }}">

            <div class="form-group">
                <label class="form-label">Your Rating</label>
                <div class="atd-star-input">
                    @for ($i = 5; $i >= 1; $i--)
                    <input type="radio" name="rating" id="star{{ $i }}" value="{{ $i }}"
                           {{ old('rating') == $i ? 'checked' : '' }}>
                    <label for="star{{ $i }}" title="{{ $i }} star"><i class="fa-solid fa-star"></i></label>
                    @endfor
                </div>
                @error('rating')<div class="error">{{ $message }}</div>@enderror
            </div>

            <div class="form-group">
                <label class="form-label">Trip Type (optional)</label>
                <div class="atd-trip-type-picker">
                    @foreach (['Solo', 'Couple', 'Family', 'Barkada'] as $type)
                    <label class="atd-trip-type-option">
                        <input type="radio" name="trip_type" value="{{ $type }}" {{ old('trip_type') === $type ? 'checked' : '' }}>
                        <span>{{ $type }}</span>
                    </label>
                    @endforeach
                </div>
            </div>

            <div class="form-group atd-form-row-2">
                <div>
                    <label class="form-label">Travelers</label>
                    <input type="number" name="pax_count" min="1" max="50" value="{{ old('pax_count', 1) }}" class="form-control atd-textinput">
                </div>
                <div>
                    <label class="form-label">Amount Spent (optional)</label>
                    <input type="number" step="0.01" min="0" name="spent_amount" value="{{ old('spent_amount') }}" placeholder="e.g. 450" class="form-control atd-textinput">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Your Review</label>
                <textarea name="body" class="form-control atd-textarea {{ $errors->has('body') ? 'is-invalid' : '' }}"
                          placeholder="Share your experience (min. 10 characters)"
                          rows="4" required>{{ old('body') }}</textarea>
                @error('body')<div class="error">{{ $message }}</div>@enderror
            </div>

            <button type="submit" class="atd-submit-btn">
                <i class="fa-solid fa-paper-plane"></i> Submit Review
            </button>
            <button type="button" class="atd-modal-cancel-btn" @click="showReviewForm = false">Close</button>
        </form>
    </div>
</div>
@endif
</div>

<script>
    function atdSortReviews(mode) {
        const list = document.getElementById('atdReviewsList');
        if (!list) return;
        const items = Array.from(list.children).filter(el => el.classList.contains('atd-review-row'));
        items.sort((a, b) => {
            if (mode === 'highest') return b.dataset.rating - a.dataset.rating;
            if (mode === 'lowest')  return a.dataset.rating - b.dataset.rating;
            return b.dataset.time - a.dataset.time;
        });
        items.forEach(el => list.appendChild(el));
    }

    function atdFilterReviews(type) {
        const list = document.getElementById('atdReviewsList');
        if (!list) return;
        const items = Array.from(list.children).filter(el => el.classList.contains('atd-review-row'));
        items.forEach(el => {
            el.style.display = (!type || el.dataset.tripType === type) ? '' : 'none';
        });
    }
</script>

<style>
    .atd-back {
        display: inline-flex; align-items: center; gap: 6px;
        font-size: 13px; font-weight: 600; color: var(--primary); text-decoration: none;
        background: none; border: none; padding: 0; margin-bottom: 10px;
        transition: transform .15s ease;
    }
    .atd-back:hover { transform: translateX(-2px); }
    .atd-back i { font-size: 11px; }

    .atd-hero {
        position: relative; border-radius: 22px; overflow: hidden;
        height: 420px; min-height: 420px; flex-shrink: 0;
        background: var(--bg);
        box-shadow: 0 12px 32px rgba(0,0,0,0.14);
    }
    .atd-about-empty { font-style: italic; opacity: .8; }
    .atd-hero-img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
    .atd-hero-noimg {
        position: absolute; inset: 0; display: flex; align-items: center; justify-content: center;
        background: linear-gradient(150deg, var(--primary) 0%, #C9A84C 100%); color: rgba(255,255,255,.5); font-size: 48px;
    }
    .atd-chip {
        position: absolute; top: 18px; left: 18px; z-index: 2;
        display: inline-flex; align-items: center; gap: 6px;
        font-size: 11.5px; font-weight: 700; letter-spacing: .02em; color: #fff;
        padding: 7px 14px; border-radius: 99px;
        background: color-mix(in srgb, var(--chip-color) 55%, rgba(0,0,0,.35));
        border: 1px solid rgba(255,255,255,.28);
        backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px);
    }

    .atd-hero-overlay {
        position: absolute; inset: 0; z-index: 1;
        display: flex; flex-direction: column; justify-content: flex-end;
        /* Generous bottom padding — the floating rating-summary card below
           pulls up -70px over the hero's bottom edge, so the title needs
           enough clearance to never sit behind it. */
        padding: 28px 32px 110px;
        background: linear-gradient(180deg, rgba(0,0,0,0) 35%, rgba(0,0,0,.68) 100%);
    }
    .atd-hero-eyebrow {
        font-family: 'IBM Plex Mono', monospace; font-size: 12px; letter-spacing: .14em; text-transform: uppercase;
        color: rgba(255,255,255,.85); margin-bottom: 8px;
    }
    .atd-hero-title { font-family: 'Fraunces', serif; font-weight: 700; font-size: 36px; line-height: 1.1; color: #fff; margin: 0; }
    @media (max-width: 600px) {
        .atd-hero-overlay { padding: 20px 20px 90px; }
        .atd-hero-title { font-size: 28px; }
    }

    .atd-caption-text { font-size: 17px; font-weight: 700; line-height: 1.6; color: var(--dark); margin: 0; max-width: 900px; }

    .atd-card {
        background: var(--bg-white); border: 1.5px solid var(--border); border-radius: 18px; padding: 22px 24px;
    }
    .atd-section-title { font-size: 18px; font-weight: 800; color: var(--dark); margin: 0 0 10px; }

    .atd-rev-summary {
        background: var(--bg-white); border: 1.5px solid var(--border); border-radius: 18px;
        padding: 26px 28px; display: flex; gap: 36px; align-items: center; flex-wrap: wrap;
        position: relative; z-index: 2; margin: -70px 0 28px;
        box-shadow: 0 16px 40px rgba(0,0,0,0.16);
    }
    @media (max-width: 600px) {
        .atd-rev-summary { margin-top: -40px; }
    }
    .atd-rev-score { text-align: center; min-width: 110px; flex-shrink: 0; }
    .atd-rev-score-num { font-family: 'Fraunces', serif; font-weight: 700; font-size: 44px; line-height: 1; color: var(--dark); }
    .atd-rev-score-count { font-size: 11.5px; color: var(--muted); margin-top: 6px; font-family: 'IBM Plex Mono', monospace; }
    .atd-rev-bars { flex: 1; min-width: 220px; display: flex; flex-direction: column; gap: 7px; }
    .atd-rev-bar-row {
        display: grid; grid-template-columns: 12px 1fr 26px; align-items: center; gap: 10px;
        font-size: 12px; color: var(--muted); font-family: 'IBM Plex Mono', monospace;
    }
    .atd-rev-bar-track { height: 6px; background: var(--border); border-radius: 3px; overflow: hidden; }
    .atd-rev-bar-fill { height: 100%; background: #F5A623; border-radius: 3px; }

    .atd-rev-accuracy { border-left: 1.5px solid var(--border); padding-left: 28px; min-width: 160px; flex-shrink: 0; }
    .atd-rev-accuracy-label { font-size: 10.5px; text-transform: uppercase; letter-spacing: .08em; color: var(--muted); margin-bottom: 6px; }
    .atd-rev-accuracy-value { font-family: 'IBM Plex Mono', monospace; font-weight: 600; font-size: 24px; color: var(--primary); }
    .atd-rev-accuracy-sub { font-size: 12px; color: var(--muted); margin-top: 2px; max-width: 160px; }
    .atd-rev-tier-badge {
        display: inline-block; margin-top: 10px; font-family: 'IBM Plex Mono', monospace; font-size: 11px;
        background: var(--primary-light); color: var(--primary); padding: 3px 9px; border-radius: 6px;
    }
    @media (max-width: 600px) {
        .atd-rev-accuracy { border-left: none; border-top: 1.5px solid var(--border); padding-left: 0; padding-top: 16px; width: 100%; }
    }

    .atd-rev-controls {
        display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;
        margin-bottom: 16px;
    }
    .atd-rev-sort {
        font-family: inherit; font-size: 12.5px; font-weight: 600; border: 1.5px solid var(--border);
        background: var(--bg-white); border-radius: 8px; padding: 7px 10px; color: var(--dark); cursor: pointer;
    }
    .atd-rev-write-btn {
        display: inline-flex; align-items: center; font-size: 13px; font-weight: 700; color: #fff;
        background: var(--primary); border-radius: 10px; padding: 8px 16px; text-decoration: none;
        transition: background .18s ease;
    }
    .atd-rev-write-btn:hover { background: var(--primary-dark); }
    .atd-reviews-list { display: flex; flex-direction: column; }
    .atd-review-row {
        display: grid; grid-template-columns: 46px 1fr; gap: 14px;
        padding: 22px 0; border-bottom: 1.5px solid var(--border);
    }
    .atd-review-row:first-child { padding-top: 0; }
    .atd-review-row:last-child { border-bottom: none; padding-bottom: 0; }
    .atd-review-row-head { display: flex; align-items: baseline; justify-content: space-between; gap: 10px; flex-wrap: wrap; }

    .atd-review-stars { color: #F5A623; font-size: 14px; letter-spacing: 1.5px; flex-shrink: 0; }
    .atd-review-stars i.fa-regular { color: var(--border); }

    .atd-trip-badge {
        font-size: 11px; font-weight: 600; color: var(--muted);
        border: 1.5px solid var(--border); border-radius: 6px; padding: 1px 8px; margin-left: 8px;
    }
    .atd-spend-chip {
        display: inline-flex; align-items: center; gap: 6px; font-family: 'IBM Plex Mono', monospace;
        font-size: 12px; background: var(--primary-light); color: var(--primary);
        padding: 3px 10px; border-radius: 6px; margin-bottom: 10px;
    }
    .atd-spend-chip.is-under { background: rgba(47,158,91,.12); color: #2F9E5B; }

    .atd-review-avatar {
        width: 46px; height: 46px; border-radius: 50%; flex-shrink: 0;
        background: var(--primary); color: #fff; font-family: 'Fraunces', serif; font-size: 16px; font-weight: 700;
        display: flex; align-items: center; justify-content: center;
    }
    .atd-review-avatar-img { object-fit: cover; }
    .atd-review-name { font-size: 14.5px; font-weight: 700; color: var(--dark); font-family: 'Fraunces', serif; }
    .atd-review-date { font-size: 11.5px; color: var(--muted); font-family: 'IBM Plex Mono', monospace; }
    .atd-review-body { font-size: 14px; color: var(--dark); line-height: 1.65; margin: 0; }
    .atd-review-body-clamped {
        display: -webkit-box; -webkit-line-clamp: 4; -webkit-box-orient: vertical; overflow: hidden;
    }
    .atd-review-more {
        background: none; border: none; padding: 0; margin-top: 4px; align-self: flex-start;
        font-size: 13px; font-weight: 700; color: var(--muted); text-decoration: underline; cursor: pointer;
    }
    .atd-review-more:hover { color: var(--primary); }

    .atd-review-foot { display: flex; align-items: center; gap: 14px; margin-top: 12px; }
    .atd-helpful-btn {
        display: inline-flex; align-items: center; gap: 6px; font-size: 12px; color: var(--muted);
        background: none; border: 1.5px solid var(--border); border-radius: 8px; padding: 6px 12px; cursor: pointer;
        transition: border-color .15s ease, color .15s ease; font-family: inherit;
    }
    .atd-helpful-btn:hover, .atd-helpful-btn.is-marked { border-color: var(--primary); color: var(--primary); }
    .atd-foot-link {
        font-size: 12px; color: var(--muted); cursor: pointer; text-decoration: underline;
        background: none; border: none; padding: 0; font-family: inherit; list-style: none;
    }
    .atd-foot-link:hover { color: var(--primary); }
    .atd-foot-link::-webkit-details-marker { display: none; }

    .atd-review-flagged { color: var(--danger); font-size: 12px; display: inline-flex; align-items: center; gap: 5px; }
    .atd-review-flag { position: relative; }
    .atd-review-flag[open] .atd-foot-link { color: var(--danger); }
    .atd-review-flag-menu {
        position: absolute; left: 0; top: 22px; z-index: 20; background: var(--bg-white);
        border: 1.5px solid var(--border); border-radius: 10px; padding: 6px; box-shadow: 0 8px 24px rgba(0,0,0,.12);
        display: flex; flex-direction: column; gap: 2px; min-width: 170px;
    }
    .atd-review-flag-menu form { margin: 0; }
    .atd-review-flag-menu button {
        width: 100%; text-align: left; background: none; border: none; padding: 7px 10px; border-radius: 6px;
        font-size: 12.5px; color: var(--dark); cursor: pointer;
    }
    .atd-review-flag-menu button:hover { background: var(--border-light); }

    .atd-review-edit-form { display: flex; flex-direction: column; gap: 10px; margin-top: 4px; }
    .atd-star-input-sm label { font-size: 18px; }
    .atd-review-edit-form .atd-textarea { min-height: 80px; }

    .atd-empty {
        text-align: center; padding: 48px 24px; background: var(--bg-white);
        border: 1.5px dashed var(--border); border-radius: 16px;
        display: flex; flex-direction: column; align-items: center; justify-content: center;
    }
    .atd-empty-icon {
        width: 52px; height: 52px; border-radius: 50%; background: var(--bg); margin: 0 auto 14px;
        display: flex; align-items: center; justify-content: center; color: var(--muted); font-size: 20px;
    }
    .atd-empty p { color: var(--muted); font-size: 13.5px; margin: 0; }

    .atd-already-reviewed {
        display: flex; align-items: center; gap: 10px;
        background: var(--primary-light); color: var(--primary); border-radius: 14px;
        padding: 16px 18px; font-size: 13.5px; font-weight: 700;
    }

    .atd-form-card {
        background: var(--bg-white); border: 1.5px solid var(--border); border-radius: 18px;
        padding: 24px 26px; max-width: 560px;
    }
    .atd-form-card .form-label,
    .atd-modal-card .form-label { font-size: 13px; font-weight: 700; color: var(--dark); margin-bottom: 8px; display: block; }
    .atd-form-card .form-group,
    .atd-modal-card .form-group { margin-bottom: 20px; }
    .atd-form-card .atd-textarea,
    .atd-form-card .atd-textinput,
    .atd-modal-card .atd-textarea,
    .atd-modal-card .atd-textinput {
        background: var(--bg); border: 1.5px solid var(--border); border-radius: 12px;
        padding: 12px 14px; font-family: inherit; font-size: 14px; color: var(--dark); width: 100%;
        box-sizing: border-box; transition: border-color .18s ease, background .18s ease;
    }
    .atd-form-card .atd-textarea:focus,
    .atd-form-card .atd-textinput:focus,
    .atd-modal-card .atd-textarea:focus,
    .atd-modal-card .atd-textinput:focus {
        outline: none; border-color: var(--primary); background: var(--bg-white);
    }
    .atd-form-row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }

    .atd-modal-backdrop {
        position: fixed; inset: 0; background: rgba(0,0,0,.55); backdrop-filter: blur(4px);
        z-index: 2100; display: flex; align-items: center; justify-content: center; padding: 20px;
    }
    .atd-modal-card {
        background: var(--bg-white); border: 1.5px solid var(--border); border-radius: 20px;
        padding: 26px 28px; width: 100%; max-width: 560px; max-height: 90vh; overflow-y: auto;
        box-sizing: border-box; animation: atdModalPop .2s cubic-bezier(.34,1.56,.64,1);
    }
    @keyframes atdModalPop { from { opacity: 0; transform: scale(.96) translateY(8px); } to { opacity: 1; transform: none; } }
    .atd-modal-cancel-btn {
        display: block; width: 100%; margin-top: 10px; background: transparent; color: var(--muted);
        border: 1.5px solid var(--border); border-radius: 12px; padding: 12px 0; font-size: 13px; font-weight: 700;
        font-family: inherit; cursor: pointer; transition: background .18s ease, border-color .18s ease;
    }
    .atd-modal-cancel-btn:hover { background: var(--border-light); border-color: var(--border); }
    .atd-trip-type-picker { display: flex; flex-wrap: wrap; gap: 8px; }
    .atd-trip-type-option input { position: absolute; opacity: 0; width: 0; height: 0; }
    .atd-trip-type-option span {
        display: inline-block; font-size: 13px; font-weight: 600; color: var(--muted);
        border: 1.5px solid var(--border); border-radius: 20px; padding: 7px 16px; cursor: pointer;
        transition: border-color .15s ease, color .15s ease, background .15s ease;
    }
    .atd-trip-type-option input:checked ~ span {
        border-color: var(--primary); color: var(--primary); background: var(--primary-light);
    }
    .atd-submit-btn {
        display: inline-flex; align-items: center; justify-content: center; gap: 8px;
        width: 100%; background: var(--primary); color: #fff; border: none; border-radius: 12px;
        padding: 13px 20px; font-size: 14px; font-weight: 700; cursor: pointer;
        transition: background .18s ease, transform .15s ease;
    }
    .atd-submit-btn:hover { background: var(--primary-dark); transform: translateY(-1px); }
    .atd-submit-btn:active { transform: translateY(0); }

    /* Interactive star-rating picker: markup is 5→1 in DOM order so the
       CSS sibling-combinator hover/checked trick can light up "this star
       and everything before it" despite visually displaying 1→5. */
    .atd-star-input { display: flex; flex-direction: row-reverse; justify-content: flex-end; gap: 6px; }
    .atd-star-input input { position: absolute; opacity: 0; width: 0; height: 0; }
    .atd-star-input label {
        font-size: 28px; color: var(--border); cursor: pointer; transition: color .12s ease, transform .12s ease;
    }
    .atd-star-input label:hover,
    .atd-star-input label:hover ~ label,
    .atd-star-input input:checked ~ label { color: #F5A623; }
    .atd-star-input label:active { transform: scale(1.15); }

    .atd-textarea { resize: vertical; }
</style>

@endsection
