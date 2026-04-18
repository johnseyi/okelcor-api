<?php

namespace App\Http\Controllers;

use App\Models\CustomerAddress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerAddressController extends Controller
{
    // GET /api/v1/auth/addresses
    public function index(Request $request): JsonResponse
    {
        $addresses = $request->user()
            ->addresses()
            ->orderByDesc('is_default')
            ->orderBy('created_at')
            ->get();

        return response()->json([
            'data'    => $addresses,
            'message' => 'success',
        ]);
    }

    // POST /api/v1/auth/addresses
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'full_name'      => ['required', 'string', 'max:200'],
            'address_line_1' => ['required', 'string', 'max:300'],
            'address_line_2' => ['nullable', 'string', 'max:300'],
            'city'           => ['required', 'string', 'max:100'],
            'postcode'       => ['required', 'string', 'max:20'],
            'country'        => ['required', 'string', 'max:100'],
            'phone'          => ['nullable', 'string', 'max:50'],
            'is_default'     => ['boolean'],
        ]);

        $customer = $request->user();

        if (! empty($data['is_default'])) {
            $customer->addresses()->update(['is_default' => false]);
        }

        $address = $customer->addresses()->create($data);

        return response()->json([
            'data'    => $address,
            'message' => 'Address added successfully.',
        ], 201);
    }

    // PUT /api/v1/auth/addresses/{id}
    public function update(Request $request, int $id): JsonResponse
    {
        $address = $request->user()->addresses()->findOrFail($id);

        $data = $request->validate([
            'full_name'      => ['sometimes', 'string', 'max:200'],
            'address_line_1' => ['sometimes', 'string', 'max:300'],
            'address_line_2' => ['nullable', 'string', 'max:300'],
            'city'           => ['sometimes', 'string', 'max:100'],
            'postcode'       => ['sometimes', 'string', 'max:20'],
            'country'        => ['sometimes', 'string', 'max:100'],
            'phone'          => ['nullable', 'string', 'max:50'],
            'is_default'     => ['boolean'],
        ]);

        if (! empty($data['is_default'])) {
            $request->user()->addresses()
                ->where('id', '!=', $id)
                ->update(['is_default' => false]);
        }

        $address->update($data);

        return response()->json([
            'data'    => $address->fresh(),
            'message' => 'Address updated successfully.',
        ]);
    }

    // DELETE /api/v1/auth/addresses/{id}
    public function destroy(Request $request, int $id): JsonResponse
    {
        $address = $request->user()->addresses()->findOrFail($id);
        $address->delete();

        return response()->json(['message' => 'Address deleted successfully.']);
    }
}
