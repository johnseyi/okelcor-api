<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreQuoteRequestRequest;
use App\Models\QuoteRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class QuoteRequestController extends Controller
{
    public function store(StoreQuoteRequestRequest $request): JsonResponse
    {
        $refNumber = $this->generateRef();

        $quote = QuoteRequest::create(array_merge(
            $request->validated(),
            [
                'ref_number' => $refNumber,
                'status'     => 'new',
                'ip_address' => $request->ip(),
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
