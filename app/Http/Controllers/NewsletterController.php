<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreNewsletterRequest;
use App\Mail\NewsletterConfirmation;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class NewsletterController extends Controller
{
    public function subscribe(StoreNewsletterRequest $request): JsonResponse
    {
        $email  = $request->input('email');
        $locale = $request->input('locale', 'en');

        $existing = NewsletterSubscriber::where('email', $email)->first();

        if ($existing && $existing->is_confirmed) {
            // Already confirmed — return 200 silently
            return response()->json([
                'message' => 'Please check your email to confirm your subscription',
            ]);
        }

        do {
            $token = Str::random(64);
        } while (NewsletterSubscriber::where('token', $token)->exists());

        if ($existing) {
            $existing->update([
                'token' => $token,
                'locale' => $locale,
                'is_confirmed' => false,
            ]);
        } else {
            NewsletterSubscriber::create([
                'email'        => $email,
                'locale'       => $locale,
                'is_confirmed' => false,
                'token'        => $token,
            ]);
        }

        $confirmUrl = url("/api/v1/newsletter/confirm/{$token}");
        Mail::to($email)->send(new NewsletterConfirmation($confirmUrl));

        return response()->json([
            'message' => 'Please check your email to confirm your subscription',
        ], 201);
    }

    public function confirm(string $token): RedirectResponse
    {
        $subscriber = NewsletterSubscriber::where('token', $token)->firstOrFail();

        $subscriber->update([
            'is_confirmed' => true,
            'token'        => null,
        ]);

        return redirect()->away('https://okelcor.de/?newsletter=confirmed');
    }
}
