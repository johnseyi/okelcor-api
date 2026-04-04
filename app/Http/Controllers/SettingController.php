<?php

namespace App\Http\Controllers;

use App\Models\SiteSetting;
use Illuminate\Http\JsonResponse;

class SettingController extends Controller
{
    private const PUBLIC_KEYS = [
        'company_name',
        'company_email',
        'company_phone',
        'company_fax',
        'company_address',
        'stripe_enabled',
        'paypal_enabled',
        'klarna_enabled',
        'vat_rate',
        'default_currency',
        'site_tagline',
        'contact_email',
        'quote_email',
    ];

    public function public(): JsonResponse
    {
        $settings = SiteSetting::whereIn('key', self::PUBLIC_KEYS)
            ->orderBy('group')
            ->orderBy('key')
            ->get();

        $data = $settings->map(fn ($s) => [
            'key'   => $s->key,
            'value' => $this->castValue($s->value, $s->type),
        ])->values();

        return response()->json(['data' => $data]);
    }

    // Legacy endpoint — kept for backwards compatibility
    public function index(): JsonResponse
    {
        return $this->public();
    }

    private function castValue(?string $value, string $type): mixed
    {
        return match ($type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'json'    => json_decode($value, true),
            default   => $value,
        };
    }
}
