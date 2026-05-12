<?php

namespace App\Console\Commands;

use App\Mail\AdminTwoFactorNotice;
use App\Models\AdminUser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendAdminTwoFactorNotice extends Command
{
    protected $signature = 'admin:send-2fa-notice
                            {--dry-run : List recipients without sending}';

    protected $description = 'Send a 2FA setup notice email to all active admin users who have not yet enabled 2FA.';

    public function handle(): int
    {
        $graceUntil = config('auth.admin_2fa_grace_until');

        $users = AdminUser::where('is_active', true)
            ->whereNull('two_factor_confirmed_at')
            ->orderBy('email')
            ->get();

        if ($users->isEmpty()) {
            $this->info('All active admin users already have 2FA enabled. No emails to send.');
            return self::SUCCESS;
        }

        $this->info("Found {$users->count()} admin user(s) without 2FA.");

        if ($this->option('dry-run')) {
            $this->table(
                ['ID', 'Name', 'Email', 'Role', 'Last Login'],
                $users->map(fn ($u) => [
                    $u->id,
                    $u->name,
                    $u->email,
                    $u->role,
                    $u->last_login_at?->toDateString() ?? 'never',
                ])->toArray()
            );
            $this->line('Dry run — no emails sent.');
            return self::SUCCESS;
        }

        $sent   = 0;
        $failed = 0;

        foreach ($users as $user) {
            try {
                Mail::to($user->email)->send(new AdminTwoFactorNotice($user, $graceUntil));
                $this->line("  ✓ Sent to {$user->email}");
                $sent++;
            } catch (\Throwable $e) {
                $this->error("  ✗ Failed for {$user->email}: {$e->getMessage()}");
                $failed++;
            }
        }

        $this->newLine();
        $this->info("Done. Sent: {$sent}, Failed: {$failed}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
