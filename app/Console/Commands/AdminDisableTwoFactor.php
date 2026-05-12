<?php

namespace App\Console\Commands;

use App\Models\AdminLoginHistory;
use App\Models\AdminSecurityEvent;
use App\Models\AdminUser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AdminDisableTwoFactor extends Command
{
    protected $signature = 'admin:2fa-disable
                            {email : Email address of the admin account}
                            {--force : Skip confirmation prompt}';

    protected $description = 'Emergency: disable 2FA for an admin account (SSH/super_admin recovery only).';

    public function handle(): int
    {
        $email = $this->argument('email');

        $admin = AdminUser::where('email', $email)->first();

        if (! $admin) {
            $this->error("No admin account found for: {$email}");
            return self::FAILURE;
        }

        // Show current state
        $this->newLine();
        $this->line('<fg=cyan;options=bold>Admin 2FA Emergency Disable</>');
        $this->line(str_repeat('─', 50));
        $this->line("ID:       {$admin->id}");
        $this->line("Name:     {$admin->name}");
        $this->line("Email:    {$admin->email}");
        $this->line("Role:     {$admin->role}");
        $this->line("2FA:      " . ($admin->hasTwoFactorEnabled() ? '<fg=yellow>ENABLED</>' : '<fg=green>already disabled</>'));
        $this->newLine();

        if (! $admin->hasTwoFactorEnabled()) {
            $this->info('2FA is already disabled for this account. No action needed.');
            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm("Disable 2FA for {$admin->email}?")) {
            $this->line('Aborted.');
            return self::SUCCESS;
        }

        // Clear all 2FA fields
        $admin->two_factor_secret         = null;
        $admin->two_factor_recovery_codes = null;
        $admin->two_factor_confirmed_at   = null;
        $admin->save();

        // Revoke all active Sanctum tokens so any in-flight challenge sessions
        // are dead — the admin must log in cleanly from scratch.
        $tokenCount = $admin->tokens()->count();
        $admin->tokens()->delete();

        // Audit log — write directly to avoid needing a Request object
        $this->writeAuditLog($admin);

        Log::warning('[2FA Emergency Disable] 2FA removed via artisan', [
            'admin_id'    => $admin->id,
            'admin_email' => $admin->email,
            'admin_role'  => $admin->role,
            'tokens_revoked' => $tokenCount,
        ]);

        $this->info("2FA disabled for {$admin->email}.");
        $this->line("  Tokens revoked: {$tokenCount}");
        $this->line("  Admin can now log in with email + password only.");
        $this->newLine();

        return self::SUCCESS;
    }

    private function writeAuditLog(AdminUser $admin): void
    {
        try {
            AdminSecurityEvent::create([
                'type'        => '2fa_emergency_disabled',
                'severity'    => 'critical',
                'admin_id'    => $admin->id,
                'admin_email' => $admin->email,
                'admin_role'  => $admin->role,
                'ip_address'  => '127.0.0.1',
                'user_agent'  => 'artisan/admin:2fa-disable',
                'description' => "2FA emergency-disabled via artisan for {$admin->email}",
                'metadata'    => ['source' => 'artisan', 'command' => 'admin:2fa-disable'],
            ]);
        } catch (\Throwable $e) {
            // Never let an audit write failure block recovery
            Log::warning('[2FA Emergency Disable] Audit log write failed: ' . $e->getMessage());
        }
    }
}
