<?php

namespace App\Console\Commands;

use App\Models\EuDeclaration;
use Illuminate\Console\Command;

class EuDeclarationsOutstanding extends Command
{
    protected $signature = 'eu-declarations:outstanding
                            {--min-age=  : Only show declarations older than N days}
                            {--country=  : Filter by country}
                            {--overdue   : Only show declarations older than 14 days (overdue)}';

    protected $description = 'List all pending EU entry certificates with age and contact details';

    private const OVERDUE_DAYS = 14;

    public function handle(): int
    {
        $query = EuDeclaration::where('status', 'pending')->orderBy('created_at');

        if ($this->option('overdue')) {
            $query->where('created_at', '<=', now()->subDays(self::OVERDUE_DAYS));
        } elseif ($minAge = (int) $this->option('min-age')) {
            $query->where('created_at', '<=', now()->subDays($minAge));
        }

        if ($country = $this->option('country')) {
            $query->where('country', $country);
        }

        $declarations = $query->get();

        if ($declarations->isEmpty()) {
            $this->info('No outstanding EU entry certificates.');
            return self::SUCCESS;
        }

        $this->line('');
        $this->line("Outstanding EU entry certificates: {$declarations->count()}");
        $this->line('');

        $this->table(
            ['Order ref', 'Company', 'Email', 'Country', 'Created', 'Days pending', 'Reminders', 'Last reminded'],
            $declarations->map(function ($d) {
                $daysPending = (int) $d->created_at?->diffInDays(now());
                $overdue     = $daysPending >= self::OVERDUE_DAYS ? ' !' : '';
                return [
                    $d->order_ref,
                    $d->company_name,
                    $d->customer_email,
                    $d->country,
                    $d->created_at?->format('Y-m-d'),
                    $daysPending . $overdue,
                    $d->reminder_count,
                    $d->last_reminded_at?->format('Y-m-d') ?? '—',
                ];
            })->toArray()
        );

        $overdue = $declarations->filter(
            fn ($d) => (int) $d->created_at?->diffInDays(now()) >= self::OVERDUE_DAYS
        )->count();

        $this->line('');
        if ($overdue > 0) {
            $this->warn("{$overdue} declaration(s) overdue (14+ days) — marked with '!'");
        }
        $this->line('');

        return self::SUCCESS;
    }
}
