<?php

namespace App\Console\Commands;

use App\Services\WixOrderImportService;
use Illuminate\Console\Command;

class ImportWixOrders extends Command
{
    protected $signature = 'import:wix-orders {file : Path to the Wix orders CSV export file}';

    protected $description = 'Import orders from a Wix CSV export';

    public function handle(WixOrderImportService $service): int
    {
        $filePath = $this->argument('file');

        try {
            $this->info('Importing orders from: ' . $filePath);
            $result = $service->import($filePath);
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Import complete.');
        $this->table(
            ['Imported (new)', 'Updated (existing)', 'Skipped'],
            [[$result['imported'], $result['updated'], $result['skipped']]]
        );

        foreach ($result['errors'] as $err) {
            $this->warn($err);
        }

        return self::SUCCESS;
    }
}
