<?php

namespace App\Console\Commands;

use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\ReceiptController;
use App\Models\OrderReceipt;
use App\Models\PurchaseOrderHeader;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * DEV / QA ONLY — generates a fake OrderReceipt for any existing order.
 * Lets you test PDF generation + the download endpoint without going
 * through a real dLocal payment webhook.
 *
 * Usage:
 *   php artisan receipt:fake {order_id}            — creates record + PDF
 *   php artisan receipt:fake {order_id} --reset    — deletes existing record first
 *   php artisan receipt:fake {order_id} --open     — prints the PDF path
 */
class FakeReceipt extends Command
{
    protected $signature   = 'receipt:fake {order_id} {--reset} {--open}';
    protected $description = '[DEV] Generate a fake receipt record for any order (skips payment webhook)';

    public function handle(): int
    {
        $orderId = (int) $this->argument('order_id');

        // ── Verify order exists ──────────────────────────────────────────────
        $order = PurchaseOrderHeader::find($orderId);
        if (!$order) {
            $this->error("Order #{$orderId} not found.");
            return 1;
        }

        $this->info("Order #{$orderId} found — invoice: " . ($order->invoice_number ?? '(none yet)'));

        // ── Optional: wipe existing record so we can regenerate ──────────────
        if ($this->option('reset')) {
            $existing = OrderReceipt::where('purchase_order_id', $orderId)->first();
            if ($existing) {
                if ($existing->receipt_pdf_path) {
                    Storage::disk('local')->delete($existing->receipt_pdf_path);
                }
                $existing->delete();
                $this->warn("  ↩  Existing receipt record deleted.");
            }
        }

        // ── Skip if receipt already exists (unless --reset was used) ─────────
        if (OrderReceipt::where('purchase_order_id', $orderId)->exists()) {
            $this->warn("  ⚠  Receipt already exists for order #{$orderId}. Use --reset to regenerate.");
            $this->_showResult($orderId);
            return 0;
        }

        // ── Fetch order info through the existing controller ─────────────────
        $this->line("  ⏳ Fetching order details...");
        $orderCtrl = new PurchaseOrderController();
        $req       = new Request(['no_paginate' => true]);
        $orderInfo = $orderCtrl->showPurchaseOrder($req, $orderId);

        if (!is_array($orderInfo) || empty($orderInfo)) {
            $this->error("  Could not load order info for #{$orderId}.");
            return 1;
        }

        // ── Build fake-but-realistic payment data ────────────────────────────
        $fakeTransactionId  = 'TEST-' . strtoupper(substr(md5($orderId . time()), 0, 12));
        $fakeAuthCode       = strtoupper(substr(md5('auth' . $orderId), 0, 8));
        $fakeTotal          = (float) ($orderInfo['total_product']['amount'] ?? 99.99);
        $fakeCurrency       = 'USD';
        $fakeLast4          = '4242';
        $fakeBrand          = 'VISA';

        $this->line("  💳 Fake payment data:");
        $this->line("     Transaction ID : {$fakeTransactionId}");
        $this->line("     Auth code      : {$fakeAuthCode}");
        $this->line("     Amount         : \${$fakeTotal} {$fakeCurrency}");
        $this->line("     Card           : {$fakeBrand} ···· {$fakeLast4}");

        // ── Create the receipt record + generate PDF ─────────────────────────
        $this->line("  ⏳ Generating receipt record and PDF...");

        $receiptCtrl = new ReceiptController();
        $receipt = $receiptCtrl->createAndStore(
            orderInfo:            $orderInfo,
            paymentTransactionId: $fakeTransactionId,
            paymentAuthCode:      $fakeAuthCode,
            paymentMethodType:    'CARD',
            paymentCardBrand:     $fakeBrand,
            paymentLast4:         $fakeLast4,
            paymentAmount:        $fakeTotal,
            paymentCurrency:      $fakeCurrency,
            paymentApprovedAt:    now()->toIso8601String(),
            customerIp:           '127.0.0.1',
            userAgent:            'FakeReceipt/Artisan',
        );

        if (!$receipt) {
            $this->error("  ✗ Receipt creation failed. Check logs.");
            return 1;
        }

        $this->info("  ✅ Receipt record created (ID: {$receipt->id})");
        $this->_showResult($orderId);

        return 0;
    }

    private function _showResult(int $orderId): void
    {
        $r = OrderReceipt::where('purchase_order_id', $orderId)->first();
        if (!$r) return;

        $this->newLine();
        $this->table(
            ['Field', 'Value'],
            [
                ['receipt_id',          $r->id],
                ['invoice_number',      $r->invoice_number ?? '—'],
                ['customer',            $r->customer_name . ' <' . $r->customer_email . '>'],
                ['payment_tx_id',       $r->payment_transaction_id],
                ['auth_code',           $r->payment_authorization_code],
                ['card',                ($r->payment_card_brand ?? '?') . ' ···· ' . ($r->payment_last_4 ?? '????')],
                ['amount',              '$' . number_format((float) $r->payment_amount, 2) . ' ' . $r->payment_currency],
                ['pdf_path',            $r->receipt_pdf_path ?? '(not generated)'],
                ['pdf_generated_at',    $r->pdf_generated_at?->toDateTimeString() ?? '—'],
            ]
        );

        if ($r->receipt_pdf_path && Storage::disk('local')->exists($r->receipt_pdf_path)) {
            $fullPath = Storage::disk('local')->path($r->receipt_pdf_path);
            $sizeKb   = round(filesize($fullPath) / 1024, 1);
            $this->newLine();
            $this->info("  📄 PDF ready ({$sizeKb} KB):");
            $this->line("     {$fullPath}");
            $this->newLine();
            $this->comment("  ── Test the download endpoint ──────────────────────────────────");
            $this->line("  1. Get a token:");
            $this->line("     curl -s -X POST " . env('APP_URL') . "/api/auth/login \\");
            $this->line("       -H 'Content-Type: application/json' \\");
            $this->line("       -d '{\"email\":\"<user_email>\",\"password\":\"<password>\"}' | jq '.authorization.token'");
            $this->newLine();
            $this->line("  2. Download the PDF:");
            $this->line("     curl -s -o /tmp/test-receipt.pdf \\");
            $this->line("       -H 'Authorization: Bearer <TOKEN>' \\");
            $this->line("       " . env('APP_URL') . "/api/purchase_order/receipt/{$orderId}");
            $this->newLine();
            $this->line("  3. Open it:");
            $this->line("     open /tmp/test-receipt.pdf");
            $this->newLine();
        } else {
            $this->warn("  ⚠  PDF not found on disk. Check storage/logs.");
        }
    }
}
