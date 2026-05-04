<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ConvertQuoteToOrderRequest;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderLog;
use App\Models\QuoteRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
        $quote = QuoteRequest::with('order')->findOrFail($id);

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
            'data'    => $this->formatDetail($quote->fresh()->load('order')),
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
            'data'    => $this->formatDetail($quote->fresh()->load('order')),
            'message' => 'Status updated successfully.',
            'meta'    => (object) [],
        ]);
    }

    public function convertToOrder(int $id, ConvertQuoteToOrderRequest $request): JsonResponse
    {
        $quote = QuoteRequest::findOrFail($id);

        // Guard: only convert quotes that have been formally quoted
        if ($quote->status !== 'quoted') {
            return response()->json([
                'message' => 'Only quotes with status "quoted" can be converted to an order.',
            ], 422);
        }

        // Guard: prevent duplicate conversion
        if ($quote->order_id !== null) {
            return response()->json([
                'message' => 'This quote has already been converted to an order.',
                'data'    => ['order_id' => $quote->order_id],
            ], 409);
        }

        $validated     = $request->validated();
        $delivery      = $validated['delivery'] ?? [];
        $items         = $validated['items'];
        $deliveryCost  = (float) ($validated['delivery_cost'] ?? 0);
        $paymentMethod = $validated['payment_method'] ?? 'bank_transfer';

        $order = DB::transaction(function () use ($quote, $delivery, $items, $deliveryCost, $paymentMethod, $validated, $request) {
            $subtotal = 0.0;

            $ref = $this->generateRef();

            $order = Order::create([
                'ref'            => $ref,
                'customer_name'  => $quote->full_name,
                'customer_email' => $quote->email,
                'customer_phone' => $delivery['phone'] ?? $quote->phone,
                'address'        => $delivery['address'] ?? $quote->delivery_address,
                'city'           => $delivery['city'] ?? $quote->delivery_city,
                'postal_code'    => $delivery['postal_code'] ?? $quote->delivery_postal_code,
                'country'        => $delivery['country'] ?? $quote->country,
                'payment_method' => $paymentMethod,
                'subtotal'       => 0,  // updated below after items
                'delivery_cost'  => $deliveryCost,
                'total'          => 0,  // updated below
                'status'         => 'confirmed',
                'payment_status' => 'pending',
                'mode'           => 'manual',
                'vat_number'     => $quote->vat_number,
                'vat_valid'      => $quote->vat_valid,
                'admin_notes'    => $validated['admin_notes']
                    ?? "Converted from quote {$quote->ref_number}.",
            ]);

            foreach ($items as $item) {
                $lineTotal = (float) $item['unit_price'] * (int) $item['quantity'];
                $subtotal += $lineTotal;

                OrderItem::create([
                    'order_id'   => $order->id,
                    'product_id' => null,
                    'sku'        => $item['sku'] ?? null,
                    'brand'      => $item['brand'],
                    'name'       => $item['name'],
                    'size'       => $item['size'],
                    'unit_price' => (float) $item['unit_price'],
                    'quantity'   => (int) $item['quantity'],
                    'line_total' => $lineTotal,
                ]);
            }

            $total = $subtotal + $deliveryCost;
            $order->update(['subtotal' => $subtotal, 'total' => $total]);

            // Link quote to order
            $quote->update(['order_id' => $order->id]);

            // Audit log
            $this->writeConversionLog($request, $order, $quote->ref_number);

            return $order;
        });

        return response()->json([
            'data' => [
                'order_ref'      => $order->ref,
                'quote_ref'      => $quote->ref_number,
                'status'         => $order->status,
                'payment_status' => $order->payment_status,
                'total'          => (float) $order->total,
            ],
            'message' => 'Quote converted to order successfully.',
        ], 201);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function generateRef(): string
    {
        $timestamp = strtoupper(base_convert(substr((string) now()->timestamp, -5), 10, 36));
        $rand      = strtoupper(Str::random(3));

        return "OKL-{$timestamp}{$rand}";
    }

    private function writeConversionLog(Request $request, Order $order, string $quoteRef): void
    {
        try {
            $admin = $request->user();

            OrderLog::create([
                'order_id'         => $order->id,
                'order_ref'        => $order->ref,
                'admin_user_id'    => $admin?->id,
                'admin_user_email' => $admin?->email,
                'action'           => 'status_changed',
                'old_value'        => null,
                'new_value'        => 'confirmed',
                'notes'            => "Created from quote {$quoteRef}.",
                'ip_address'       => $request->ip(),
                'created_at'       => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('OrderLog write failed on quote conversion', [
                'order_ref'  => $order->ref,
                'quote_ref'  => $quoteRef,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    // -------------------------------------------------------------------------
    // Formatters
    // -------------------------------------------------------------------------

    private function formatList(QuoteRequest $r): array
    {
        return [
            'id'                       => $r->id,
            'ref_number'               => $r->ref_number,
            'full_name'                => $r->full_name,
            'company_name'             => $r->company_name,
            'email'                    => $r->email,
            'tyre_category'            => $r->tyre_category,
            'country'                  => $r->country,
            'quantity'                 => $r->quantity,
            'delivery_address'         => $r->delivery_address,
            'delivery_city'            => $r->delivery_city,
            'delivery_postal_code'     => $r->delivery_postal_code,
            'status'                   => $r->status,
            'created_at'               => $r->created_at?->toIso8601String(),
            'order_id'                 => $r->order_id,
            'has_attachment'           => (bool) $r->attachment_path,
            'attachment_url'           => $r->attachment_path ? url(Storage::url($r->attachment_path)) : null,
            'attachment_name'          => $r->attachment_original_name,
            'attachment_original_name' => $r->attachment_original_name,
            'attachment_size'          => $r->attachment_size,
            'attachment_mime'          => $r->attachment_mime,
        ];
    }

    private function formatDetail(QuoteRequest $r): array
    {
        $r->loadMissing('order');

        return [
            'id'                       => $r->id,
            'ref_number'               => $r->ref_number,
            'full_name'                => $r->full_name,
            'company_name'             => $r->company_name,
            'email'                    => $r->email,
            'phone'                    => $r->phone,
            'tyre_category'            => $r->tyre_category,
            'country'                  => $r->country,
            'quantity'                 => $r->quantity,
            'delivery_location'        => $r->delivery_location,
            'delivery_address'         => $r->delivery_address,
            'delivery_city'            => $r->delivery_city,
            'delivery_postal_code'     => $r->delivery_postal_code,
            'notes'                    => $r->notes,
            'status'                   => $r->status,
            'admin_notes'              => $r->admin_notes,
            'created_at'               => $r->created_at?->toIso8601String(),
            'updated_at'               => $r->updated_at?->toIso8601String(),
            'order_id'                 => $r->order_id,
            'order_ref'                => $r->order?->ref,
            'has_attachment'           => (bool) $r->attachment_path,
            'attachment_url'           => $r->attachment_path ? url(Storage::url($r->attachment_path)) : null,
            'attachment_name'          => $r->attachment_original_name,
            'attachment_original_name' => $r->attachment_original_name,
            'attachment_size'          => $r->attachment_size,
            'attachment_mime'          => $r->attachment_mime,
        ];
    }
}
