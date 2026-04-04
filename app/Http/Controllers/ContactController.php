<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContactRequest;
use App\Models\ContactMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class ContactController extends Controller
{
    public function store(StoreContactRequest $request): JsonResponse
    {
        $message = ContactMessage::create(array_merge(
            $request->validated(),
            [
                'status'     => 'new',
                'ip_address' => $request->ip(),
            ]
        ));

        // Notify admin (logged in local dev — configure CONTACT_EMAIL env var for prod)
        Log::info('New contact message', ['id' => $message->id, 'email' => $message->email]);

        return response()->json([
            'data' => [
                'message' => 'Message received. We will respond within 2 business days.',
            ],
        ], 201);
    }
}
