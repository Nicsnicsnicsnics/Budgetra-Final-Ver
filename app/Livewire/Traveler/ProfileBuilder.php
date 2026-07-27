<?php

namespace App\Livewire\Traveler;

use Livewire\Component;
use App\Models\UserProfile;

class ProfileBuilder extends Component
{
    public int    $step       = 1;
    public string $returnTo   = '';
    public string $homeCity   = '';
    public string $dailyBudgetDisplay = '';
    public float  $dailyBudget = 0;
    public string $travelStyle = '';
    public array  $memberEmailInputs = [''];
    public array  $groupMemberEmails = [];

    public array  $selectedInterests    = [];
    public array  $selectedSubInterests = [];
    public string $expandedInterest     = '';
    public string $preferredTransportation = '';
    public string $preferredAccommodation  = '';

    public const INTERESTS = [
        'Beach'           => ['Surfing', 'Snorkeling', 'Island Hopping', 'Swimming', 'Diving', 'Beach Camping', 'Kayaking', 'Sunset Watching'],
        'Nature'          => ['Mountains', 'Waterfalls', 'Wildlife', 'Forests', 'Camping', 'Bird Watching', 'National Parks', 'Caves'],
        'Food Trip'       => ['Street Food', 'Fine Dining', 'Local Delicacies', 'Cafes', 'Food Tours', 'Cooking Classes', 'Farmers Markets', 'Food Festivals'],
        'Adventure'       => ['Hiking', 'Diving', 'Ziplining', 'Canyoneering', 'Rock Climbing', 'Whitewater Rafting', 'Paragliding', 'ATV Rides'],
        'Historical Sites'=> ['Churches', 'Ruins', 'Forts', 'Heritage Towns', 'Monuments', 'Ancestral Houses', 'War Memorials', 'Archaeological Sites'],
        'Shopping'        => ['Malls', 'Night Markets', 'Pasalubong', 'Thrift Shops', 'Local Crafts', 'Souvenir Shops', 'Boutiques', 'Flea Markets'],
        'Museums'         => ['Art', 'History', 'Science', 'Culture', 'Natural History', 'Interactive Exhibits', 'Local Heritage', 'Photography'],
        'Nightlife'       => ['Bars', 'Clubs', 'Live Music', 'Night Markets', 'Rooftop Bars', 'Karaoke', 'Night Tours', 'Cultural Shows'],
        'Relaxation'      => ['Spa', 'Beach Resort', 'Hot Springs', 'Wellness', 'Yoga Retreats', 'Massage', 'Meditation', 'Quiet Cafes'],
    ];

    public const ICONS = [
        'Beach'           => 'fa-umbrella-beach',
        'Nature'          => 'fa-leaf',
        'Food Trip'       => 'fa-utensils',
        'Adventure'       => 'fa-person-hiking',
        'Historical Sites'=> 'fa-landmark',
        'Shopping'        => 'fa-bag-shopping',
        'Museums'         => 'fa-building-columns',
        'Nightlife'       => 'fa-moon',
        'Relaxation'      => 'fa-spa',
    ];

    public const IMAGES = [
        'Beach'           => 'beach.jpg',
        'Nature'          => 'nature.jpg',
        'Food Trip'       => 'foodtrip.jpg',
        'Adventure'       => 'adventure.jpg',
        'Historical Sites'=> 'historical.jpg',
        'Shopping'        => 'shopping.jpg',
        'Museums'         => 'museums.jpg',
        'Nightlife'       => 'nightlife.jpg',
        'Relaxation'      => 'relaxation.jpg',
    ];

    public const SUGGESTED_CITIES = ['Manila', 'Cebu City', 'Davao City'];

    public const LOCAL_DESTINATIONS = [
        'MNL' => 'Manila',
        'CEB' => 'Cebu City',
        'DVO' => 'Davao City',
        'KLO' => 'Boracay',
        'PPS' => 'Puerto Princesa',
        'TAG' => 'Tagbilaran',
        'ILO' => 'Iloilo City',
        'BCD' => 'Bacolod City',
        'GES' => 'General Santos',
        'ZAM' => 'Zamboanga City',
        'TAC' => 'Tacloban City',
        'IAO' => 'Siargao',
    ];

    public const TRAVEL_STYLES = [
        'Solo'  => ['icon' => 'fa-user', 'desc' => 'Travel at your own pace with a budget built for one.', 'image' => 'solo.jpg'],
        'Group' => ['icon' => 'fa-user-group',      'desc' => 'Divide expenses and explore together, no one overpays.', 'image' => 'group.jpg'],
    ];

    public const TRANSPORTATION_OPTIONS = [
        'Flight' => 'fa-plane',
    ];

    public const TRANSPORTATION_IMAGES = [
        'Flight' => 'flights.jpg',
    ];

    public const ACCOMMODATION_OPTIONS = [
        'Hotel'     => 'fa-hotel',
        'Apartment' => 'fa-building',
        'Inn'       => 'fa-house-chimney',
        'Resort'    => 'fa-umbrella-beach',
    ];

    public const ACCOMMODATION_IMAGES = [
        'Hotel'     => 'hotel.jpg',
        'Apartment' => 'apartment.png',
        'Inn'       => 'inn.jpg',
        'Resort'    => 'resort.jpg',
    ];

    public function mount(): void
    {
        $profile = auth()->user()->userProfile;
        if ($profile) {
            $city = $profile->home_city ?? '';
            $this->homeCity = is_numeric(preg_replace('/[\s,₱]/', '', $city)) ? '' : $city;
            $this->dailyBudget         = $profile->daily_budget ?? 0;
            $this->dailyBudgetDisplay  = $this->dailyBudget ? number_format($this->dailyBudget) : '';
            $this->travelStyle         = $profile->travel_style  ?? '';
            $this->groupMemberEmails   = $profile->group_member_emails ?? [];
            $this->selectedInterests   = $profile->interests     ?? [];
            $this->selectedSubInterests= $profile->sub_interests ?? [];
            $this->preferredTransportation = $profile->preferred_transportation ?? '';
            $this->preferredAccommodation  = $profile->preferred_accommodation  ?? '';
        }

        // Allow deep-linking straight to a specific step (e.g. "Edit" from
        // the AI planner jumps right to the interests step) instead of
        // always starting the whole wizard over from step 1.
        $requestedStep = (int) request()->query('step', 0);
        if ($requestedStep >= 1 && $requestedStep <= 6) {
            $this->step = $requestedStep;
        }

        // Whitelist which "come back to" routes are allowed, so this can't
        // be abused as an open redirect via the query string.
        $requestedReturn = (string) request()->query('return', '');
        if (in_array($requestedReturn, ['trips.plan.ai'], true)) {
            $this->returnTo = $requestedReturn;
        }
    }

    public function nextStep(): void
    {
        if ($this->step === 1) {
            $city = trim($this->homeCity);
            if (empty($city)) {
                $this->addError('homeCity', 'Please enter your home city.');
                return;
            }
            if (is_numeric(preg_replace('/[\s,₱]/', '', $city))) {
                $this->addError('homeCity', 'Please enter a city name (e.g. "Manila"), not a number.');
                return;
            }
        }
        $this->resetErrorBag();
        $this->step++;
    }

    public function prevStep(): void
    {
        if ($this->step > 1) $this->step--;
    }

    public function selectTravelStyle(string $style): void
    {
        $this->travelStyle = ($this->travelStyle === $style) ? '' : $style;
    }

    public function selectTransportation(string $option): void
    {
        $this->preferredTransportation = ($this->preferredTransportation === $option) ? '' : $option;
    }

    public function selectAccommodation(string $option): void
    {
        $this->preferredAccommodation = ($this->preferredAccommodation === $option) ? '' : $option;
    }

    public function addGroupMember(int $index): void
    {
        $email = trim($this->memberEmailInputs[$index] ?? '');

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->addError("memberEmailInputs.$index", 'Please enter a valid email address.');
            return;
        }

        if (in_array($email, $this->groupMemberEmails)) {
            $this->addError("memberEmailInputs.$index", 'This member has already been added.');
            return;
        }

        $this->resetErrorBag("memberEmailInputs.$index");
        $this->groupMemberEmails[] = $email;
        $this->memberEmailInputs[$index] = '';
    }

    public function addMemberRow(): void
    {
        $this->memberEmailInputs[] = '';
    }

    public function removeMemberRow(int $index): void
    {
        unset($this->memberEmailInputs[$index]);
        $this->memberEmailInputs = array_values($this->memberEmailInputs);

        if (empty($this->memberEmailInputs)) {
            $this->memberEmailInputs = [''];
        }
    }

    public function removeGroupMember(string $email): void
    {
        $this->groupMemberEmails = array_values(array_filter(
            $this->groupMemberEmails, fn ($e) => $e !== $email
        ));
    }

    public function toggleInterest(string $interest): void
    {
        if (in_array($interest, $this->selectedInterests)) {
            $this->selectedInterests = array_values(array_filter(
                $this->selectedInterests, fn($i) => $i !== $interest
            ));
            // remove associated sub-interests
            $subs = self::INTERESTS[$interest] ?? [];
            $this->selectedSubInterests = array_values(array_filter(
                $this->selectedSubInterests, fn($s) => !in_array($s, $subs)
            ));
            if ($this->expandedInterest === $interest) {
                $this->expandedInterest = '';
            }
        } else {
            $this->selectedInterests[] = $interest;
            $this->expandedInterest = $interest;
        }
    }

    public function toggleSubInterest(string $sub): void
    {
        if (in_array($sub, $this->selectedSubInterests)) {
            $this->selectedSubInterests = array_values(array_filter(
                $this->selectedSubInterests, fn($s) => $s !== $sub
            ));
        } else {
            $this->selectedSubInterests[] = $sub;
        }
    }

    private function persistProfile(): void
    {
        UserProfile::updateOrCreate(
            ['user_id' => auth()->id()],
            [
                'home_city'      => $this->homeCity,
                'daily_budget'   => $this->dailyBudget,
                'travel_style'         => $this->travelStyle,
                'group_member_emails'  => $this->travelStyle === 'Group' ? $this->groupMemberEmails : [],
                'interests'      => $this->selectedInterests,
                'sub_interests'  => $this->selectedSubInterests,
                'preferred_transportation' => $this->preferredTransportation,
                'preferred_accommodation'  => $this->preferredAccommodation,
            ]
        );
    }

    public function confirmProfile(): void
    {
        $this->persistProfile();
        $this->redirect(route($this->returnTo ?: 'trips.plan'), navigate: true);
    }

    // Quick-edit path: arrived here via "Edit" from the AI planner to change
    // just the interests. Saves immediately and returns, instead of forcing
    // the traveler through the remaining transportation/accommodation/group
    // steps of the full onboarding wizard again.
    public function saveInterestsAndReturn(): void
    {
        $this->persistProfile();
        $this->redirect(route($this->returnTo ?: 'dashboard'), navigate: true);
    }

    public function render()
    {
        return view('livewire.traveler.profile-builder', [
            'interests'    => self::INTERESTS,
            'icons'        => self::ICONS,
            'images'       => self::IMAGES,
            'suggested'    => self::SUGGESTED_CITIES,
            'localDestinations' => self::LOCAL_DESTINATIONS,
            'travelStyles' => self::TRAVEL_STYLES,
            'transportationOptions' => self::TRANSPORTATION_OPTIONS,
            'transportationImages'  => self::TRANSPORTATION_IMAGES,
            'accommodationOptions'  => self::ACCOMMODATION_OPTIONS,
            'accommodationImages'   => self::ACCOMMODATION_IMAGES,
        ])->layout('layouts.app');
    }
}
