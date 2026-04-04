<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminNewsletterController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = NewsletterSubscriber::orderByDesc('subscribed_at');

        if ($request->boolean('confirmed_only')) {
            $query->where('is_confirmed', true);
        }
        if ($request->filled('locale')) {
            $query->where('locale', $request->locale);
        }

        $perPage   = min((int) $request->input('per_page', 50), 200);
        $paginated = $query->paginate($perPage);

        return response()->json([
            'data' => $paginated->map(fn ($s) => [
                'id'              => $s->id,
                'email'           => $s->email,
                'locale'          => $s->locale,
                'is_confirmed'    => (bool) $s->is_confirmed,
                'subscribed_at'   => $s->subscribed_at?->toIso8601String(),
                'unsubscribed_at' => $s->unsubscribed_at?->toIso8601String(),
            ]),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
                'last_page'    => $paginated->lastPage(),
            ],
        ]);
    }

    public function destroy(string $email): JsonResponse
    {
        $subscriber = NewsletterSubscriber::where('email', $email)->firstOrFail();
        $subscriber->delete();

        return response()->json(['message' => 'Subscriber removed.']);
    }
}
