<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminOrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Order::orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('q')) {
            $q = $request->q;
            $query->where(function ($sub) use ($q) {
                $sub->where('ref', 'like', "%{$q}%")
                    ->orWhere('customer_name', 'like', "%{$q}%")
                    ->orWhere('customer_email', 'like', "%{$q}%");
            });
        }

        $perPage   = min((int) $request->input('per_page', 20), 100);
        $paginated = $query->paginate($perPage);

        return response()->json([
            'data'    => $paginated->map(fn ($o) => $this->formatOrderList($o)),
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
        $order = Order::with('items')->findOrFail($id);

        return response()->json([
            'data'    => $this->formatOrderDetail($order),
            'message' => 'success',
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'status' => ['required', Rule::in(['pending', 'confirmed', 'shipped', 'delivered', 'cancelled'])],
        ]);

        $order = Order::findOrFail($id);
        $order->update(['status' => $request->status]);
        $order->load('items');

        return response()->json([
            'data'    => $this->formatOrderDetail($order),
            'message' => 'success',
        ]);
    }

    private function formatOrderList(Order $o): array
    {
        return [
            'id'             => $o->id,
            'order_ref'      => $o->ref,
            'customer_name'  => $o->customer_name,
            'customer_email' => $o->customer_email,
            'total'          => (float) $o->total,
            'status'         => $o->status,
            'payment_method' => $o->payment_method,
            'created_at'     => $o->created_at?->toIso8601String(),
        ];
    }

    private function formatOrderDetail(Order $o): array
    {
        return [
            'id'             => $o->id,
            'order_ref'      => $o->ref,
            'customer_name'  => $o->customer_name,
            'customer_email' => $o->customer_email,
            'phone'          => $o->customer_phone,
            'company_name'   => null,
            'address'        => trim(implode(', ', array_filter([$o->address, $o->city, $o->postal_code]))),
            'country'        => $o->country,
            'total'          => (float) $o->total,
            'status'         => $o->status,
            'payment_method' => $o->payment_method,
            'notes'          => $o->admin_notes,
            'created_at'     => $o->created_at?->toIso8601String(),
            'updated_at'     => $o->updated_at?->toIso8601String(),
            'items'          => $o->items->map(fn ($i) => [
                'id'           => $i->id,
                'product_id'   => $i->product_id,
                'product_name' => $i->name,
                'brand'        => $i->brand,
                'size'         => $i->size,
                'sku'          => $i->sku,
                'quantity'     => $i->quantity,
                'unit_price'   => (float) $i->unit_price,
                'subtotal'     => (float) $i->line_total,
            ])->values(),
        ];
    }
}
