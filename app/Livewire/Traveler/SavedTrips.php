<?php
namespace App\Livewire\Traveler;

use App\Models\GroupMember;
use App\Models\Trip;
use App\Models\User;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app', ['active' => 'saved-trips'])]
class SavedTrips extends Component
{
    public ?int $detailTripId   = null;
    public ?int $deleteTripId   = null;
    public string $deleteTripName = '';
    public ?int $editNameTripId  = null;
    public string $editNameValue = '';
    public string $editType      = 'Solo';
    public string $editStatus    = 'upcoming';
    public string $memberEmail   = '';
    public string $memberError   = '';
    public array  $pendingMembers = [];  // [['id'=>..,'name'=>..,'email'=>..]]
    public array  $savedMembers   = [];  // already saved for this trip
    public ?int $shareCodeTripId  = null;
    public string $shareCodeValue = '';

    public function showDetail(int $id): void
    {
        $this->detailTripId = $this->detailTripId === $id ? null : $id;
    }

    public function closeDetail(): void
    {
        $this->detailTripId = null;
    }

    public function confirmDelete(int $id): void
    {
        $trip = Trip::find($id);
        $this->deleteTripId   = $id;
        $this->deleteTripName = $trip->trip_name ?? $trip->destination ?? 'this trip';
    }

    public function openEditName(int $id): void
    {
        $trip = Trip::find($id);
        if (!$trip) return;
        $this->editNameTripId = $id;
        $this->editNameValue = $trip->trip_name ?? $trip->destination ?? '';
        $this->editType      = ucfirst(strtolower($trip->travel_type ?? 'Solo'));
        $today = \Carbon\Carbon::today();
        $computed = $trip->start_date->gt($today) ? 'upcoming' : ($trip->end_date->lt($today) ? 'past' : 'active');
        $this->editStatus     = $trip->getRawOriginal('status') ?? $computed;
        $this->memberEmail    = '';
        $this->memberError    = '';
        $this->pendingMembers = [];
        $this->savedMembers   = GroupMember::where('trip_id', $id)
            ->with('user')
            ->get()
            ->map(fn($m) => ['id' => $m->user_id, 'name' => $m->user->full_name, 'email' => $m->user->email])
            ->toArray();
    }

    public function lookupMember(): void
    {
        $this->memberError = '';
        $email = trim($this->memberEmail);
        if ($email === '') return;

        $user = User::where('email', $email)->first();
        if (!$user) {
            $this->memberError = 'No registered user found with that email.';
            return;
        }
        if ($user->id === auth()->id()) {
            $this->memberError = 'You are already the trip owner.';
            return;
        }
        $alreadyInSaved   = collect($this->savedMembers)->pluck('id')->contains($user->id);
        $alreadyInPending = collect($this->pendingMembers)->pluck('id')->contains($user->id);
        if ($alreadyInSaved || $alreadyInPending) {
            $this->memberError = 'This user is already added.';
            return;
        }
        $this->pendingMembers[] = ['id' => $user->id, 'name' => $user->full_name, 'email' => $user->email];
        $this->memberEmail = '';
    }

    public function removePendingMember(int $index): void
    {
        array_splice($this->pendingMembers, $index, 1);
    }

    public function removeSavedMember(int $userId): void
    {
        if (!$this->editNameTripId) return;
        GroupMember::where('trip_id', $this->editNameTripId)->where('user_id', $userId)->delete();
        $this->savedMembers = array_values(array_filter($this->savedMembers, fn($m) => $m['id'] !== $userId));
    }

    public function saveEditName(): void
    {
        if (!$this->editNameTripId || trim($this->editNameValue) === '') return;
        $trip = Trip::find($this->editNameTripId);
        if ($trip && $trip->user_id === auth()->id()) {
            $trip->update([
                'trip_name'   => trim($this->editNameValue),
                'travel_type' => $this->editType,
                'status'      => $this->editStatus,
            ]);
            $newName = trim($this->editNameValue) ?: $trip->destination;
            $trip->savingsGoals()->update(['goal_name' => $newName]);

            if ($this->editType === 'Group') {
                foreach ($this->pendingMembers as $m) {
                    GroupMember::firstOrCreate(['trip_id' => $trip->id, 'user_id' => $m['id']]);
                }
            }
        }
        $this->editNameTripId  = null;
        $this->editNameValue   = '';
        $this->editType        = 'Solo';
        $this->editStatus      = 'upcoming';
        $this->memberEmail     = '';
        $this->memberError     = '';
        $this->pendingMembers  = [];
        $this->savedMembers    = [];
    }

    public function cancelEditName(): void
    {
        $this->editNameTripId  = null;
        $this->editNameValue   = '';
        $this->editType        = 'Solo';
        $this->editStatus      = 'upcoming';
        $this->memberEmail     = '';
        $this->memberError     = '';
        $this->pendingMembers  = [];
        $this->savedMembers    = [];
    }

    public function cancelDelete(): void
    {
        $this->deleteTripId   = null;
        $this->deleteTripName = '';
    }

    public function toggleShare(int $id): void
    {
        $trip = Trip::find($id);
        if ($trip && $trip->user_id === auth()->id()) {
            $trip->update(['is_shared' => !$trip->is_shared]);
        }
    }

    public function showShareCode(int $id): void
    {
        $trip = Trip::find($id);
        if (!$trip || $trip->user_id !== auth()->id()) return;

        if (!$trip->share_code) {
            $trip->update(['share_code' => Trip::generateUniqueShareCode()]);
        }
        $this->shareCodeTripId = $id;
        $this->shareCodeValue  = $trip->share_code;
    }

    public function closeShareCodeModal(): void
    {
        $this->shareCodeTripId = null;
        $this->shareCodeValue  = '';
    }

    public function deleteTrip(): void
    {
        if (!$this->deleteTripId) return;
        $trip = Trip::find($this->deleteTripId);
        if ($trip && $trip->user_id === auth()->id()) {
            $trip->delete();
        }
        if ($this->detailTripId === $this->deleteTripId) {
            $this->detailTripId = null;
        }
        $this->deleteTripId   = null;
        $this->deleteTripName = '';
    }

    private function fetchTrips()
    {
        return Trip::where('user_id', auth()->id())
            ->withSum('expenses', 'amount')
            ->latest('created_at')
            ->get()
            ->map(function (Trip $trip) {
                $today = Carbon::today();
                $days  = max(1, (int) $trip->start_date->diffInDays($trip->end_date));
                $trip->setAttribute('days', $days);
                $trip->setAttribute('status',
                    $trip->status ??
                    ($trip->start_date->gt($today) ? 'upcoming' :
                    ($trip->end_date->lt($today)   ? 'past'     : 'active')));

                // Real spending from logged Expenses, vs. the planned budget —
                // lets the card show actual money tracking, not just the estimate.
                $actualSpent = (float) ($trip->expenses_sum_amount ?? 0);
                $budget      = (float) ($trip->total_cost ?? $trip->budget_limit ?? 0);
                $trip->setAttribute('actual_spent', $actualSpent);
                $trip->setAttribute('spend_pct', $budget > 0 ? min(100, round($actualSpent / $budget * 100)) : 0);

                return $trip;
            });
    }

    public function render()
    {
        $trips = $this->fetchTrips();
        $detailTrip = $this->detailTripId
            ? $trips->firstWhere('id', $this->detailTripId)
            : null;
        return view('livewire.traveler.saved-trips', compact('trips', 'detailTrip'));
    }
}
