<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class VatValidationService
{
    // ISO 3166-1 alpha-2 EU country codes accepted by VIES
    private const EU_COUNTRIES = [
        'AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'EL', 'ES',
        'FI', 'FR', 'HR', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV', 'MT',
        'NL', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK', 'XI',
    ];

    private const VIES_REST_BASE = 'https://ec.europa.eu/taxation_customs/vies/rest-api/ms';

    /**
     * Validate a VAT number against the EU VIES REST API (no SOAP required).
     *
     * Accepts formats: "DE811193234", "DE 811193234", "de811193234"
     *
     * @return array{valid: bool, name: string|null, address: string|null, country_code: string, vat_number: string, message: string}
     */
    public function validate(string $vatNumber): array
    {
        [$countryCode, $number] = $this->parseVatNumber($vatNumber);

        if (! $countryCode) {
            return [
                'valid'        => false,
                'name'         => null,
                'address'      => null,
                'country_code' => '',
                'vat_number'   => $number,
                'message'      => 'Could not determine country code. Please include the 2-letter country prefix (e.g. DE811193234).',
            ];
        }

        $countryCode = strtoupper($countryCode);

        if (! in_array($countryCode, self::EU_COUNTRIES, true)) {
            return [
                'valid'        => false,
                'name'         => null,
                'address'      => null,
                'country_code' => $countryCode,
                'vat_number'   => $number,
                'message'      => "Country code {$countryCode} is not supported by the EU VIES service.",
            ];
        }

        try {
            $response = Http::timeout(10)->get(
                self::VIES_REST_BASE . "/{$countryCode}/vat/{$number}"
            );

            if (! $response->successful()) {
                return $this->unavailable($countryCode, $number);
            }

            $data  = $response->json();
            $valid = (bool) ($data['isValid'] ?? false);

            $name    = $this->blankToNull($data['name'] ?? null);
            $address = $this->blankToNull($data['address'] ?? null);

            return [
                'valid'        => $valid,
                'name'         => $name,
                'address'      => $address,
                'country_code' => $countryCode,
                'vat_number'   => $number,
                'message'      => $valid
                    ? 'VAT number is valid.'
                    : 'VAT number is not valid.',
            ];
        } catch (\Throwable $e) {
            return $this->unavailable($countryCode, $number);
        }
    }

    /**
     * Split a VAT string into [countryCode, number].
     * Handles: "DE811193234", "DE 811193234", "de811193234"
     */
    private function parseVatNumber(string $vatNumber): array
    {
        $clean = strtoupper(preg_replace('/\s+/', '', $vatNumber));

        if (preg_match('/^([A-Z]{2})(.+)$/', $clean, $matches)) {
            return [$matches[1], $matches[2]];
        }

        return [null, $clean];
    }

    private function unavailable(string $countryCode, string $number): array
    {
        return [
            'valid'        => false,
            'name'         => null,
            'address'      => null,
            'country_code' => $countryCode,
            'vat_number'   => $number,
            'message'      => 'VAT validation service unavailable, please try again.',
        ];
    }

    // VIES REST API returns "---" when a field is not available
    private function blankToNull(?string $value): ?string
    {
        if ($value === null || trim($value) === '' || trim($value) === '---') {
            return null;
        }

        return $value;
    }
}
