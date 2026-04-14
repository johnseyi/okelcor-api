<?php

namespace App\Services;

use DragonBe\Vies\Vies;
use DragonBe\Vies\ViesException;
use DragonBe\Vies\ViesServiceException;

class VatValidationService
{
    // ISO 3166-1 alpha-2 EU country codes accepted by VIES
    private const EU_COUNTRIES = [
        'AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'EL', 'ES',
        'FI', 'FR', 'HR', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV', 'MT',
        'NL', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK', 'XI',
    ];

    /**
     * Validate a VAT number against the EU VIES service.
     *
     * Accepts formats: "DE811193234" or "DE 811193234" or just "811193234"
     * with country code derived from the prefix.
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

        if (! in_array(strtoupper($countryCode), self::EU_COUNTRIES, true)) {
            return [
                'valid'        => false,
                'name'         => null,
                'address'      => null,
                'country_code' => strtoupper($countryCode),
                'vat_number'   => $number,
                'message'      => "Country code {$countryCode} is not supported by the EU VIES service.",
            ];
        }

        try {
            $vies   = new Vies();
            $result = $vies->validateVat(strtoupper($countryCode), $number);

            $valid = $result->isValid();

            return [
                'valid'        => $valid,
                'name'         => $result->getName() ?: null,
                'address'      => $result->getAddress() ?: null,
                'country_code' => strtoupper($countryCode),
                'vat_number'   => $number,
                'message'      => $valid
                    ? 'VAT number is valid.'
                    : 'VAT number is not valid.',
            ];
        } catch (ViesServiceException $e) {
            // VIES service is temporarily down or returning a fault
            return [
                'valid'        => false,
                'name'         => null,
                'address'      => null,
                'country_code' => strtoupper($countryCode),
                'vat_number'   => $number,
                'message'      => 'VAT validation service unavailable, please try again.',
            ];
        } catch (ViesException $e) {
            return [
                'valid'        => false,
                'name'         => null,
                'address'      => null,
                'country_code' => strtoupper($countryCode),
                'vat_number'   => $number,
                'message'      => 'VAT validation service unavailable, please try again.',
            ];
        } catch (\Throwable $e) {
            return [
                'valid'        => false,
                'name'         => null,
                'address'      => null,
                'country_code' => strtoupper($countryCode),
                'vat_number'   => $number,
                'message'      => 'VAT validation service unavailable, please try again.',
            ];
        }
    }

    /**
     * Split a VAT string into [countryCode, number].
     * Handles: "DE811193234", "DE 811193234", "de811193234"
     */
    private function parseVatNumber(string $vatNumber): array
    {
        $clean = strtoupper(preg_replace('/\s+/', '', $vatNumber));

        // If starts with 2 letters, treat them as the country code
        if (preg_match('/^([A-Z]{2})(.+)$/', $clean, $matches)) {
            return [$matches[1], $matches[2]];
        }

        return [null, $clean];
    }
}
