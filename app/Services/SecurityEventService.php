<?php

namespace App\Services;

use App\Models\SecurityEvent;

class SecurityEventService
{
    public static function log(
        string $type,
        ?int $customerId,
        ?string $ip,
        ?string $userAgent,
        string $description,
        string $severity = 'info'
    ): SecurityEvent {
        return SecurityEvent::create([
            'type'        => $type,
            'customer_id' => $customerId,
            'ip_address'  => $ip,
            'user_agent'  => $userAgent,
            'description' => $description,
            'severity'    => $severity,
        ]);
    }
}
