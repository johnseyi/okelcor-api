<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DeleteSpecificTestOrders extends Command
{
    protected $signature = 'orders:delete-specific
                            {--dry-run : List what would be deleted without making changes}';

    protected $description = 'Delete 9 specific test orders by ref (hard-coded list)';

    private const TARGET_REFS = [
        'OKL-68JN8C',
        'OKL-173X6SP',
        'OKL-Y5CCST',
        'OKL-WU91X1',
        'OKL-VUDVYH',
        'OKL-T99DJY',
        'OKL-1ZPORV0',
        'OKL-1ZPKSAT',
        'OKL-1ZPFAK8',
    ];

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $orders   = Order::with('items')->whereIn('ref', self::TARGET_REFS)->get();
        $invoices = Invoice::whereIn('order_ref', self::TARGET_REFS)->get();

        $foundRefs = $orders->pluck('ref')->all();
        $notFound  = array_diff(self::TARGET_REFS, $foundRefs);

        // ── Report ──────────────────────────────────────────────────────────

        $this->line('');
        $this->line('=== Matched Orders ===');

        if ($orders->isEmpty()) {
            $this->warn('No matching orders found.');
        } else {
            $this->table(
                ['Ref', 'Status', 'Payment', 'Total', 'Items', 'Created'],
                $orders->map(fn ($o) => [
                    $o->ref,
                    $o->status,
                    $o->payment_status,
                    '€' . number_format((float) $o->total, 2),
                    $o->items->count(),
                    $o->created_at?->format('Y-m-d H:i:s'),
                ])->toArray()
            );
        }

        $this->line('');
        $this->line('=== Related Invoices ===');

        if ($invoices->isEmpty()) {
            $this->info('None found.');
        } else {
            $this->table(
                ['Invoice #', 'Order Ref', 'Status', 'Amount', 'PDF path'],
                $invoices->map(fn ($i) => [
                    $i->invoice_number,
                    $i->order_ref ?? '—',
                    $i->status,
                    '€' . number_format((float) $i->amount, 2),
                    $i->pdf_url ?? '(none)',
                ])->toArray()
            );
        }

        if ($notFound) {
            $this->line('');
            $this->warn('Not found in DB (will skip): ' . implode(', ', $notFound));
        }

        $this->line('');
        $this->warn(sprintf(
            'Will delete: %d order(s), %d invoice(s)',
            $orders->count(),
            $invoices->count()
        ));

        if ($dryRun) {
            $this->line('');
            $this->info('[DRY RUN] No changes made. Re-run without --dry-run to delete.');
            return self::SUCCESS;
        }

        $this->line('');
        if (! $this->confirm('Permanently delete these records? This cannot be undone.')) {
            $this->info('Aborted. No changes made.');
            return self::SUCCESS;
        }

        // ── Delete DB records in transaction ────────────────────────────────

        $deletedOrders   = [];
        $deletedInvoices = [];
        $pdfPathsToDelete = [];

        DB::transaction(function () use ($orders, $invoices, &$deletedOrders, &$deletedInvoices, &$pdfPathsToDelete) {
            foreach ($invoices as $invoice) {
                if ($invoice->pdf_url) {
                    $pdfPathsToDelete[] = $invoice->pdf_url;
                }
                $invoice->delete();
                $deletedInvoices[] = $invoice->invoice_number;
            }

            foreach ($orders as $order) {
                $itemCount = $order->items()->delete();
                // order_logs: nullOnDelete in schema — DB sets order_id = NULL automatically
                $order->delete();
                $deletedOrders[] = ['ref' => $order->ref, 'items' => $itemCount];
            }
        });

        // ── Delete PDF files (after DB commit) ──────────────────────────────

        $deletedPdfs = [];
        $skippedPdfs = [];

        foreach ($pdfPathsToDelete as $path) {
            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
                $deletedPdfs[] = $path;
            } else {
                $skippedPdfs[] = $path . ' (not found on disk)';
            }
        }

        // ── Summary ─────────────────────────────────────────────────────────

        $this->line('');
        $this->line('=== Deleted Orders ===');
        foreach ($deletedOrders as $d) {
            $this->line("  {$d['ref']} — {$d['items']} item(s) removed");
        }

        if ($deletedInvoices) {
            $this->line('');
            $this->line('=== Deleted Invoices ===');
            foreach ($deletedInvoices as $inv) {
                $this->line("  {$inv}");
            }
        }

        if ($deletedPdfs) {
            $this->line('');
            $this->line('=== Deleted PDF files ===');
            foreach ($deletedPdfs as $pdf) {
                $this->line("  {$pdf}");
            }
        }

        if ($skippedPdfs) {
            $this->line('');
            $this->warn('=== Skipped PDF files (not found on disk) ===');
            foreach ($skippedPdfs as $pdf) {
                $this->line("  {$pdf}");
            }
        }

        if ($notFound) {
            $this->line('');
            $this->warn('=== Order refs not found in DB (skipped) ===');
            foreach ($notFound as $ref) {
                $this->line("  {$ref}");
            }
        }

        Log::info('Specific test orders deleted', [
            'refs'     => array_column($deletedOrders, 'ref'),
            'invoices' => $deletedInvoices,
            'pdfs'     => $deletedPdfs,
        ]);

        $this->line('');
        $this->info(sprintf(
            'Done. %d order(s), %d invoice(s), %d PDF(s) deleted.',
            count($deletedOrders),
            count($deletedInvoices),
            count($deletedPdfs)
        ));

        return self::SUCCESS;
    }
}
