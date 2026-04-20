<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\WixCustomerImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerImportController extends Controller
{
    // -------------------------------------------------------------------------
    // POST /api/v1/admin/customers/import — super_admin only
    // -------------------------------------------------------------------------
    public function import(Request $request, WixCustomerImportService $service): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ]);

        set_time_limit(300);

        $path = $request->file('file')->getRealPath();

        try {
            $result = $service->import($path, sendEmails: true);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => [
                'imported'          => $result['imported'],
                'skipped_no_email'  => $result['skipped_no_email'],
                'skipped_duplicate' => $result['skipped_duplicate'],
                'b2b'               => $result['b2b'],
                'b2c'               => $result['b2c'],
                'errors'            => $result['errors'],
            ],
            'message' => "{$result['imported']} customers imported successfully.",
        ]);
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/admin/customers — super_admin, admin
    // -------------------------------------------------------------------------
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'customer_type'      => ['nullable', 'in:b2b,b2c'],
            'imported_from_wix'  => ['nullable', 'boolean'],
            'search'             => ['nullable', 'string', 'max:100'],
            'per_page'           => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Customer::query()->orderByDesc('created_at');

        if ($request->filled('customer_type')) {
            $query->where('customer_type', $request->customer_type);
        }

        if ($request->has('imported_from_wix')) {
            $query->where('imported_from_wix', filter_var($request->imported_from_wix, FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('search')) {
            $term = '%' . $request->search . '%';
            $query->where(function ($q) use ($term) {
                $q->where('first_name', 'like', $term)
                  ->orWhere('last_name', 'like', $term)
                  ->orWhere('email', 'like', $term)
                  ->orWhere('company_name', 'like', $term);
            });
        }

        $perPage    = $request->integer('per_page', 50);
        $paginated  = $query->paginate($perPage);

        return response()->json([
            'data' => $paginated->map(fn ($c) => $this->formatCustomer($c))->values(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
                'last_page'    => $paginated->lastPage(),
            ],
            'message' => 'success',
        ]);
    }

    // -------------------------------------------------------------------------

    private function formatCustomer(Customer $c): array
    {
        return [
            'id'               => $c->id,
            'customer_type'    => $c->customer_type,
            'first_name'       => $c->first_name,
            'last_name'        => $c->last_name,
            'email'            => $c->email,
            'phone'            => $c->phone,
            'country'          => $c->country,
            'company_name'     => $c->company_name,
            'vat_number'       => $c->vat_number,
            'vat_verified'     => (bool) $c->vat_verified,
            'is_active'        => (bool) $c->is_active,
            'email_verified'   => (bool) $c->email_verified_at,
            'imported_from_wix' => (bool) $c->imported_from_wix,
            'created_at'       => $c->created_at?->toIso8601String(),
        ];
    }
}
