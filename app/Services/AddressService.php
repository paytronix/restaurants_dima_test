<?php

namespace App\Services;

use App\Models\CustomerAddress;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AddressService
{
    public function listAddresses(User $user): Collection
    {
        return $user->addresses()->orderByDesc('is_default')->orderBy('id')->get();
    }

    public function createAddress(User $user, array $data): CustomerAddress
    {
        return DB::transaction(function () use ($user, $data) {
            $isDefault = $data['is_default'] ?? false;

            if ($isDefault) {
                $user->addresses()->update(['is_default' => false]);
            } else {
                $hasAddresses = $user->addresses()->exists();
                if (! $hasAddresses) {
                    $isDefault = true;
                }
            }

            $address = CustomerAddress::create([
                'user_id' => $user->id,
                'label' => $data['label'],
                'country' => $data['country'],
                'city' => $data['city'],
                'postal_code' => $data['postal_code'],
                'street_line1' => $data['street_line1'],
                'street_line2' => $data['street_line2'] ?? null,
                'is_default' => $isDefault,
            ]);

            return $address;
        });
    }

    public function updateAddress(int $id, User $user, array $data): CustomerAddress
    {
        $address = CustomerAddress::find($id);

        if ($address === null) {
            throw new NotFoundHttpException('Address not found.');
        }

        if ($address->user_id !== $user->id) {
            throw new AccessDeniedHttpException('You do not have permission to update this address.');
        }

        return DB::transaction(function () use ($address, $user, $data) {
            if (isset($data['is_default']) && $data['is_default'] === true) {
                $user->addresses()
                    ->where('id', '!=', $address->id)
                    ->update(['is_default' => false]);
            }

            $allowedFields = [
                'label',
                'country',
                'city',
                'postal_code',
                'street_line1',
                'street_line2',
                'is_default',
            ];

            $filteredData = array_intersect_key($data, array_flip($allowedFields));

            $address->update($filteredData);

            return $address->fresh();
        });
    }

    public function deleteAddress(int $id, User $user): void
    {
        $address = CustomerAddress::find($id);

        if ($address === null) {
            throw new NotFoundHttpException('Address not found.');
        }

        if ($address->user_id !== $user->id) {
            throw new AccessDeniedHttpException('You do not have permission to delete this address.');
        }

        DB::transaction(function () use ($address, $user) {
            $wasDefault = $address->is_default;

            $address->delete();

            if ($wasDefault) {
                $nextAddress = $user->addresses()->orderBy('id')->first();
                if ($nextAddress !== null) {
                    $nextAddress->update(['is_default' => true]);
                }
            }
        });
    }

    public function setDefault(int $id, User $user): CustomerAddress
    {
        $address = CustomerAddress::find($id);

        if ($address === null) {
            throw new NotFoundHttpException('Address not found.');
        }

        if ($address->user_id !== $user->id) {
            throw new AccessDeniedHttpException('You do not have permission to modify this address.');
        }

        return DB::transaction(function () use ($address, $user) {
            $user->addresses()
                ->where('id', '!=', $address->id)
                ->update(['is_default' => false]);

            $address->update(['is_default' => true]);

            return $address->fresh();
        });
    }
}
