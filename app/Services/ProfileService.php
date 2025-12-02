<?php

namespace App\Services;

use App\Models\CustomerProfile;
use App\Models\User;

class ProfileService
{
    public function getProfile(User $user): CustomerProfile
    {
        $profile = $user->profile;

        if ($profile === null) {
            $profile = CustomerProfile::create([
                'user_id' => $user->id,
            ]);
        }

        return $profile;
    }

    public function updateProfile(User $user, array $data): CustomerProfile
    {
        $profile = $this->getProfile($user);

        $allowedFields = [
            'first_name',
            'last_name',
            'phone',
            'marketing_opt_in',
            'birth_date',
        ];

        $filteredData = array_intersect_key($data, array_flip($allowedFields));

        $profile->update($filteredData);

        if (isset($data['first_name']) || isset($data['last_name'])) {
            $firstName = $data['first_name'] ?? $profile->first_name ?? '';
            $lastName = $data['last_name'] ?? $profile->last_name ?? '';
            $user->update(['name' => trim($firstName.' '.$lastName)]);
        }

        return $profile->fresh();
    }
}
