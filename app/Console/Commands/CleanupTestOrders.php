<?php

namespace App\Console\Commands;

use App\Models\EuDeclaration;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\TradeDocument;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CleanupTestOrders extends Command
{
    protected $signature = 'orders:cleanup-test-data
                            {--dry-run : Show what would be deleted without making changes}
                            {--force   : Skip confirmation prompt}';

    protected $description = 'One-time removal of test orders and all related data (items, invoices, declarations, trade docs, logs)';

    private const TEST_REFS = [
        'OKL-14CVV2C',
        'OKL-1303SMU',
        'OKL-13180T5',
        'OKL-YOTFQM',
        'OKL-XW6LHC',
        'OKL-1FES6QA',
        'OKL-1A8IOAI',
        'OKL-VDUWAD',
        'OKL-1M84OQ9',
        'OKL-1CDIP0E',
    ];

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $this->newLine();
        $this->line('=== Test Order Cleanup ===');

        if ($dryRun) {
            $this->warn('DRY RUN — no changes will be made.');
        }

        // ---------------------------------------------------------------
        // Load orders
        // ---------------------------------------------------------------
        $orders = Order::with(['euDeclaration', 'tradeDocuments'])
            ->whereIn('ref', self::TEST_REFS)
            ->get()
            ->keyBy('ref');

        $foundRefs   = $orders->keys()->all();
        $missingRefs = array_values(array_diff(self::TEST_REFS, $foundRefs));

        if ($missingRefs) {
            $this->warn('Not found (will be skipped): ' . implode(', ', $missingRefs));
        }

        if ($orders->isEmpty()) {
            $this->info('No matching orders found. Nothing to delete.');
            return self::SUCCESS;
        }

        $orderIds  = $orders->pluck('id')->all();
        $orderRefs = $orders->pluck('ref')->all();

        // ---------------------------------------------------------------
        // Collect counts and file paths BEFORE any deletion
        // ---------------------------------------------------------------
        $counts = $this->collectCounts($orderIds, $orderRefs);
        $files  = $this->collectFilePaths($orders, $orderRefs);

        $this->printPreview($orders, $counts, $files);

        if ($dryRun) {
            $this->newLine();
            $this->warn('Dry run complete. Run without --dry-run to apply.');
            return self::SUCCESS;
        }

        // ---------------------------------------------------------------
        // Confirm
        // ---------------------------------------------------------------
        if (! $this->option('force')) {
            if (! $this->confirm('Permanently delete these records? This cannot be undone.')) {
                $this->info('Aborted — no changes made.');
                return self::SUCCESS;
            }
        }

        // ---------------------------------------------------------------
        // DB deletion inside a transaction
        // ---------------------------------------------------------------
        DB::transaction(function () use ($orderIds, $orderRefs) {

            // 1. Nullify quote_requests.order_id — preserve the quote, just unlink it
            DB::table('quote_requests')
                ->whereIn('order_id', $orderIds)
                ->update(['order_id' => null]);

            // 2. Order logs — nullOnDelete would leave them; we want them gone
            DB::table('order_logs')
                ->whereIn('order_id', $orderIds)
                ->delete();
            // Also catch any logs that were written after the order_id was nullified
            DB::table('order_logs')
                ->whereIn('order_ref', $orderRefs)
                ->delete();

            // 3. Shipment events
            DB::table('order_shipment_events')
                ->whereIn('order_id', $orderIds)
                ->delete();

            // 4. Order items
            DB::table('order_items')
                ->whereIn('order_id', $orderIds)
                ->delete();

            // 5. EU declarations — must precede invoice deletion because invoice_id FK
            //    is nullOnDelete; deleting invoices first would just nullify the FK
            //    but we want declarations fully gone
            EuDeclaration::whereIn('order_id', $orderIds)->delete();

            // 6. Trade documents
            TradeDocument::whereIn('order_id', $orderIds)->delete();

            // 7. Invoices — linked by order_ref (no FK to orders table)
            Invoice::whereIn('order_ref', $orderRefs)->delete();

            // 8. Orders — cascades would handle any remaining children,
            //    but we have already removed them explicitly above
            Order::whereIn('id', $orderIds)->delete();
        });

        // ---------------------------------------------------------------
        // File cleanup — AFTER DB transaction succeeds
        // Orphaned files are recoverable; missing DB records are not.
        // ---------------------------------------------------------------
        $deletedFiles = $this->deleteFiles($files);

        // ---------------------------------------------------------------
        // Summary
        // ---------------------------------------------------------------
        $this->printSummary($counts, $deletedFiles, $orderRefs);

        Log::info('orders:cleanup-test-data completed', [
            'refs'          => $orderRefs,
            'db_counts'     => $counts,
            'files_deleted' => $deletedFiles,
        ]);

        return self::SUCCESS;
    }

    // -----------------------------------------------------------------------
    // Data collection helpers
    // -----------------------------------------------------------------------

    private function collectCounts(array $orderIds, array $orderRefs): array
    {
        return [
            'orders'          => count($orderIds),
            'order_items'     => DB::table('order_items')->whereIn('order_id', $orderIds)->count(),
            'order_logs'      => DB::table('order_logs')->whereIn('order_ref', $orderRefs)->count(),
            'shipment_events' => DB::table('order_shipment_events')->whereIn('order_id', $orderIds)->count(),
            'eu_declarations' => EuDeclaration::whereIn('order_id', $orderIds)->count(),
            'trade_documents' => TradeDocument::whereIn('order_id', $orderIds)->count(),
            'invoices'        => Invoice::whereIn('order_ref', $orderRefs)->count(),
            'quotes_unlinked' => DB::table('quote_requests')->whereIn('order_id', $orderIds)->count(),
        ];
    }

    /**
     * Collect file paths from DB before records are deleted.
     * Returns arrays grouped by disk so we can delete them after the transaction.
     */
    private function collectFilePaths(Collection $orders, array $orderRefs): array
    {
        $public = [];
        $local  = [];

        // Invoice PDFs — public disk
        foreach (Invoice::whereIn('order_ref', $orderRefs)->get() as $invoice) {
            if ($invoice->pdf_url) {
                $public[] = $invoice->pdf_url;
            }
        }

        // EU declaration PDFs and signature images — local (private) disk
        foreach ($orders as $order) {
            $decl = $order->euDeclaration;
            if (! $decl) {
                continue;
            }
            if ($decl->pdf_path) {
                $local[] = $decl->pdf_path;
            }
            if ($decl->signature_path) {
                $local[] = $decl->signature_path;
            }
        }

        // Trade document PDFs and uploaded files — local (private) disk
        foreach ($orders as $order) {
            foreach ($order->tradeDocuments as $doc) {
                $pdf  = $doc->getRawOriginal('pdf_path');
                $file = $doc->getRawOriginal('file_path');
                if ($pdf) {
                    $local[] = $pdf;
                }
                if ($file) {
                    $local[] = $file;
                }
            }
        }

        return ['public' => $public, 'local' => $local];
    }

    // -----------------------------------------------------------------------
    // File deletion
    // -----------------------------------------------------------------------

    private function deleteFiles(array $files): array
    {
        $deleted = ['public' => 0, 'local' => 0, 'missing' => 0];

        foreach ($files['public'] as $path) {
            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
                $deleted['public']++;
            } else {
                $deleted['missing']++;
            }
        }

        foreach ($files['local'] as $path) {
            if (Storage::disk('local')->exists($path)) {
                Storage::disk('local')->delete($path);
                $deleted['local']++;
            } else {
                $deleted['missing']++;
            }
        }

        return $deleted;
    }

    // -----------------------------------------------------------------------
    // Output helpers
    // -----------------------------------------------------------------------

    private function printPreview(Collection $orders, array $counts, array $files): void
    {
        $this->newLine();
        $this->line('<fg=cyan>Orders to be deleted:</>');

        $rows = $orders->map(fn ($o) => [
            $o->ref,
            $o->customer_email,
            $o->status . ' / ' . $o->payment_status,
            '€' . number_format((float) $o->total, 2),
        ])->values()->all();

        $this->table(['Ref', 'Customer email', 'Status / Payment', 'Total'], $rows);

        $this->newLine();
        $this->line('<fg=cyan>Records that will be deleted:</>');
        $this->table(['Table / Action', 'Count'], [
            ['orders',                        $counts['orders']],
            ['order_items',                   $counts['order_items']],
            ['order_logs',                    $counts['order_logs']],
            ['order_shipment_events',         $counts['shipment_events']],
            ['eu_declarations',               $counts['eu_declarations']],
            ['trade_documents',               $counts['trade_documents']],
            ['invoices',                      $counts['invoices']],
            ['quote_requests  (unlinked)',     $counts['quotes_unlinked']],
        ]);

        $totalFiles = count($files['public']) + count($files['local']);
        $this->line("<fg=cyan>Files to delete:</> {$totalFiles} "
            . "(public: " . count($files['public']) . ", private: " . count($files['local']) . ")");
    }

    private function printSummary(array $counts, array $deletedFiles, array $refs): void
    {
        $this->newLine();
        $this->info('Cleanup complete.');
        $this->newLine();

        $this->line('<fg=green>Database records deleted:</>');
        $this->table(['Table / Action', 'Count'], [
            ['orders',                        $counts['orders']],
            ['order_items',                   $counts['order_items']],
            ['order_logs',                    $counts['order_logs']],
            ['order_shipment_events',         $counts['shipment_events']],
            ['eu_declarations',               $counts['eu_declarations']],
            ['trade_documents',               $counts['trade_documents']],
            ['invoices',                      $counts['invoices']],
            ['quote_requests  (unlinked)',     $counts['quotes_unlinked']],
        ]);

        $this->newLine();
        $this->line('<fg=green>Files deleted:</>');
        $this->table(['Disk', 'Deleted'], [
            ['public (invoice PDFs)',              $deletedFiles['public']],
            ['local  (declarations / signatures)', $deletedFiles['local']],
            ['not found on disk (skipped)',        $deletedFiles['missing']],
        ]);

        $this->newLine();
        $this->line('Refs processed: ' . implode(', ', $refs));
        $this->line('Customer accounts were NOT deleted.');
        $this->newLine();
    }
}
