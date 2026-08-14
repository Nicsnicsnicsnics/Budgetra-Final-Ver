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

{{-- Hero photo --}}
<div class="atd-hero">
    @if ($attraction->image)
    <img src="{{ asset('storage/' . $attraction->image) }}" alt="{{ $attraction->name }}" class="atd-hero-img">
    @else
    <div class="atd-hero-noimg"><i class="fa-solid fa-image"></i></div>
    @endif

    @if ($attraction->category)
    <span class="atd-chip atd-chip-category" style="--chip-color:{{ $categoryMeta['color'] }};">
        <i class="fa-solid {{ $categoryMeta['icon'] }}"></i> {{ $attraction->category }}
    </span>
    @endif
</div>

{{-- Caption: name/meta + description, editorial style below the photo --}}
<div class="atd-caption mb-24">
    <h1 class="atd-caption-name">{{ $attraction->name }}</h1>
    <div class="atd-caption-meta">
        <span class="atd-caption-location"><i class="fa-solid fa-location-dot"></i> {{ $attraction->destination }}</span>
        <span class="atd-caption-dot">&middot;</span>
        <span class="atd-stars">
            @for ($i = 1; $i <= 5; $i++)
                <i class="fa-{{ $i <= $ratingRounded ? 'solid' : 'regular' }} fa-star"></i>
            @endfor
        </span>
        <span class="atd-rating-num">{{ number_format($avgRating, 1) }}</span>
        <span class="atd-rating-count">({{ $reviews->count() }} {{ Str::plural('review', $reviews->count()) }})</span>
    </div>
    @if ($attraction->description)
    <p class="atd-caption-text">{{ $attraction->description }}</p>
    @else
    <p class="atd-caption-text atd-about-empty">No description available yet for this attraction.</p>
    @endif
</div>

{{-- Reviews --}}
<div class="mb-24">
    <h2 class="atd-section-title mb-16">Reviews</h2>
    @if (session('success'))
    <div class="alert alert-success mb-16">{{ session('success') }}</div>
    @endif

    <div class="atd-reviews-grid">
    @forelse ($reviews as $review)
    @php
        $initials = collect(explode(' ', $review->user->full_name ?? 'Traveler'))->filter()->map(fn($p) => mb_substr($p, 0, 1))->take(2)->implode('');
        $isMine = $review->user_id === auth()->id();
        $whoLabel = trim(($review->user->full_name ?? 'Traveler') . ($review->user->country ? ' — ' . $review->user->country : ''));
    @endphp
    <div class="atd-review-card" x-data="{ editing: false, expanded: false }">
        <div class="atd-review-head">
            <span class="atd-review-stars" x-show="!editing" @if ($isMine) x-cloak @endif>
                @for ($i = 1; $i <= 5; $i++)
                    <i class="fa-{{ $i <= $review->rating ? 'solid' : 'regular' }} fa-star"></i>
                @endfor
            </span>
            <span class="atd-review-rating-num" x-show="!editing" @if ($isMine) x-cloak @endif>{{ $review->rating }}</span>

            <div class="atd-review-head-right">
                @if ($isMine)
                <button type="button" class="atd-review-edit-btn" title="Edit your review" @click="editing = !editing" x-text="editing ? 'Cancel' : ''">
                    <i class="fa-regular fa-pen-to-square" x-show="!editing"></i>
                </button>
                @else
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

        <div class="atd-review-who">
            @if ($review->user?->profile_photo)
            <img src="{{ Illuminate\Support\Facades\Storage::url($review->user->profile_photo) }}" alt="{{ $review->user->full_name }}" class="atd-review-avatar atd-review-avatar-img">
            @else
            <div class="atd-review-avatar">{{ strtoupper($initials) }}</div>
            @endif
            <div>
                <div class="atd-review-name">{{ $whoLabel }}</div>
                <div class="atd-review-date">{{ $review->created_at->format('F j, Y') }}</div>
            </div>
        </div>

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
            <textarea name="body" class="form-control atd-textarea" rows="3" minlength="10" required>{{ $review->body }}</textarea>
            <button type="submit" class="btn btn-primary btn-sm">Save Changes</button>
        </form>
        @else
        <p class="atd-review-body" :class="{ 'atd-review-body-clamped': !expanded }">{{ $review->body }}</p>
        <button type="button" class="atd-review-more" @click="expanded = !expanded" x-show="!expanded" x-text="'Read more'"></button>
        @endif
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
<div class="mb-24">
    <h2 class="atd-section-title mb-16">Leave a Review</h2>
    @auth
    @if ($hasReviewed)
    <div class="atd-already-reviewed">
        <i class="fa-solid fa-circle-check"></i> You've already reviewed this attraction — use the edit icon on your review above to make changes.
    </div>
    @else
    <div class="atd-card" style="max-width:560px;">
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
        background: var(--bg); margin-bottom: 20px;
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

    .atd-caption-name { font-size: 26px; font-weight: 800; color: var(--dark); line-height: 1.2; margin: 0 0 8px; }
    .atd-caption-meta { display: flex; align-items: center; gap: 8px; margin-bottom: 14px; }
    .atd-caption-location { color: var(--muted); font-size: 13.5px; font-weight: 600; display: flex; align-items: center; gap: 6px; }
    .atd-caption-location i { font-size: 11px; }
    .atd-caption-dot { color: var(--border); }
    .atd-stars { color: #F5A623; font-size: 14px; letter-spacing: 1.5px; }
    .atd-stars i.fa-regular { color: var(--border); }
    .atd-rating-num { color: var(--dark); font-size: 13.5px; font-weight: 700; }
    .atd-rating-count { color: var(--muted); font-size: 12.5px; }
    .atd-caption-text { font-size: 17px; font-weight: 700; line-height: 1.6; color: var(--dark); margin: 0; max-width: 900px; }

    .atd-card {
        background: var(--bg-white); border: 1.5px solid var(--border); border-radius: 18px; padding: 22px 24px;
    }
    .atd-section-title { font-size: 18px; font-weight: 800; color: var(--dark); margin: 0 0 10px; }

    .atd-reviews-grid {
        display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px;
    }
    .atd-reviews-grid .atd-empty { grid-column: 1 / -1; }

    .atd-review-card {
        background: var(--bg-white); border: 1.5px solid var(--border); border-radius: 16px;
        padding: 18px 20px; display: flex; flex-direction: column;
    }
    .atd-review-head { display: flex; align-items: center; gap: 6px; margin-bottom: 14px; }
    .atd-review-stars { color: #F5A623; font-size: 14px; letter-spacing: 1.5px; flex-shrink: 0; }
    .atd-review-stars i.fa-regular { color: var(--border); }
    .atd-review-rating-num { font-size: 13px; font-weight: 700; color: var(--dark); margin-left: 2px; }
    .atd-review-head-right { display: flex; align-items: center; gap: 10px; margin-left: auto; flex-shrink: 0; }

    .atd-review-who { display: flex; align-items: center; gap: 10px; min-width: 0; margin-bottom: 12px; }
    .atd-review-avatar {
        width: 40px; height: 40px; border-radius: 50%; flex-shrink: 0;
        background: var(--primary); color: #fff; font-size: 14px; font-weight: 700;
        display: flex; align-items: center; justify-content: center;
    }
    .atd-review-avatar-img { object-fit: cover; }
    .atd-review-name { font-size: 14px; font-weight: 700; color: var(--dark); }
    .atd-review-date { font-size: 12px; color: var(--muted); margin-top: 1px; }
    .atd-review-body { font-size: 14px; color: var(--dark); line-height: 1.65; margin: 0; }
    .atd-review-body-clamped {
        display: -webkit-box; -webkit-line-clamp: 4; -webkit-box-orient: vertical; overflow: hidden;
    }
    .atd-review-more {
        background: none; border: none; padding: 0; margin-top: 4px; align-self: flex-start;
        font-size: 13px; font-weight: 700; color: var(--muted); text-decoration: underline; cursor: pointer;
    }
    .atd-review-more:hover { color: var(--primary); }
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

    .atd-review-edit-btn {
        display: inline-flex; align-items: center; gap: 5px; background: none; border: none;
        color: var(--muted); font-size: 12px; font-weight: 700; cursor: pointer; padding: 0;
    }
    .atd-review-edit-btn:hover { color: var(--primary); }
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
