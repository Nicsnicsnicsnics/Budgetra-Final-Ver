# Share Trip — Design

## Purpose

Let a traveler share a saved trip's manual selections (flight, accommodation,
food & dining, attraction — per leg, for multi-city trips) with another
Budgetra user, either via a link or a short code. The recipient gets their
own independent copy of those selections as a new trip in their account.
AI-suggested itinerary activities and the emergency fund are never included —
only what the traveler actually picked.

## Why this needs new columns (not just reusing existing data)

`Trip.summary_data` (already stored per trip) mixes the traveler's own
selection cost with AI-suggested activity cost into one number per category
(e.g. `summary_data.food.cost` = venue pick + AI-suggested food activities
combined). There is no existing field that isolates "traveler's own pick"
from "AI-suggested." To share only the traveler's picks, the four (or eight,
for multi-city) raw selection objects need to be captured and stored
separately, at the point they're already known: when the wizard's
`saveItinerary()` runs.

**Consequence:** trips saved before this feature ships have no selection
snapshot. Their Saved Trips card shows the Share button disabled with a
tooltip explaining why, instead of hiding it silently.

## Data model changes

Migration adds to `trips`:

| Column | Type | Notes |
|---|---|---|
| `flight_selection` | json, nullable | Leg 1 — same shape as `$selectedFlight` in the wizard |
| `hotel_selection` | json, nullable | Leg 1 |
| `venue_selection` | json, nullable | Leg 1 |
| `attraction_selection` | json, nullable | Leg 1 |
| `leg2_flight_selection` | json, nullable | Leg 2 — multi-city only |
| `leg2_hotel_selection` | json, nullable | Leg 2 |
| `leg2_venue_selection` | json, nullable | Leg 2 |
| `leg2_attraction_selection` | json, nullable | Leg 2 |
| `share_code` | string, nullable, unique | Generated lazily on first Share click; never regenerated or revoked |

`Trip` model: add these to `$fillable`, cast the eight selection columns as
`array`.

## Writing the snapshot

In `TripPlannerWizard::saveItinerary()`, alongside the existing `Trip::create([...])`
call, also persist:

```php
'flight_selection'          => $this->selectedFlight ?: null,
'hotel_selection'           => $this->selectedHotel ?: null,
'venue_selection'           => $this->selectedVenue ?: null,
'attraction_selection'      => $this->selectedAttraction ?: null,
'leg2_flight_selection'     => $isMultiCitySaved ? ($this->selectedMcFlight ?: null) : null,
'leg2_hotel_selection'      => $isMultiCitySaved ? ($this->selectedMcHotel ?: null) : null,
'leg2_venue_selection'      => $isMultiCitySaved ? ($this->selectedMcVenue ?: null) : null,
'leg2_attraction_selection' => $isMultiCitySaved ? ($this->selectedMcAttraction ?: null) : null,
```

These are the exact same arrays already used elsewhere in `saveItinerary()`
to build `summary_data` — no new data collection, just also storing the raw
form.

## Sharing (owner side)

**UI**: on the Saved Trips card, directly below the existing kebab (⋮) menu
button, add a small circular "Share" icon button (share-nodes icon). Disabled
(with a tooltip) when the trip has no selection snapshot at all (all four
leg-1 columns null) — this is the pre-feature-trip case.

**Click behavior**: opens a modal ("Share Trip"):
- If the trip has no `share_code` yet, generate one now (see below) and save it.
- Two rows: "Share via Link" showing `{APP_URL}/trips/import/{code}` with a
  Copy button, and "Share via Code" showing the bare code with its own Copy
  button.
- No revoke/regenerate control (per approved design) — once generated, a
  trip's code is permanent.

**Code generation**: `Str::upper(Str::random(8))` (alphanumeric, unambiguous
— exclude `0/O/1/I` via a custom alphabet), retried on the rare unique
collision. Stored in `share_code`.

## Redeeming (recipient side)

### Entry point 1 — direct link

Route: `GET /trips/import/{code}`.

- If not authenticated: redirect to login, preserving `?redirect=/trips/import/{code}`
  (existing auth redirect pattern) so the import resumes right after login/register.
- If authenticated: look up the trip by `share_code`. Not found → flash error,
  redirect to Saved Trips. Found → run the import (see below), flash a
  success message ("Trip imported!"), redirect to Saved Trips.
- A user importing their own shared trip is allowed (creates a duplicate in
  their own account) — no special-casing needed.

### Entry point 2 — code entry inside Manual Planning

Today, clicking "Manual Planning" on the mode-select screen jumps straight to
Trip Details (step 1). This adds one screen in between, gated by a new
component property `$manualCodeGateDone = false`:

- `selectPlanningMode('manual')` sets `planningMode = 'manual'` and
  `step = 1` as it does today, but Trip Details' step-1 block only renders
  when `$manualCodeGateDone` is true.
- While false, step 1 instead renders: "Have a trip code? Enter it here" —
  a text input + "Import" button, and a "Skip" link/button.
- "Import" calls the same import logic as the link flow, using the typed
  code. On success: redirect to Saved Trips (the point was to grab the
  trip, not keep planning manually). On invalid code: inline error, stays
  on the same screen.
- "Skip" sets `$manualCodeGateDone = true` and re-renders — Trip Details
  shows exactly as it does today. No other existing step is touched.
- This screen only appears for Manual Planning, not AI Powered Planning.

## Import logic (shared by both entry points)

New method, e.g. `TripImportService::import(Trip $sharedTrip, User $recipient): Trip`:

1. Build `summary_data` fresh from the (up to 8) stored selection snapshots
   only — reuse the same detail-string formatting already in
   `saveItinerary()` (airline/number, hotel name + nights, venue name +
   cuisine, attraction name), but cost per category = selection cost only
   (no AI figure exists to add, since none is copied).
2. `total_cost` = sum of the four (or eight) selection costs. No emergency
   fund line (emergency fund is never shared).
3. Create a new `Trip` for `$recipient` with: `destination`, `trip_name`,
   `origin`, `origin_code`, `destination_code`, `start_date`, `end_date`,
   `is_multi_city`, `leg2_destination(_code)`, `leg2_start_date`,
   `leg2_end_date`, `cover_image`, `budget_limit` (= total_cost, so the
   spend-percentage bar starts sensible), `total_cost`, `summary_data`,
   `travel_type = 'Solo'`, `num_travelers = 1`. Copy the same eight
   selection-snapshot columns forward too, so the recipient could re-share
   it themselves later.
4. No `Itinerary` rows are created — the recipient's trip starts with an
   empty itinerary calendar (they can generate their own AI suggestions or
   add items manually later, same as any freshly-saved trip missing that
   step).
5. Return the new trip; both entry points redirect to Saved Trips afterward.

## Error handling

- Invalid/unknown code (either entry point): clear inline message, no crash.
- Trip with no snapshot data at all being shared (shouldn't reach the
  modal since the button is disabled, but defend in the controller too):
  reject with a flash error.
- Malformed link (`{code}` not matching the expected format): 404, same as
  any other unmatched route.

## Out of scope (explicitly not building)

- Revoking/regenerating share codes.
- Sharing AI-suggested itinerary content or the emergency fund.
- Real-time collaboration / multi-owner trips.
- Any change to trips saved before this feature ships (they simply can't be
  shared).
