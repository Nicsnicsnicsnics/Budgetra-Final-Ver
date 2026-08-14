@extends('layouts.app')
@section('title', $attraction->name)
@section('content')

@php
    $categoryMeta = [
        'Culture'   => ['icon' => 'fa-landmark',      'color' => '#C084FC'],
        'Nature'    => ['icon' => 'fa-leaf',          'color' => '#4ADE80'],
        'Adventure' => ['icon' => 'fa-person-hiking', 'color' => '#FB923C'],
        'Shopping'  => ['icon' => 'fa-bag-shopping',  'color' => '#60A5FA'],
    ][$attraction->category] ?? ['icon' => 'fa-map-pin', 'color' => 'var(--primary)'];
    $ratingRounded = round($avgRating);
@endphp

<a href="{{ route('attractions.index') }}" class="atd-back">
    <i class="fa-solid fa-arrow-left"></i> All Attractions
</a>

{{-- Hero + About --}}
<div class="atd-top-grid mb-24">
    <div class="atd-hero">
        @if ($attraction->image)
        <img src="{{ asset('storage/' . $attraction->image) }}" alt="{{ $attraction->name }}" class="atd-hero-img">
        @else
        <div class="atd-hero-noimg"><i class="fa-solid fa-image"></i></div>
        @endif
        <div class="atd-hero-scrim"></div>

        @if ($attraction->category)
        <span class="atd-chip atd-chip-category" style="--chip-color:{{ $categoryMeta['color'] }};">
            <i class="fa-solid {{ $categoryMeta['icon'] }}"></i> {{ $attraction->category }}
        </span>
        @endif

        <div class="atd-hero-content">
            <h1 class="atd-hero-name">{{ $attraction->name }}</h1>
            <div class="atd-hero-location"><i class="fa-solid fa-location-dot"></i> {{ $attraction->destination }}</div>
            <div class="atd-hero-rating">
                <span class="atd-stars">
                    @for ($i = 1; $i <= 5; $i++)
                        <i class="fa-{{ $i <= $ratingRounded ? 'solid' : 'regular' }} fa-star"></i>
                    @endfor
                </span>
                <span class="atd-rating-num">{{ number_format($avgRating, 1) }}</span>
                <span class="atd-rating-count">({{ $reviews->count() }} {{ Str::plural('review', $reviews->count()) }})</span>
            </div>
        </div>
    </div>

    {{-- Description --}}
    <div class="atd-card atd-about-card">
        <h2 class="atd-section-title">About</h2>
        @if ($attraction->description)
        <p class="atd-about-text">{{ $attraction->description }}</p>
        @else
        <p class="atd-about-text atd-about-empty">No description available yet for this attraction.</p>
        @endif
    </div>
</div>

<div class="atd-grid mb-24">
    {{-- Reviews list --}}
    <div class="atd-col">
        <h2 class="atd-section-title mb-16">Reviews</h2>
        @if (session('success'))
        <div class="alert alert-success mb-16">{{ session('success') }}</div>
        @endif

        <div class="atd-reviews-list">
        @forelse ($reviews as $review)
        @php
            $initials = collect(explode(' ', $review->user->full_name ?? 'Traveler'))->filter()->map(fn($p) => mb_substr($p, 0, 1))->take(2)->implode('');
        @endphp
        <div class="atd-review-card">
            <div class="atd-review-head">
                <div class="atd-review-who">
                    <div class="atd-review-avatar">{{ strtoupper($initials) }}</div>
                    <div>
                        <div class="atd-review-name">{{ $review->user->full_name ?? 'Traveler' }}</div>
                        <div class="atd-review-date">{{ $review->created_at->format('M j, Y') }}</div>
                    </div>
                </div>
                <div class="atd-review-head-right">
                    <span class="atd-review-stars">
                        @for ($i = 1; $i <= 5; $i++)
                            <i class="fa-{{ $i <= $review->rating ? 'solid' : 'regular' }} fa-star"></i>
                        @endfor
                    </span>
                    @if ($review->user_id !== auth()->id())
                        @if ($review->flagged_by === auth()->id())
                        <span class="atd-review-flagged" title="You flagged this review"><i class="fa-solid fa-flag"></i></span>
                        @else
                        <details class="atd-review-flag">
                            <summary title="Report this review"><i class="fa-regular fa-flag"></i></summary>
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
            <p class="atd-review-body">{{ $review->body }}</p>
        </div>
        @empty
        <div class="atd-empty">
            <div class="atd-empty-icon"><i class="fa-regular fa-comment-dots"></i></div>
            <p>No reviews yet. Be the first!</p>
        </div>
        @endforelse
        </div>
    </div>

    {{-- Add review form --}}
    <div class="atd-col">
        <h2 class="atd-section-title mb-16">Leave a Review</h2>
        @auth
        @if ($hasReviewed)
        <div class="atd-already-reviewed">
            <i class="fa-solid fa-circle-check"></i> You've already reviewed this attraction.
        </div>
        @else
        <div class="atd-card">
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
                    <label class="form-label">Your Review</label>
                    <textarea name="body" class="form-control atd-textarea {{ $errors->has('body') ? 'is-invalid' : '' }}"
                              placeholder="Share your experience (min. 10 characters)"
                              rows="4" required>{{ old('body') }}</textarea>
                    @error('body')<div class="error">{{ $message }}</div>@enderror
                </div>

                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fa-solid fa-paper-plane"></i> Submit Review
                </button>
            </form>
        </div>
        @endif
        @endauth
    </div>
</div>

<style>
    .atd-back {
        display: inline-flex; align-items: center; gap: 6px;
        font-size: 13px; font-weight: 600; color: var(--primary); text-decoration: none;
        background: none; border: none; padding: 0; margin-bottom: 10px;
        transition: transform .15s ease;
    }
    .atd-back:hover { transform: translateX(-2px); }
    .atd-back i { font-size: 11px; }

    .atd-top-grid {
        display: grid; grid-template-columns: 1.2fr 1fr; gap: 24px; align-items: stretch;
    }
    @media (max-width: 860px) { .atd-top-grid { grid-template-columns: 1fr; } }

    .atd-hero {
        position: relative; border-radius: 22px; overflow: hidden;
        height: 340px; min-height: 340px; flex-shrink: 0;
        background: var(--bg);
        box-shadow: 0 12px 32px rgba(0,0,0,0.14);
    }
    .atd-about-card { display: flex; flex-direction: column; }
    .atd-about-empty { font-style: italic; opacity: .8; }
    .atd-hero-img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
    .atd-hero-noimg {
        position: absolute; inset: 0; display: flex; align-items: center; justify-content: center;
        background: linear-gradient(150deg, var(--primary) 0%, #C9A84C 100%); color: rgba(255,255,255,.5); font-size: 48px;
    }
    .atd-hero-scrim {
        position: absolute; inset: 0;
        background: linear-gradient(to top, rgba(8,6,16,0.88) 0%, rgba(8,6,16,0.35) 45%, rgba(8,6,16,0.05) 70%);
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
    .atd-hero-content { position: absolute; left: 24px; right: 24px; bottom: 40px; z-index: 2; }
    .atd-hero-name { color: #fff; font-size: 32px; font-weight: 800; line-height: 1.15; margin: 0 0 8px; text-shadow: 0 2px 10px rgba(0,0,0,.35); }
    .atd-hero-location { color: rgba(255,255,255,.88); font-size: 14px; font-weight: 600; margin-bottom: 10px; display: flex; align-items: center; gap: 7px; }
    .atd-hero-location i { font-size: 12px; }
    .atd-hero-rating { display: flex; align-items: center; gap: 9px; }
    .atd-stars { color: #F5A623; font-size: 15px; letter-spacing: 2px; }
    .atd-stars i.fa-regular { color: rgba(255,255,255,.35); }
    .atd-rating-num { color: #fff; font-size: 14.5px; font-weight: 700; }
    .atd-rating-count { color: rgba(255,255,255,.72); font-size: 12.5px; }

    .atd-card {
        background: var(--bg-white); border: 1.5px solid var(--border); border-radius: 18px; padding: 22px 24px;
    }
    .atd-section-title { font-size: 18px; font-weight: 800; color: var(--dark); margin: 0 0 10px; }
    .atd-about-text { font-size: 14px; line-height: 1.75; color: var(--muted); margin: 0; }

    .atd-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; align-items: stretch; }
    @media (max-width: 860px) { .atd-grid { grid-template-columns: 1fr; } }
    .atd-col { display: flex; flex-direction: column; }
    .atd-reviews-list { display: flex; flex-direction: column; flex: 1; }
    .atd-reviews-list .atd-empty { flex: 1; }
    .atd-col > .atd-card,
    .atd-col > .atd-already-reviewed { flex: 1; }

    .atd-review-card {
        background: var(--bg-white); border: 1.5px solid var(--border); border-radius: 16px;
        padding: 16px 18px; margin-bottom: 12px;
    }
    .atd-review-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; gap: 10px; }
    .atd-review-who { display: flex; align-items: center; gap: 10px; min-width: 0; }
    .atd-review-avatar {
        width: 38px; height: 38px; border-radius: 50%; flex-shrink: 0;
        background: var(--primary); color: #fff; font-size: 13px; font-weight: 700;
        display: flex; align-items: center; justify-content: center;
    }
    .atd-review-name { font-size: 13.5px; font-weight: 700; color: var(--dark); }
    .atd-review-date { font-size: 11px; color: var(--muted); margin-top: 1px; }
    .atd-review-stars { color: #F5A623; font-size: 12.5px; letter-spacing: 1.5px; flex-shrink: 0; }
    .atd-review-stars i.fa-regular { color: var(--border); }
    .atd-review-body { font-size: 13.5px; color: var(--muted); line-height: 1.65; margin: 0; }

    .atd-review-head-right { display: flex; align-items: center; gap: 10px; flex-shrink: 0; }
    .atd-review-flagged { color: var(--danger); font-size: 12px; }
    .atd-review-flag { position: relative; }
    .atd-review-flag summary { list-style: none; cursor: pointer; color: var(--muted); font-size: 12px; }
    .atd-review-flag summary::-webkit-details-marker { display: none; }
    .atd-review-flag summary:hover { color: var(--danger); }
    .atd-review-flag[open] summary { color: var(--danger); }
    .atd-review-flag-menu {
        position: absolute; right: 0; top: 22px; z-index: 20; background: var(--bg-white);
        border: 1.5px solid var(--border); border-radius: 10px; padding: 6px; box-shadow: 0 8px 24px rgba(0,0,0,.12);
        display: flex; flex-direction: column; gap: 2px; min-width: 170px;
    }
    .atd-review-flag-menu form { margin: 0; }
    .atd-review-flag-menu button {
        width: 100%; text-align: left; background: none; border: none; padding: 7px 10px; border-radius: 6px;
        font-size: 12.5px; color: var(--dark); cursor: pointer;
    }
    .atd-review-flag-menu button:hover { background: var(--border-light); }

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

    /* Interactive star-rating picker: markup is 5→1 in DOM order so the
       CSS sibling-combinator hover/checked trick can light up "this star
       and everything before it" despite visually displaying 1→5. */
    .atd-star-input { display: flex; flex-direction: row-reverse; justify-content: flex-end; gap: 4px; }
    .atd-star-input input { position: absolute; opacity: 0; width: 0; height: 0; }
    .atd-star-input label {
        font-size: 24px; color: var(--border); cursor: pointer; transition: color .12s ease, transform .12s ease;
    }
    .atd-star-input label:hover,
    .atd-star-input label:hover ~ label,
    .atd-star-input input:checked ~ label { color: #F5A623; }
    .atd-star-input label:active { transform: scale(1.15); }

    .atd-textarea { resize: vertical; }
</style>

@endsection
