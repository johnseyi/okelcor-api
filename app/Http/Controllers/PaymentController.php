<?php

namespace App\Http\Controllers;

use App\Mail\OrderConfirmation;
use App\Mail\OrderReceived;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\StripeService;
use App\Services\TaxService;
use App\Services\VatValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use UnexpectedValueException;

class PaymentController extends Controller
{
    public function __construct(
        private StripeService $stripeService,
        private VatValidationService $vatService,
        private TaxService $taxService,
    ) {}

    /**
     * POST /api/v1/payments/create-session
     *
     * Saves a pending order, creates a Stripe Checkout Session, and returns the
     * hosted checkout URL for the frontend.
     *
     * Request body:
     * {
     *   "delivery": { "name", "email", "address", "city", "postalCode", "country", "phone" },
     *   "paymentMethod": "stripe",
     *   "vat_number": "DE...",   (optional)
     *   "items": [
     *     { "product": { "id", "brand", "name", "size", "price" }, "quantity": 4 }
     *   ]
     * }
     */
    public function createSession(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'delivery'               => ['required', 'array'],
            'delivery.name'          => ['required', 'string', 'max:200'],
            'delivery.email'         => ['required', 'email', 'max:255'],
            'delivery.address'       => ['required', 'string', 'max:300'],
            'delivery.city'          => ['required', 'string', 'max:100'],
            'delivery.postalCode'    => ['required', 'string', 'max:20'],
            'delivery.country'       => ['required', 'string', 'max:100'],
            'delivery.phone'         => ['sometimes', 'nullable', 'string', 'max:50'],
            'paymentMethod'          => ['sometimes', 'nullable', 'string', 'in:stripe'],
            'payment_method'         => ['sometimes', 'nullable', 'string', 'in:stripe'],
            'vat_number'             => ['sometimes', 'nullable', 'string', 'max:20'],
            'items'                  => ['required', 'array', 'min:1'],
            'items.*.product'        => ['required', 'array'],
            'items.*.product.id'     => ['required', 'integer'],
            'items.*.product.brand'  => ['required', 'string', 'max:200'],
            'items.*.product.name'   => ['required', 'string', 'max:300'],
            'items.*.product.size'   => ['required', 'string', 'max:50'],
            'items.*.product.price'  => ['required', 'numeric', 'min:0'],
            'items.*.quantity'       => ['required', 'integer', 'min:1'],
        ]);

        $delivery = $validated['delivery'];
        $items    = $validated['items'];

        $vatNumber = $validated['vat_number'] ?? null;
        $vatValid  = null;
        if ($vatNumber) {
            $vatResult = $this->vatService->validate($vatNumber);
            $vatValid  = $vatResult['valid'] ? 1 : 0;
        }

        // Use DB prices to prevent client-side price manipulation
        $productIds = collect($items)->pluck('product.id')->unique()->values()->all();
        $products   = Product::whereIn('id', $productIds)->get()->keyBy('id');

        $lineItems = [];
        $subtotal  = 0;

        foreach ($items as $item) {
            $productData = $item['product'];
            $productId   = $productData['id'];
            $quantity    = (int) $item['quantity'];
            $dbProduct   = $products->get($productId);

            $unitPrice = $dbProduct ? (float) $dbProduct->price : (float) $productData['price'];
            $lineTotal = $unitPrice * $quantity;
            $subtotal += $lineTotal;

            $lineItems[] = [
                'product_id' => $productId,
                'sku'        => $dbProduct?->sku,
                'brand'      => $productData['brand'],
                'name'       => $productData['name'],
                'size'       => $productData['size'],
                'unit_price' => $unitPrice,
                'quantity'   => $quantity,
                'line_total' => $lineTotal,
            ];
        }

        // Calculate tax before the transaction — subtotal is known, no DB needed
        $customerType = $this->resolveCustomerType($request);
        $vatValidBool = $vatValid !== null ? (bool) $vatValid : null;
        $tax          = $this->taxService->calculate($delivery['country'], $vatValidBool, $customerType);
        $taxAmount    = round($subtotal * $tax['tax_rate'] / 100, 2);
        $total        = $subtotal + $taxAmount; // delivery_cost is always 0 on Stripe checkout

        $ref = $this->generateRef();

        try {
            $order = DB::transaction(function () use (
                $delivery, $lineItems, $subtotal, $total, $ref, $request,
                $vatNumber, $vatValid, $tax, $taxAmount
            ) {
                $order = Order::create([
                    'ref'               => $ref,
                    'customer_name'     => $delivery['name'],
                    'customer_email'    => $delivery['email'],
                    'customer_phone'    => $delivery['phone'] ?? null,
                    'address'           => $delivery['address'],
                    'city'              => $delivery['city'],
                    'postal_code'       => $delivery['postalCode'],
                    'country'           => $delivery['country'],
                    'payment_method'    => 'stripe',
                    'subtotal'          => $subtotal,
                    'delivery_cost'     => 0.00,
                    'total'             => $total,
                    'status'            => 'pending',
                    'payment_status'    => 'pending',
                    'mode'              => 'live',
                    'ip_address'        => $request->ip(),
                    'vat_number'        => $vatNumber,
                    'vat_valid'         => $vatValid,
                    'tax_treatment'     => $tax['tax_treatment'],
                    'tax_rate'          => $tax['tax_rate'],
                    'tax_amount'        => $taxAmount,
                    'is_reverse_charge' => $tax['is_reverse_charge'],
                ]);

                foreach ($lineItems as $line) {
                    OrderItem::create(['order_id' => $order->id] + $line);
                }

                return $order;
            });

            // Build Stripe line items: net product items + VAT as separate line (standard only)
            $currency    = strtolower((string) config('services.stripe.currency', 'eur'));
            $stripeItems = $lineItems;

            if ($taxAmount > 0) {
                $stripeItems[] = [
                    'name'       => 'VAT (' . number_format($tax['tax_rate'], 0) . '%)',
                    'unit_price' => $taxAmount,
                    'quantity'   => 1,
                ];
            }

            $result = $this->stripeService->createCheckoutSession([
                'ref'            => $ref,
                'order_ref'      => $ref,
                'customer_email' => $delivery['email'],
                'currency'       => $currency,
                'items'          => $stripeItems,
            ]);

            $order->update(['payment_session_id' => $result['checkout_session_id']]);

            Log::info('Stripe checkout session created', [
                'ref'                 => $ref,
                'checkout_session_id' => $result['checkout_session_id'],
                'amount'              => $subtotal,
            ]);

            return response()->json([
                'data' => [
                    'provider'            => 'stripe',
                    'order_ref'           => $ref,
                    'checkout_session_id' => $result['checkout_session_id'],
                    'checkout_url'        => $result['checkout_url'],
                ],
            ], 201);
        } catch (\Throwable $e) {
            Log::error('createSession failed', ['error' => $e->getMessage(), 'ref' => $ref]);

            return response()->json([
                'message' => 'Payment gateway error. Please try again.',
            ], 502);
        }
    }

    /**
     * POST /api/v1/payments/webhook
     *
     * Handles Stripe webhook notifications for Checkout payments.
     */
    public function webhook(Request $request): JsonResponse
    {
        $secret = config('services.stripe.webhook_secret');

        if (! is_string($secret) || trim($secret) === '') {
            Log::error('Stripe webhook secret is not configured.');

            return response()->json(['message' => 'Stripe webhook is not configured.'], 500);
        }

        try {
            $event = Webhook::constructEvent(
                $request->getContent(),
                (string) $request->header('Stripe-Signature'),
                $secret
            );
        } catch (UnexpectedValueException $e) {
            return response()->json(['message' => 'Invalid payload.'], 400);
        } catch (SignatureVerificationException $e) {
            return response()->json(['message' => 'Invalid signature.'], 400);
        }

        $object = $this->stripeObjectToArray($event->data->object ?? []);
        $order = $this->resolveOrderFromStripeObject($object);

        Log::info('Stripe webhook received', [
            'event'     => $event->type,
            'order_ref' => $order?->ref,
            'object_id' => $object['id'] ?? null,
        ]);

        if (! $order && in_array($event->type, [
            'checkout.session.completed',
            'payment_intent.payment_failed',
            'charge.refunded',
        ], true)) {
            return response()->json(['message' => 'Order not found.'], 404);
        }

        match ($event->type) {
            'checkout.session.completed' => $this->markOrderPaid($order, $object),
            'payment_intent.payment_failed' => $this->markOrderFailed($order),
            'charge.refunded' => $this->markOrderRefunded($order),
            default => null,
        };

        return response()->json(['message' => 'Webhook received.']);
    }

    private function resolveOrderFromStripeObject(array $object): ?Order
    {
        $checkoutSessionId = $object['id'] ?? null;
        if (is_string($checkoutSessionId) && str_starts_with($checkoutSessionId, 'cs_')) {
            $order = Order::where('payment_session_id', $checkoutSessionId)->first();
            if ($order) {
                return $order;
            }
        }

        $orderRef = $object['metadata']['order_ref'] ?? $object['client_reference_id'] ?? null;
        if (is_string($orderRef) && $orderRef !== '') {
            return Order::where('ref', $orderRef)->first();
        }

        return null;
    }

    private function markOrderPaid(?Order $order, array $object): void
    {
        if (! $order) {
            return;
        }

        // Idempotency guard — Stripe may retry webhooks; skip if already processed
        if ($order->payment_status === 'paid') {
            Log::info('Stripe webhook: order already paid, skipping duplicate', ['ref' => $order->ref]);
            return;
        }

        $order->update([
            'payment_status'     => 'paid',
            'payment_session_id' => $object['id'] ?? $order->payment_session_id,
            'status'             => 'confirmed',
        ]);

        $order->load('items');

        $invoice = $this->createInvoiceForOrder($order);

        try {
            Mail::to($order->customer_email)->send(new OrderConfirmation($order, $invoice));
            Log::info('Stripe order confirmation email sent', [
                'ref'            => $order->ref,
                'customer_email' => $order->customer_email,
            ]);
        } catch (\Throwable $e) {
            Log::error('Stripe order confirmation email failed', [
                'ref'   => $order->ref,
                'error' => $e->getMessage(),
            ]);
        }

        $adminEmail = config('mail.order_email');
        if ($adminEmail) {
            try {
                Mail::to($adminEmail)->send(new OrderReceived($order));
                Log::info('Stripe admin order notification sent', [
                    'ref'         => $order->ref,
                    'admin_email' => $adminEmail,
                ]);
            } catch (\Throwable $e) {
                Log::error('Stripe admin order notification email failed', [
                    'ref'   => $order->ref,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function markOrderFailed(?Order $order): void
    {
        if (! $order) {
            return;
        }

        $order->update([
            'payment_status' => 'failed',
            'status'         => 'cancelled',
        ]);
    }

    private function markOrderRefunded(?Order $order): void
    {
        if (! $order) {
            return;
        }

        $order->update([
            'payment_status' => 'refunded',
        ]);
    }

    private function createInvoiceForOrder(Order $order): ?Invoice
    {
        try {
            $customer = Customer::where('email', $order->customer_email)->first();

            if (! $customer) {
                Log::info('Invoice skipped: no customer account for order', [
                    'ref'   => $order->ref,
                    'email' => $order->customer_email,
                ]);
                return null;
            }

            // Check if an invoice record already exists for this order
            $invoice = Invoice::where('order_ref', $order->ref)->first();

            if ($invoice && $invoice->pdf_url) {
                // Invoice record and PDF both exist — fully complete, nothing to do
                return $invoice;
            }

            if (! $invoice) {
                // Create the invoice record inside a transaction with a sequence lock
                $invoice = DB::transaction(function () use ($customer, $order) {
                    $year   = now()->year;
                    $prefix = "INV-{$year}-";

                    // Lock year's rows to prevent concurrent sequence conflicts
                    $lastNumber = Invoice::where('invoice_number', 'like', "{$prefix}%")
                        ->lockForUpdate()
                        ->orderByDesc('invoice_number')
                        ->value('invoice_number');

                    $sequence      = $lastNumber ? (int) substr($lastNumber, strlen($prefix)) + 1 : 1;
                    $invoiceNumber = $prefix . str_pad($sequence, 4, '0', STR_PAD_LEFT);

                    return Invoice::create([
                        'customer_id'       => $customer->id,
                        'invoice_number'    => $invoiceNumber,
                        'issued_at'         => now(),
                        'due_at'            => null,
                        'amount'            => $order->total,
                        'status'            => 'paid',
                        'pdf_url'           => null,
                        'order_ref'         => $order->ref,
                        'subtotal_net'      => (float) $order->subtotal + (float) $order->delivery_cost,
                        'tax_treatment'     => $order->tax_treatment,
                        'tax_rate'          => $order->tax_rate,
                        'tax_amount'        => $order->tax_amount,
                        'is_reverse_charge' => $order->is_reverse_charge,
                    ]);
                });

                Log::info('Invoice created for order', [
                    'ref'            => $order->ref,
                    'invoice_number' => $invoice->invoice_number,
                ]);
            }

            // Generate PDF for both new invoices and existing ones with pdf_url=null
            Log::info('Invoice PDF generation started', [
                'invoice'   => $invoice->invoice_number,
                'order_ref' => $order->ref,
            ]);

            try {
                $pdf  = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.invoice', [
                    'invoice' => $invoice,
                    'order'   => $order,
                ]);

                $path = "invoices/{$invoice->invoice_number}.pdf";

                \Illuminate\Support\Facades\Storage::disk('public')->put($path, $pdf->output());

                $invoice->update(['pdf_url' => $path]);

                Log::info('Invoice PDF generated', [
                    'invoice' => $invoice->invoice_number,
                    'path'    => $path,
                ]);
            } catch (\Throwable $e) {
                Log::warning('Invoice PDF generation failed', [
                    'invoice' => $invoice->invoice_number,
                    'error'   => $e->getMessage(),
                ]);
            }

            return $invoice;
        } catch (\Throwable $e) {
            Log::warning('Invoice creation failed for order', [
                'ref'   => $order->ref,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function stripeObjectToArray(mixed $object): array
    {
        if (is_array($object)) {
            return $object;
        }

        if (is_object($object) && method_exists($object, 'toArray')) {
            return $object->toArray();
        }

        return [];
    }

    private function resolveCustomerType(Request $request): ?string
    {
        $raw = $request->bearerToken();
        if (! $raw) {
            return null;
        }

        $token = PersonalAccessToken::findToken($raw);
        if (! $token || $token->tokenable_type !== Customer::class) {
            return null;
        }

        return $token->tokenable?->customer_type;
    }

    private function generateRef(): string
    {
        $timestamp = strtoupper(base_convert(substr((string) now()->timestamp, -5), 10, 36));
        $rand      = strtoupper(Str::random(3));

        return "OKL-{$timestamp}{$rand}";
    }
}
