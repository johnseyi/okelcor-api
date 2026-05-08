<?php

namespace App\Console\Commands;

use App\Mail\EuDeclarationReminderMail;
use App\Models\EuDeclaration;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendEuDeclarationReminders extends Command
{
    protected $signature = 'eu-declarations:send-reminders
                            {--dry-run  : List eligible declarations without sending emails}
                            {--min-age= : Minimum age in days before first reminder (default: 14)}';

    protected $description = 'Send reminder emails to customers with unsigned EU entry certificates';

    // Never spam: wait at least this many days between reminders
    private const RESEND_INTERVAL_DAYS = 7;

    public function handle(): int
    {
        $minAge = (int) $this->option('min-age') ?: 14;
        $dryRun = $this->option('dry-run');

        $eligible = EuDeclaration::where('status', 'pending')
            ->where('created_at', '<=', now()->subDays($minAge))
            ->where(function ($q) {
                $q->whereNull('last_reminded_at')
                  ->orWhere('last_reminded_at', '<=', now()->subDays(self::RESEND_INTERVAL_DAYS));
            })
            ->orderBy('created_at')
            ->get();

        if ($eligible->isEmpty()) {
            $this->info('No declarations eligible for reminders.');
            return self::SUCCESS;
        }

        $this->line('');
        $this->line(
            $dryRun
                ? "Eligible declarations ({$eligible->count()}) — dry-run, no emails sent:"
                : "Sending reminders to {$eligible->count()} customer(s):"
        );
        $this->line('');

        $this->table(
            ['Order ref', 'Company', 'Email', 'Country', 'Created', 'Days pending', 'Reminders sent'],
            $eligible->map(fn ($d) => [
                $d->order_ref,
                $d->company_name,
                $d->customer_email,
                $d->country,
                $d->created_at?->format('Y-m-d'),
                (int) $d->created_at?->diffInDays(now()),
                $d->reminder_count,
            ])->toArray()
        );

        if ($dryRun) {
            $this->line('');
            $this->info('[DRY RUN] No emails sent. Re-run without --dry-run to send.');
            return self::SUCCESS;
        }

        $sent   = 0;
        $failed = 0;

        foreach ($eligible as $declaration) {
            try {
                Mail::to($declaration->customer_email)->send(new EuDeclarationReminderMail($declaration));

                $declaration->update([
                    'last_reminded_at' => now(),
                    'reminder_count'   => $declaration->reminder_count + 1,
                ]);

                Log::info('EU declaration reminder sent', [
                    'declaration_id' => $declaration->id,
                    'order_ref'      => $declaration->order_ref,
                    'reminder_count' => $declaration->reminder_count,
                    'email'          => $declaration->customer_email,
                ]);

                $this->line("  Sent → {$declaration->customer_email} ({$declaration->order_ref})");
                $sent++;
            } catch (\Throwable $e) {
                Log::error('EU declaration reminder failed', [
                    'declaration_id' => $declaration->id,
                    'order_ref'      => $declaration->order_ref,
                    'error'          => $e->getMessage(),
                ]);

                $this->error("  Failed → {$declaration->customer_email} ({$declaration->order_ref}): {$e->getMessage()}");
                $failed++;
            }
        }

        $this->line('');
        $this->info("Done. Sent: {$sent} | Failed: {$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
