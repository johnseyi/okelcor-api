<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GenerateMissingInvoicePdfs extends Command
{
    protected $signature = 'invoices:generate-missing-pdfs
                            {--dry-run  : List affected invoices without generating files}
                            {--invoice= : Target a single invoice number (e.g. INV-2026-0003)}';

    protected $description = 'Generate PDF files for invoices that have pdf_url=null';

    public function handle(): int
    {
        $dryRun        = $this->option('dry-run');
        $targetInvoice = $this->option('invoice');

        $query = Invoice::whereNull('pdf_url');

        if ($targetInvoice) {
            $query->where('invoice_number', $targetInvoice);
        }

        $invoices = $query->orderBy('id')->get();

        if ($invoices->isEmpty()) {
            $this->info('No invoices with missing PDFs found.');
            return self::SUCCESS;
        }

        $this->line('');
        $this->line(
            $dryRun
                ? 'Invoices with missing PDFs (dry-run — no files written):'
                : 'Generating PDFs for invoices with pdf_url=null:'
        );
        $this->line('');

        $this->table(
            ['Invoice number', 'Order ref', 'Amount', 'Issued at'],
            $invoices->map(fn ($inv) => [
                $inv->invoice_number,
                $inv->order_ref ?? '—',
                '€' . number_format((float) $inv->amount, 2),
                $inv->issued_at?->format('Y-m-d'),
            ])->toArray()
        );

        $this->line('');
        $this->warn("Total: {$invoices->count()} invoice(s)");
        $this->line('');

        if ($dryRun) {
            $this->info('[DRY RUN] No files written. Re-run without --dry-run to generate PDFs.');
            return self::SUCCESS;
        }

        $generated = 0;
        $failed    = 0;

        foreach ($invoices as $invoice) {
            $order = null;

            if ($invoice->order_ref) {
                $order = Order::with('items')->where('ref', $invoice->order_ref)->first();
            }

            if (! $order) {
                $this->warn("  Skipped {$invoice->invoice_number}: order '{$invoice->order_ref}' not found.");
                Log::warning('invoices:generate-missing-pdfs — order not found', [
                    'invoice'   => $invoice->invoice_number,
                    'order_ref' => $invoice->order_ref,
                ]);
                $failed++;
                continue;
            }

            $this->line("  Processing {$invoice->invoice_number} / {$order->ref} ...");

            Log::info('Invoice PDF generation started', [
                'invoice'   => $invoice->invoice_number,
                'order_ref' => $order->ref,
            ]);

            try {
                $pdf  = Pdf::loadView('pdf.invoice', [
                    'invoice' => $invoice,
                    'order'   => $order,
                ]);

                $path = "invoices/{$invoice->invoice_number}.pdf";

                Storage::disk('public')->put($path, $pdf->output());

                $invoice->update(['pdf_url' => $path]);

                Log::info('Invoice PDF generated', [
                    'invoice' => $invoice->invoice_number,
                    'path'    => $path,
                ]);

                $this->info("  Generated: {$path}");
                $generated++;
            } catch (\Throwable $e) {
                Log::warning('Invoice PDF generation failed', [
                    'invoice' => $invoice->invoice_number,
                    'error'   => $e->getMessage(),
                ]);

                $this->error("  Failed: {$invoice->invoice_number} — {$e->getMessage()}");
                $failed++;
            }
        }

        $this->line('');
        $this->info("Done. Generated: {$generated} | Failed/skipped: {$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
