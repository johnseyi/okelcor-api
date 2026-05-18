<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\OrderLog;
use App\Models\TradeDocument;
use App\Services\TradeDocumentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CorrectOrderDeliveryFee extends Command
{
    protected $signature = 'orders:correct-delivery-fee
                            {ref          : Order reference (e.g. OKL-548XDW)}
                            {amount       : Correct delivery fee in euros (e.g. 2500.00)}
                            {--supersede-proforma : Also supersede the existing issued proforma}
                            {--reason=    : Reason for the correction (required unless --dry-run)}
                            {--dry-run    : Preview changes without writing anything}';

    protected $description = 'Safely correct an order delivery fee and optionally supersede the existing proforma so a new one can be generated.';

    public function __construct(private TradeDocumentService $docService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $ref       = $this->argument('ref');
        $amount    = (float) $this->argument('amount');
        $isDryRun  = (bool) $this->option('dry-run');
        $reason    = (string) $this->option('reason');
        $supersedeProforma = (bool) $this->option('supersede-proforma');

        if (! $isDryRun && empty($reason)) {
            $this->error('--reason is required unless --dry-run is passed.');
            return self::FAILURE;
        }

        // ── A. Find order ────────────────────────────────────────────────────
        $order = Order::with('items')->where('ref', $ref)->first();

        if (! $order) {
            $this->error("Order '{$ref}' not found.");
            return self::FAILURE;
        }

        $oldDeliveryCost = (float) $order->delivery_cost;
        $oldTotal        = (float) $order->total;
        $newDeliveryCost = $amount;
        $newTotal        = round($oldTotal - $oldDeliveryCost + $newDeliveryCost, 2);

        $this->line('');
        $this->line('┌─ Order ─────────────────────────────────────────────────┐');
        $this->line("│  Ref        : {$order->ref}");
        $this->line("│  Customer   : {$order->customer_email}");
        $this->line("│  Old fee    : €" . number_format($oldDeliveryCost, 2));
        $this->line("│  New fee    : €" . number_format($newDeliveryCost, 2));
        $this->line("│  Old total  : €" . number_format($oldTotal, 2));
        $this->line("│  New total  : €" . number_format($newTotal, 2));
        $this->line('└─────────────────────────────────────────────────────────┘');

        // ── B. Find existing issued proforma ─────────────────────────────────
        $proforma = TradeDocument::where('order_id', $order->id)
            ->where('type', 'proforma')
            ->where('status', 'issued')
            ->first();

        if ($proforma) {
            $this->line('');
            $this->line("Existing proforma : {$proforma->number}  [status: {$proforma->status}]");
        } else {
            $this->line('');
            $this->line('Existing proforma : none found with status=issued');
        }

        if ($supersedeProforma && ! $proforma) {
            $this->warn('  --supersede-proforma passed but no issued proforma exists — skipping supersede step.');
            $supersedeProforma = false;
        }

        // ── Dry-run exit ─────────────────────────────────────────────────────
        if ($isDryRun) {
            $this->line('');
            $this->warn('[DRY RUN] No changes written. Re-run without --dry-run to apply.');
            if ($supersedeProforma && $proforma) {
                $this->line("  Would supersede : {$proforma->number}");
            }
            $this->line("  Would update delivery_cost : {$oldDeliveryCost} → {$newDeliveryCost}");
            $this->line("  Would update total         : {$oldTotal} → {$newTotal}");
            if ($supersedeProforma) {
                $this->line('  Would generate  : new proforma after supersede');
            }
            return self::SUCCESS;
        }

        // ── C. Mark proforma superseded ──────────────────────────────────────
        if ($supersedeProforma && $proforma) {
            $proforma->update([
                'status'           => 'superseded',
                'superseded_at'    => now(),
                'superseded_by_id' => null, // CLI — no admin user
                'supersede_reason' => $reason,
            ]);

            try {
                OrderLog::create([
                    'order_id'   => $order->id,
                    'order_ref'  => $order->ref,
                    'action'     => 'document_superseded',
                    'old_value'  => $proforma->number,
                    'new_value'  => 'superseded',
                    'notes'      => "CLI supersede. Reason: {$reason}",
                    'ip_address' => '127.0.0.1',
                    'created_at' => now(),
                ]);
            } catch (\Throwable $e) {
                Log::warning('OrderLog write failed (CLI supersede)', ['error' => $e->getMessage()]);
            }

            $this->info("  ✓ Superseded : {$proforma->number}");
        }

        // ── D. Update delivery fee ────────────────────────────────────────────
        $order->update([
            'delivery_cost' => $newDeliveryCost,
            'total'         => $newTotal,
        ]);

        try {
            OrderLog::create([
                'order_id'   => $order->id,
                'order_ref'  => $order->ref,
                'action'     => 'financial_corrected',
                'old_value'  => "delivery_cost={$oldDeliveryCost}, total={$oldTotal}",
                'new_value'  => "delivery_cost={$newDeliveryCost}, total={$newTotal}",
                'notes'      => "CLI financial correction. Reason: {$reason}",
                'ip_address' => '127.0.0.1',
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('OrderLog write failed (CLI financial correction)', ['error' => $e->getMessage()]);
        }

        $this->info("  ✓ Updated delivery_cost : €" . number_format($oldDeliveryCost, 2)
            . ' → €' . number_format($newDeliveryCost, 2));
        $this->info("  ✓ Updated total         : €" . number_format($oldTotal, 2)
            . ' → €' . number_format($newTotal, 2));

        // ── E. Generate new proforma ──────────────────────────────────────────
        $newProforma = null;

        if ($supersedeProforma) {
            $order->refresh()->load('items');

            try {
                $newProforma = $this->docService->generateProformaForOrder($order->fresh(['items']));

                try {
                    OrderLog::create([
                        'order_id'   => $order->id,
                        'order_ref'  => $order->ref,
                        'action'     => 'document_generated',
                        'new_value'  => $newProforma->number,
                        'notes'      => "CLI generated corrected proforma after superseding {$proforma->number}.",
                        'ip_address' => '127.0.0.1',
                        'created_at' => now(),
                    ]);
                } catch (\Throwable $e) {
                    Log::warning('OrderLog write failed (CLI proforma generation)', ['error' => $e->getMessage()]);
                }

                $this->info("  ✓ Generated  : {$newProforma->number}");
            } catch (\Throwable $e) {
                $this->error("  ✗ Proforma generation failed: {$e->getMessage()}");
            }
        }

        // ── G. Summary ────────────────────────────────────────────────────────
        $this->line('');
        $this->line('════════════════════════════════════════════════════════════');
        $this->info('DONE');
        $this->line("  Order ref        : {$order->ref}");
        $this->line("  Old delivery fee : €" . number_format($oldDeliveryCost, 2));
        $this->line("  New delivery fee : €" . number_format($newDeliveryCost, 2));
        $this->line("  Old total        : €" . number_format($oldTotal, 2));
        $this->line("  New total        : €" . number_format($newTotal, 2));
        if ($proforma && $supersedeProforma) {
            $this->line("  Old proforma     : {$proforma->number}  [now: superseded]");
        }
        if ($newProforma) {
            $this->line("  New proforma     : {$newProforma->number}  [status: issued]");
        }
        $this->line('════════════════════════════════════════════════════════════');

        return self::SUCCESS;
    }
}
