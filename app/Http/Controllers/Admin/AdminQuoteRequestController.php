<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\QuoteRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class AdminQuoteRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = QuoteRequest::orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->integer('customer_id'));
        }

        if ($request->filled('customer_email')) {
            $query->where('email', $request->customer_email);
        }

        if ($request->filled('q')) {
            $q = $request->q;
            $query->where(function ($sub) use ($q) {
                $sub->where('ref_number', 'like', "%{$q}%")
                    ->orWhere('full_name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%");
            });
        }

        $perPage   = min((int) $request->input('per_page', 25), 100);
        $paginated = $query->paginate($perPage);

        return response()->json([
            'data'    => $paginated->map(fn ($r) => $this->formatList($r))->values(),
            'meta'    => [
                'current_page' => $paginated->currentPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
                'last_page'    => $paginated->lastPage(),
            ],
            'message' => 'success',
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $quote = QuoteRequest::findOrFail($id);

        return response()->json([
            'data'    => $this->formatDetail($quote),
            'message' => 'success',
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'status' => ['required', Rule::in(['new', 'reviewed', 'quoted', 'closed'])],
        ]);

        $quote = QuoteRequest::findOrFail($id);
        $quote->update(['status' => $request->status]);

        return response()->json([
            'data'    => $this->formatDetail($quote->fresh()),
            'message' => 'Quote request updated.',
        ]);
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'status' => ['required', Rule::in(['new', 'reviewed', 'quoted', 'closed'])],
        ]);

        $quote = QuoteRequest::findOrFail($id);
        $quote->update(['status' => $request->status]);

        return response()->json([
            'data'    => $this->formatDetail($quote->fresh()),
            'message' => 'Status updated successfully.',
            'meta'    => (object) [],
        ]);
    }

    private function formatList(QuoteRequest $r): array
    {
        return [
            'id'                      => $r->id,
            'ref_number'              => $r->ref_number,
            'full_name'               => $r->full_name,
            'company_name'            => $r->company_name,
            'email'                   => $r->email,
            'tyre_category'           => $r->tyre_category,
            'country'                 => $r->country,
            'quantity'                => $r->quantity,
            'status'                  => $r->status,
            'created_at'              => $r->created_at?->toIso8601String(),
            'attachment_url'          => $r->attachment_path ? url(Storage::url($r->attachment_path)) : null,
            'attachment_original_name' => $r->attachment_original_name,
            'attachment_size'         => $r->attachment_size,
            'attachment_mime'         => $r->attachment_mime,
        ];
    }

    private function formatDetail(QuoteRequest $r): array
    {
        return [
            'id'                      => $r->id,
            'ref_number'              => $r->ref_number,
            'full_name'               => $r->full_name,
            'company_name'            => $r->company_name,
            'email'                   => $r->email,
            'phone'                   => $r->phone,
            'tyre_category'           => $r->tyre_category,
            'country'                 => $r->country,
            'quantity'                => $r->quantity,
            'delivery_location'       => $r->delivery_location,
            'notes'                   => $r->notes,
            'status'                  => $r->status,
            'admin_notes'             => $r->admin_notes,
            'created_at'              => $r->created_at?->toIso8601String(),
            'updated_at'              => $r->updated_at?->toIso8601String(),
            'attachment_url'          => $r->attachment_path ? url(Storage::url($r->attachment_path)) : null,
            'attachment_original_name' => $r->attachment_original_name,
            'attachment_size'         => $r->attachment_size,
            'attachment_mime'         => $r->attachment_mime,
        ];
    }
}
