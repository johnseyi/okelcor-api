<?php

namespace App\Services;

use App\Mail\WixCustomerWelcome;
use App\Models\Customer;
use App\Models\CustomerAddress;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class WixCustomerImportService
{
    private const PERSONAL_DOMAINS = [
        'gmail.com', 'hotmail.com', 'yahoo.com', 'outlook.com', 'icloud.com',
        'aol.com', 'live.com', 'msn.com', 'ebay.com', 'onetel.com',
        'web.de', 'gmx.de', 'gmx.net', 't-online.de', 'freenet.de', 'mail.ru',
    ];

    public function import(string $filePath, bool $sendEmails = true): array
    {
        if (! file_exists($filePath)) {
            throw new \RuntimeException("File not found: {$filePath}");
        }

        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            throw new \RuntimeException("Cannot open file: {$filePath}");
        }

        // Strip UTF-8 BOM from first header field
        $rawHeaders    = fgetcsv($handle);
        $rawHeaders[0] = ltrim($rawHeaders[0], "\xEF\xBB\xBF");
        $headers       = array_map('trim', $rawHeaders);

        $stats = [
            'imported'          => 0,
            'skipped_no_email'  => 0,
            'skipped_duplicate' => 0,
            'b2b'               => 0,
            'b2c'               => 0,
            'errors'            => [],
        ];

        $row = 1;
        while (($data = fgetcsv($handle)) !== false) {
            $row++;

            if (count($data) !== count($headers)) {
                // Pad short rows to avoid offset errors
                $data = array_pad($data, count($headers), '');
            }

            $record = array_combine($headers, $data);
            $record = array_map('trim', $record);

            $email = $record['Email 1'] ?? '';

            if (empty($email) || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $stats['skipped_no_email']++;
                continue;
            }

            $email = strtolower($email);

            if (Customer::where('email', $email)->exists()) {
                $stats['skipped_duplicate']++;
                continue;
            }

            try {
                $customerType = $this->detectCustomerType($record);
                $password     = Str::random(12);

                $firstName = $record['First Name'] ?? '';
                $lastName  = $record['Last Name'] ?? '';

                // Fall back to email username if both name fields are empty or placeholder
                if (empty($firstName) && empty($lastName)) {
                    $firstName = ucfirst(explode('@', $email)[0]);
                }

                $customer = Customer::create([
                    'customer_type'      => $customerType,
                    'first_name'         => $firstName ?: $email,
                    'last_name'          => $lastName ?: '',
                    'email'              => $email,
                    'password'           => bcrypt($password),
                    'phone'              => $this->cleanPhone($record['Phone 1'] ?? ''),
                    'country'            => $record['Address 1 - Country'] ?? null,
                    'company_name'       => $record['Company'] ?? null ?: null,
                    'vat_number'         => $record['VAT ID'] ?? null ?: null,
                    'vat_verified'       => false,
                    'must_reset_password' => true,
                    'imported_from_wix'  => true,
                    'email_verified_at'  => now(),
                    'is_active'          => true,
                ]);

                $this->maybeCreateAddress($customer, $record);

                if ($sendEmails) {
                    Mail::to($email)->send(new WixCustomerWelcome($customer, $password));
                }

                $stats['imported']++;
                $stats[$customerType]++;

            } catch (\Throwable $e) {
                $stats['errors'][] = "Row {$row} ({$email}): " . $e->getMessage();
            }
        }

        fclose($handle);

        return $stats;
    }

    private function detectCustomerType(array $record): string
    {
        // Explicit company field → always b2b
        $company = trim($record['Company'] ?? '');
        if (! empty($company)) {
            return 'b2b';
        }

        $email  = strtolower(trim($record['Email 1'] ?? ''));
        $domain = substr($email, strpos($email, '@') + 1);

        return $this->isPersonalDomain($domain) ? 'b2c' : 'b2b';
    }

    private function isPersonalDomain(string $domain): bool
    {
        foreach (self::PERSONAL_DOMAINS as $personal) {
            // Exact match (gmail.com) or subdomain match (members.ebay.com)
            if ($domain === $personal || str_ends_with($domain, '.' . $personal)) {
                return true;
            }
        }

        return false;
    }

    private function maybeCreateAddress(Customer $customer, array $record): void
    {
        $street = trim($record['Address 1 - Street'] ?? '');
        $city   = trim($record['Address 1 - City'] ?? '');

        if (empty($street) && empty($city)) {
            return;
        }

        $fullName = trim($customer->first_name . ' ' . $customer->last_name)
            ?: ($customer->company_name ?? $customer->email);

        CustomerAddress::create([
            'customer_id'   => $customer->id,
            'full_name'     => $fullName,
            'address_line_1' => $street,
            'address_line_2' => trim($record['Address 1 - Street Line 2'] ?? '') ?: null,
            'city'          => $city,
            'postcode'      => trim($record['Address 1 - Zip'] ?? '') ?: null,
            'country'       => trim($record['Address 1 - Country'] ?? '') ?: null,
            'phone'         => $this->cleanPhone($record['Phone 1'] ?? ''),
            'is_default'    => true,
        ]);
    }

    private function cleanPhone(string $phone): ?string
    {
        // Strip surrounding quotes Wix sometimes wraps phone numbers in
        $phone = trim($phone, " \t\n\r\0\x0B'\"");

        return empty($phone) ? null : $phone;
    }
}
