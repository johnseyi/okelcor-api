<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreQuoteRequestRequest;
use App\Mail\QuoteRequestAcknowledgement;
use App\Mail\QuoteRequestReceived;
use App\Models\Customer;
use App\Models\QuoteRequest;
use App\Services\VatValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

class QuoteRequestController extends Controller
{
    public function __construct(private VatValidationService $vatService) {}

    public function store(StoreQuoteRequestRequest $request): JsonResponse
    {
        $refNumber = $this->generateRef();
        $validated = $request->validated();

        // Strip VAT for individual (b2c) customers — they don't have a business VAT number
        $customer  = $this->resolveCustomerFromToken($request);
        $vatNumber = ($customer && $customer->customer_type === 'b2c')
            ? null
            : ($validated['vat_number'] ?? null);
        $vatValid  = null;

        if ($vatNumber) {
            $vatResult = $this->vatService->validate($vatNumber);
            $vatValid  = $vatResult['valid'] ? 1 : 0;
        }

        $quote = QuoteRequest::create(array_merge(
            $validated,
            [
                'customer_id' => $customer?->id,
                'ref_number'  => $refNumber,
                'status'      => 'new',
                'ip_address'  => $request->ip(),
                'vat_number'  => $vatNumber,
                'vat_valid'   => $vatValid,
            ]
        ));

        if ($request->hasFile('attachment')) {
            $file     = $request->file('attachment');
            $ext      = strtolower($file->getClientOriginalExtension());
            $filename = Str::uuid() . '.' . $ext;

            Storage::disk('public')->putFileAs('quote-attachments', $file, $filename);

            $quote->update([
                'attachment_path'          => 'quote-attachments/' . $filename,
                'attachment_original_name' => $file->getClientOriginalName(),
                'attachment_mime'          => $file->getMimeType(),
                'attachment_size'          => $file->getSize(),
            ]);
        }

        // Admin notification
        $quoteEmail = config('mail.quote_email');
        if ($quoteEmail) {
            try {
                Log::info('Sending quote request admin notification', ['ref' => $refNumber, 'to' => $quoteEmail]);
                Mail::to($quoteEmail)->send(new QuoteRequestReceived($quote));
                Log::info('Quote request admin notification sent', ['ref' => $refNumber]);
            } catch (\Throwable $e) {
                Log::error('Quote request admin notification failed', ['ref' => $refNumber, 'error' => $e->getMessage()]);
            }
        }

        // Customer acknowledgement
        try {
            Log::info('Sending quote request acknowledgement', ['ref' => $refNumber, 'to' => $quote->email]);
            Mail::to($quote->email)->send(new QuoteRequestAcknowledgement($quote));
            Log::info('Quote request acknowledgement sent', ['ref' => $refNumber]);
        } catch (\Throwable $e) {
            Log::error('Quote request acknowledgement failed', ['ref' => $refNumber, 'error' => $e->getMessage()]);
        }

        return response()->json([
            'data' => [
                'ref_number' => $refNumber,
                'message'    => 'Quote request received. Our team will respond within 1 business day.',
            ],
        ], 201);
    }

    private function resolveCustomerFromToken(Request $request): ?Customer
    {
        $raw = $request->bearerToken();
        if (! $raw) {
            return null;
        }

        $token = PersonalAccessToken::findToken($raw);
        if (! $token || $token->tokenable_type !== Customer::class) {
            return null;
        }

        return $token->tokenable;
    }

    private function generateRef(): string
    {
        $timestamp = substr((string) now()->timestamp, -6);
        $rand      = strtoupper(Str::random(3));

        return "OKL-QR-{$timestamp}-{$rand}";
    }
}
