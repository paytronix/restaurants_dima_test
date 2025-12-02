<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Address\StoreAddressRequest;
use App\Http\Requests\Address\UpdateAddressRequest;
use App\Http\Resources\CustomerAddressResource;
use App\Services\AddressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AddressController extends Controller
{
    public function __construct(
        private AddressService $addressService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $addresses = $this->addressService->listAddresses($request->user());

        return response()->json([
            'data' => CustomerAddressResource::collection($addresses),
            'meta' => [
                'total' => $addresses->count(),
            ],
        ]);
    }

    public function store(StoreAddressRequest $request): JsonResponse
    {
        $address = $this->addressService->createAddress($request->user(), $request->validated());

        return response()->json([
            'data' => new CustomerAddressResource($address),
            'meta' => [],
        ], 201);
    }

    public function update(UpdateAddressRequest $request, int $id): JsonResponse
    {
        $address = $this->addressService->updateAddress($id, $request->user(), $request->validated());

        return response()->json([
            'data' => new CustomerAddressResource($address),
            'meta' => [],
        ]);
    }

    public function destroy(Request $request, int $id): Response
    {
        $this->addressService->deleteAddress($id, $request->user());

        return response()->noContent();
    }

    public function makeDefault(Request $request, int $id): JsonResponse
    {
        $address = $this->addressService->setDefault($id, $request->user());

        return response()->json([
            'data' => new CustomerAddressResource($address),
            'meta' => [],
        ]);
    }
}
