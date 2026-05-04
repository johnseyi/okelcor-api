<?php

namespace App\Services;

class TaxService
{
    private const DE_RATE   = 19.00;
    private const ZERO_RATE = 0.00;

    /**
     * EU member state ISO codes (for VAT purposes).
     * Greece: VIES uses 'EL', ISO uses 'GR' — both normalised to 'GR' in COUNTRY_MAP.
     * Northern Ireland 'XI' retains EU VAT rules post-Brexit.
     */
    private const EU_CODES = [
        'AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'GR',
        'ES', 'FI', 'FR', 'HR', 'HU', 'IE', 'IT', 'LT', 'LU',
        'LV', 'MT', 'NL', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK', 'XI',
    ];

    /**
     * Maps country names and ISO codes (uppercased) to canonical ISO 2-letter codes.
     * Covers English names, German names, ISO codes, and common alternatives.
     */
    private const COUNTRY_MAP = [
        // Austria
        'AT' => 'AT', 'AUSTRIA' => 'AT', 'ÖSTERREICH' => 'AT', 'OESTERREICH' => 'AT',
        // Belgium
        'BE' => 'BE', 'BELGIUM' => 'BE', 'BELGIEN' => 'BE', 'BELGIQUE' => 'BE',
        // Bulgaria
        'BG' => 'BG', 'BULGARIA' => 'BG', 'BULGARIEN' => 'BG',
        // Cyprus
        'CY' => 'CY', 'CYPRUS' => 'CY', 'ZYPERN' => 'CY',
        // Czech Republic
        'CZ' => 'CZ', 'CZECH REPUBLIC' => 'CZ', 'CZECHIA' => 'CZ',
        'TSCHECHIEN' => 'CZ', 'TSCHECHISCHE REPUBLIK' => 'CZ', 'CZECH' => 'CZ',
        // Germany
        'DE' => 'DE', 'GERMANY' => 'DE', 'DEUTSCHLAND' => 'DE',
        // Denmark
        'DK' => 'DK', 'DENMARK' => 'DK', 'DÄNEMARK' => 'DK', 'DAENEMARK' => 'DK',
        // Estonia
        'EE' => 'EE', 'ESTONIA' => 'EE', 'ESTLAND' => 'EE',
        // Greece — EL (VIES) and GR (ISO) both map to 'GR'
        'EL' => 'GR', 'GR' => 'GR', 'GREECE' => 'GR', 'GRIECHENLAND' => 'GR',
        // Spain
        'ES' => 'ES', 'SPAIN' => 'ES', 'SPANIEN' => 'ES', 'ESPAÑA' => 'ES', 'ESPANA' => 'ES',
        // Finland
        'FI' => 'FI', 'FINLAND' => 'FI', 'FINNLAND' => 'FI', 'SUOMI' => 'FI',
        // France
        'FR' => 'FR', 'FRANCE' => 'FR', 'FRANKREICH' => 'FR',
        // Croatia
        'HR' => 'HR', 'CROATIA' => 'HR', 'KROATIEN' => 'HR',
        // Hungary
        'HU' => 'HU', 'HUNGARY' => 'HU', 'UNGARN' => 'HU',
        // Ireland
        'IE' => 'IE', 'IRELAND' => 'IE', 'IRLAND' => 'IE', 'REPUBLIC OF IRELAND' => 'IE',
        // Italy
        'IT' => 'IT', 'ITALY' => 'IT', 'ITALIEN' => 'IT', 'ITALIA' => 'IT',
        // Lithuania
        'LT' => 'LT', 'LITHUANIA' => 'LT', 'LITAUEN' => 'LT',
        // Luxembourg
        'LU' => 'LU', 'LUXEMBOURG' => 'LU', 'LUXEMBURG' => 'LU',
        // Latvia
        'LV' => 'LV', 'LATVIA' => 'LV', 'LETTLAND' => 'LV',
        // Malta
        'MT' => 'MT', 'MALTA' => 'MT',
        // Netherlands
        'NL' => 'NL', 'NETHERLANDS' => 'NL', 'THE NETHERLANDS' => 'NL',
        'NIEDERLANDE' => 'NL', 'HOLLAND' => 'NL',
        // Poland
        'PL' => 'PL', 'POLAND' => 'PL', 'POLEN' => 'PL',
        // Portugal
        'PT' => 'PT', 'PORTUGAL' => 'PT',
        // Romania
        'RO' => 'RO', 'ROMANIA' => 'RO', 'RUMÄNIEN' => 'RO', 'RUMAENIEN' => 'RO',
        // Sweden
        'SE' => 'SE', 'SWEDEN' => 'SE', 'SCHWEDEN' => 'SE',
        // Slovenia
        'SI' => 'SI', 'SLOVENIA' => 'SI', 'SLOWENIEN' => 'SI',
        // Slovakia
        'SK' => 'SK', 'SLOVAKIA' => 'SK', 'SLOWAKEI' => 'SK', 'SLOVAK REPUBLIC' => 'SK',
        // Northern Ireland — EU VAT rules apply post-Brexit
        'XI' => 'XI', 'NORTHERN IRELAND' => 'XI',
    ];

    /**
     * Determine VAT treatment for an order.
     *
     * Rules (Okelcor is a German-registered VAT entity, prices are NET ex-VAT):
     *   1. Germany (DE)               → standard 19 %
     *   2. EU (not DE) + B2B + valid VAT → reverse charge 0 %
     *   3. EU (not DE) + B2C or no valid VAT → standard 19 %
     *   4. Non-EU / unknown            → exempt 0 %
     *
     * @param  string|null $country      Country name or ISO code as supplied by the customer
     * @param  bool|null   $vatValid     True only when VIES confirmed the VAT number as valid
     * @param  string|null $customerType 'b2b' or 'b2c' — null treated as b2c for safety
     * @return array{tax_treatment: string, tax_rate: float, is_reverse_charge: bool, note: string|null}
     */
    public function calculate(?string $country, ?bool $vatValid, ?string $customerType = null): array
    {
        $code = $this->resolveCountryCode($country);

        // Rule 4: Non-EU or unrecognised country → exempt
        if ($code === null || ! in_array($code, self::EU_CODES, true)) {
            return [
                'tax_treatment'    => 'exempt',
                'tax_rate'         => self::ZERO_RATE,
                'is_reverse_charge' => false,
                'note'             => 'Export outside the EU — VAT exempt.',
            ];
        }

        // Rule 1: Germany → standard rate regardless of customer type
        if ($code === 'DE') {
            return [
                'tax_treatment'    => 'standard',
                'tax_rate'         => self::DE_RATE,
                'is_reverse_charge' => false,
                'note'             => null,
            ];
        }

        // Rules 2 & 3: EU country other than Germany
        $isB2b        = $customerType === 'b2b';
        $hasValidVat  = $vatValid === true;

        if ($isB2b && $hasValidVat) {
            // Rule 2: Intra-EU B2B with verified VAT → reverse charge
            return [
                'tax_treatment'    => 'reverse_charge',
                'tax_rate'         => self::ZERO_RATE,
                'is_reverse_charge' => true,
                'note'             => 'Reverse charge — VAT liability transfers to the recipient.',
            ];
        }

        // Rule 3: B2C or unverified VAT → standard German rate
        return [
            'tax_treatment'    => 'standard',
            'tax_rate'         => self::DE_RATE,
            'is_reverse_charge' => false,
            'note'             => null,
        ];
    }

    /**
     * Resolve a free-text country string to a canonical ISO 2-letter code.
     * Returns null when the country cannot be identified.
     */
    public function resolveCountryCode(?string $country): ?string
    {
        if ($country === null || trim($country) === '') {
            return null;
        }

        $key = strtoupper(trim($country));

        return self::COUNTRY_MAP[$key] ?? null;
    }

    /**
     * Convenience: return true if the given country resolves to an EU member state.
     */
    public function isEu(?string $country): bool
    {
        $code = $this->resolveCountryCode($country);

        return $code !== null && in_array($code, self::EU_CODES, true);
    }
}
