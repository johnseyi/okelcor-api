<?php

namespace App\Console\Commands;

use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CleanupTestStripeOrders extends Command
{
    protected $signature = 'orders:cleanup-test-stripe
                            {--date=   : Date to target (YYYY-MM-DD, required)}
                            {--email=  : Customer email to target (required)}
                            {--dry-run : List matching orders without deleting}';

    protected $description = 'Delete test Stripe orders for a specific date and email (always --dry-run first)';

    public function handle(): int
    {
        $date    = $this->option('date');
        $email   = $this->option('email');
        $dryRun  = $this->option('dry-run');

        if (! $date || ! $email) {
            $this->error('Both --date and --email are required.');
            $this->line('Usage: php artisan orders:cleanup-test-stripe --date=2026-05-02 --email=you@example.com --dry-run');
            return self::FAILURE;
        }

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $this->error('--date must be YYYY-MM-DD format.');
            return self::FAILURE;
        }

        $orders = Order::with('items')
            ->where('payment_method', 'stripe')
            ->where('customer_email', $email)
            ->whereDate('created_at', $date)
            ->orderBy('created_at')
            ->get();

        if ($orders->isEmpty()) {
            $this->info("No Stripe orders found for [{$email}] on [{$date}].");
            return self::SUCCESS;
        }

        $this->line('');
        $this->line("Matched Stripe orders — email: {$email} | date: {$date}");
        $this->line('');

        $this->table(
            ['Ref', 'Status', 'Payment status', 'Total', 'Items', 'Created at'],
            $orders->map(fn ($o) => [
                $o->ref,
                $o->status,
                $o->payment_status,
                '€' . number_format((float) $o->total, 2),
                $o->items->count(),
                $o->created_at?->format('Y-m-d H:i:s'),
            ])->toArray()
        );

        $this->line('');
        $this->warn("Total matched: {$orders->count()} order(s)");
        $this->line('');

        if ($dryRun) {
            $this->info('[DRY RUN] No changes made. Re-run without --dry-run to delete.');
            return self::SUCCESS;
        }

        if (! $this->confirm("Permanently delete these {$orders->count()} order(s)? This cannot be undone.")) {
            $this->info('Aborted. No changes made.');
            return self::SUCCESS;
        }

        $deleted = [];

        DB::transaction(function () use ($orders, &$deleted) {
            foreach ($orders as $order) {
                $order->items()->delete();
                $order->delete();
                $deleted[] = $order->ref;
            }
        });

        foreach ($deleted as $ref) {
            $this->line("  Deleted: {$ref}");
        }

        Log::info('Test Stripe orders deleted', [
            'refs'  => $deleted,
            'email' => $email,
            'date'  => $date,
            'count' => count($deleted),
        ]);

        $this->line('');
        $this->info('Done. ' . count($deleted) . ' order(s) deleted.');

        return self::SUCCESS;
    }
}
