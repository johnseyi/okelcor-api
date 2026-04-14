<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreQuoteRequestRequest;
use App\Models\QuoteRequest;
use App\Services\VatValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class QuoteRequestController extends Controller
{
    public function __construct(private VatValidationService $vatService) {}

    public function store(StoreQuoteRequestRequest $request): JsonResponse
    {
        $refNumber = $this->generateRef();
        $validated = $request->validated();

        $vatNumber = $validated['vat_number'] ?? null;
        $vatValid  = null;

        if ($vatNumber) {
            $vatResult = $this->vatService->validate($vatNumber);
            $vatValid  = $vatResult['valid'] ? 1 : 0;
        }

        $quote = QuoteRequest::create(array_merge(
            $validated,
            [
                'ref_number' => $refNumber,
                'status'     => 'new',
                'ip_address' => $request->ip(),
                'vat_number' => $vatNumber,
                'vat_valid'  => $vatValid,
            ]
        ));

        // Notify admin (logged in local dev — configure QUOTE_EMAIL env var for prod)
        Log::info('New quote request', ['ref' => $refNumber, 'email' => $quote->email]);

        return response()->json([
            'data' => [
                'ref_number' => $refNumber,
                'message'    => 'Quote request received. Our team will respond within 1 business day.',
            ],
        ], 201);
    }

    private function generateRef(): string
    {
        $timestamp = substr((string) now()->timestamp, -6);
        $rand      = strtoupper(Str::random(3));

        return "OKL-QR-{$timestamp}-{$rand}";
    }
}
