<div>
    <div style="display:flex;align-items:center;justify-content:space-between;" class="mb-16">
        <div>
            <h1>Destinations</h1>
            <p class="text-muted">Explore places our travelers have visited.</p>
        </div>
    </div>

    {{-- Filters --}}
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;max-width:560px;" class="mb-24">
        <div>
            <input type="text" wire:model.live.debounce.300ms="search"
                   class="form-control" placeholder="Search destinations...">
        </div>
        <div>
            <select wire:model.live="country" class="form-control">
                <option value="">All countries</option>
                @foreach ($this->countries as $c)
                <option value="{{ $c }}">{{ $c }}</option>
                @endforeach
            </select>
        </div>
    </div>

    {{-- Card grid --}}
    @if ($this->destinations->isEmpty())
    <div style="text-align:center;padding:40px 24px;">
        <div style="font-size:40px;margin-bottom:12px;">🧭</div>
        <h3 style="font-weight:700;margin-bottom:8px;">No destinations found</h3>
        <p class="text-muted" style="margin-bottom:20px;">Try a different search or country filter.</p>
    </div>
    @else
    <div class="attraction-grid">
        @foreach ($this->destinations as $destination)
        <a href="{{ route('destinations.show', $destination) }}" style="text-decoration:none;color:inherit;">
            <div class="card">
                <div style="height:160px;background:var(--border-light);border-radius:10px 10px 0 0;overflow:hidden;display:flex;align-items:center;justify-content:center;font-size:40px;">
                    @if ($destination->image)
                    <img src="{{ asset('storage/' . $destination->image) }}"
                         style="width:100%;height:100%;object-fit:cover;" alt="{{ $destination->name }}">
                    @else
                    🧭
                    @endif
                </div>
                <div class="card-body">
                    <div style="font-size:14px;font-weight:600;">{{ $destination->name }}</div>
                    @if ($destination->country)
                    <span class="badge badge-primary mt-4">{{ $destination->country }}</span>
                    @endif
                    <div class="mt-4 text-muted" style="font-size:12px;">
                        {{ $destination->attractions_count }} {{ Str::plural('attraction', $destination->attractions_count) }}
                    </div>
                    <div class="btn btn-primary btn-sm mt-8" style="font-size:11px;">Explore</div>
                </div>
            </div>
        </a>
        @endforeach
    </div>
    @endif
</div>
