<?php

namespace App\Console\Commands;

use App\Services\WixProductImportService;
use Illuminate\Console\Command;

class ImportWixProducts extends Command
{
    protected $signature = 'import:wix-products
                            {file : Absolute path to the CSV file}
                            {--segment= : Price segment to write: b2b or b2c. Omit for single-file import.}';

    protected $description = 'Import tyre products from a CSV export';

    public function handle(WixProductImportService $importer): int
    {
        set_time_limit(600);
        ini_set('memory_limit', '512M');

        $filePath = $this->argument('file');
        $segment  = $this->option('segment');

        if ($segment !== null && ! in_array($segment, ['b2b', 'b2c'], true)) {
            $this->error("Invalid --segment value '{$segment}'. Must be 'b2b' or 'b2c'.");
            return self::FAILURE;
        }

        if (! file_exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return self::FAILURE;
        }

        if ($segment) {
            $this->info("Segment: {$segment} — price column → price_{$segment}");
        }

        $result = $importer->import($filePath, $segment);

        if (! empty($result['errors'])) {
            foreach ($result['errors'] as $err) {
                $row = $err['row'] ? "Row {$err['row']}: " : '';
                $this->warn($row . $err['message']);
            }
        }

        $this->table(
            ['Imported (new)', 'Updated (existing)', 'Skipped', 'Images downloaded'],
            [[$result['imported'], $result['updated'], $result['skipped'], $result['images']]]
        );

        return self::SUCCESS;
    }
}
