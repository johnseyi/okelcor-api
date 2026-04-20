<?php

namespace App\Console\Commands;

use App\Services\WixCustomerImportService;
use Illuminate\Console\Command;

class ImportWixCustomers extends Command
{
    protected $signature = 'import:wix-customers
                            {file : Path to the Wix contacts CSV export file}
                            {--no-email : Skip sending welcome emails (dry-run friendly)}';

    protected $description = 'Import customers from a Wix contacts CSV export';

    public function handle(WixCustomerImportService $service): int
    {
        set_time_limit(600);
        ini_set('memory_limit', '256M');

        $filePath   = $this->argument('file');
        $sendEmails = ! $this->option('no-email');

        if ($sendEmails) {
            $this->info('Importing customers (welcome emails WILL be sent).');
            $this->warn('Use --no-email to skip emails for a dry run.');
        } else {
            $this->info('Importing customers (emails suppressed via --no-email).');
        }

        $this->newLine();

        try {
            $result = $service->import($filePath, $sendEmails);
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->info('Import complete.');
        $this->table(
            ['Imported', 'Skipped (no email)', 'Skipped (duplicate)', 'B2B', 'B2C'],
            [[
                $result['imported'],
                $result['skipped_no_email'],
                $result['skipped_duplicate'],
                $result['b2b'],
                $result['b2c'],
            ]]
        );

        if (! empty($result['errors'])) {
            $this->newLine();
            $this->warn(count($result['errors']) . ' row(s) had errors:');
            foreach ($result['errors'] as $err) {
                $this->line("  • {$err}");
            }
        }

        return self::SUCCESS;
    }
}
