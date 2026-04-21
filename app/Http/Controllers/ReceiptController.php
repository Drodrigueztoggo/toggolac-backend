<?php

namespace App\Http\Controllers;

use App\Models\OrderReceipt;
use App\Models\PurchaseOrderHeader;
use App\Models\Transaction;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ReceiptController extends Controller
{
    /**
     * Serve (or generate on first request) the PDF receipt for an order.
     * Authenticated — customer can only download their own order.
     */
    public function download(Request $request, int $orderId)
    {
        try {
            $user = auth()->user();

            // Verify order belongs to this user
            $order = PurchaseOrderHeader::where('id', $orderId)
                ->where('client_id', $user->id)
                ->firstOrFail();

            // Look up or create the receipt record
            $receiptRecord = OrderReceipt::where('purchase_order_id', $orderId)->first();

            if (!$receiptRecord) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Receipt not available yet. Please try again after payment is confirmed.',
                ], 404);
            }

            // Generate PDF if it doesn't exist yet (or storage was cleared)
            if (!$receiptRecord->receipt_pdf_path || !Storage::disk('local')->exists($receiptRecord->receipt_pdf_path)) {
                $pdfPath = $this->_generateAndStore($receiptRecord, $order);
                if (!$pdfPath) {
                    return response()->json(['status' => 'error', 'message' => 'Could not generate receipt.'], 500);
                }
            }

            $pdfPath  = $receiptRecord->receipt_pdf_path;
            $fullPath = Storage::disk('local')->path($pdfPath);
            $filename = 'receipt-' . ($receiptRecord->invoice_number ?? $orderId) . '.pdf';

            return response()->download($fullPath, $filename, [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['status' => 'error', 'message' => 'Order not found.'], 404);
        } catch (\Exception $e) {
            Log::error('Receipt download error: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Could not download receipt.'], 500);
        }
    }

    /**
     * Called internally from DlocalPaymentController when payment is PAID.
     * Creates the OrderReceipt record and generates + stores the PDF.
     */
    public function createAndStore(
        array   $orderInfo,
        string  $paymentTransactionId,
        ?string $paymentAuthCode,
        ?string $paymentMethodType,
        ?string $paymentCardBrand,
        ?string $paymentLast4,
        float   $paymentAmount,
        string  $paymentCurrency,
        ?string $paymentApprovedAt,
        ?string $customerIp  = null,
        ?string $userAgent   = null,
    ): ?OrderReceipt {
        try {
            $orderId = $orderInfo['id'] ?? null;
            if (!$orderId) return null;

            // Idempotent — skip if receipt already created (webhook can fire twice)
            $existing = OrderReceipt::where('purchase_order_id', $orderId)->first();
            if ($existing) return $existing;

            $customerName  = trim(($orderInfo['client']['name'] ?? '') . ' ' . ($orderInfo['client']['last_name'] ?? ''));
            $customerEmail = $orderInfo['client']['email'] ?? '';

            $addressParts = array_filter([
                $orderInfo['destination_address']        ?? null,
                $orderInfo['destinationCity']['name']    ?? null,
                $orderInfo['destinationState']['name']   ?? null,
                $orderInfo['destinationCountry']['name'] ?? null,
            ]);
            $shippingAddress = implode(', ', $addressParts) ?: null;

            // Build order snapshot — frozen copy of everything at payment time
            $snapshot = [
                'order_id'          => $orderId,
                'invoice_number'    => $orderInfo['invoice_number']   ?? null,
                'order_token'       => $orderInfo['order_token']      ?? null,
                'purchase_details'  => $orderInfo['purchase_order_details'] ?? [],
                'taxes'             => $orderInfo['taxes']            ?? [],
                'total'             => $orderInfo['total_product']    ?? null,
                'destination'       => [
                    'address' => $shippingAddress,
                    'country' => $orderInfo['destinationCountry']['name'] ?? null,
                    'state'   => $orderInfo['destinationState']['name']   ?? null,
                    'city'    => $orderInfo['destinationCity']['name']    ?? null,
                ],
                'client' => [
                    'name'  => $customerName,
                    'email' => $customerEmail,
                ],
                'snapshot_at' => now()->toIso8601String(),
            ];

            $receipt = OrderReceipt::create([
                'purchase_order_id'          => $orderId,
                'invoice_number'             => $orderInfo['invoice_number'] ?? null,
                'customer_name'              => $customerName,
                'customer_email'             => $customerEmail,
                'shipping_address'           => $shippingAddress,
                'payment_transaction_id'     => $paymentTransactionId,
                'payment_authorization_code' => $paymentAuthCode,
                'payment_method_type'        => $paymentMethodType,
                'payment_card_brand'         => $paymentCardBrand,
                'payment_last_4'             => $paymentLast4,
                'payment_amount'             => $paymentAmount,
                'payment_currency'           => $paymentCurrency,
                'payment_approved_at'        => $paymentApprovedAt ? Carbon::parse($paymentApprovedAt) : now(),
                'customer_ip'                => $customerIp,
                'user_agent'                 => $userAgent,
                'order_snapshot'             => $snapshot,
            ]);

            // Generate PDF immediately and store it
            $order = PurchaseOrderHeader::find($orderId);
            if ($order) {
                $this->_generateAndStore($receipt, $order, $orderInfo);
            }

            return $receipt;

        } catch (\Exception $e) {
            Log::error('OrderReceipt creation error: ' . $e->getMessage());
            return null;
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    public function _generateAndStore(OrderReceipt $receipt, $order, ?array $preloadedInfo = null): ?string
    {
        try {
            // Fetch order info if not already supplied
            if ($preloadedInfo) {
                $orderInfo = $preloadedInfo;
            } else {
                $orderCtrl = new PurchaseOrderController();
                $req       = new \Illuminate\Http\Request(['no_paginate' => true]);
                $orderInfo = $orderCtrl->showPurchaseOrder($req, $order->id);
            }

            // Merge final sale acknowledgment fields directly from the Eloquent model
            // (ensures they are always present even if showPurchaseOrder doesn't return them)
            $orderInfo['final_sale_acknowledged']    = (bool) ($order->final_sale_acknowledged ?? false);
            $orderInfo['final_sale_acknowledged_at'] = $order->final_sale_acknowledged_at
                ? (string) $order->final_sale_acknowledged_at
                : null;

            // Determine language from destination country
            $countryName = strtolower($orderInfo['destinationCountry']['name'] ?? '');
            $language    = str_contains($countryName, 'colombia') ? 'es' : 'en';

            // Build bilingual tax labels (reuse same logic as email)
            $taxLabelMap = [
                'TOTAL PRODUCTOS' => ['en' => 'Product Total',    'es' => 'Total productos'],
                'COSTO SERVICIO'  => ['en' => 'Service Fee',      'es' => 'Costo de servicio'],
                'COSTO DE ENVÍO'  => ['en' => 'Shipping Cost',    'es' => 'Costo de envío'],
                'COSTO DE ENVIO'  => ['en' => 'Shipping Cost',    'es' => 'Costo de envío'],
                'TAXES EN USA'    => ['en' => 'USA Taxes (7%)',   'es' => 'Impuestos USA (7%)'],
                'IVA COLOMBIA'    => ['en' => 'Colombia VAT',     'es' => 'IVA Colombia'],
                'ARANCELES'       => ['en' => 'Customs Duties',   'es' => 'Aranceles'],
            ];

            $isUS              = !str_contains($countryName, 'colombia');
            $colombiaOnlyCodes = ['IVA', 'IVA_CO', 'ARANCELES', 'CUSTOMS', 'TARIFF'];
            $colombiaOnlyNames = ['COLOMBIA', 'IVA', 'ARANCELES', 'ARANCEL'];

            $taxes = collect($orderInfo['taxes']['taxesList'] ?? [])
                ->filter(function ($t) use ($isUS, $colombiaOnlyCodes, $colombiaOnlyNames) {
                    if (($t['amount'] ?? 0) <= 0) return false;
                    if (!$isUS) return true;
                    $code = strtoupper($t['code'] ?? '');
                    $name = strtoupper($t['name'] ?? '');
                    if (in_array($code, $colombiaOnlyCodes)) return false;
                    foreach ($colombiaOnlyNames as $kw) {
                        if (str_contains($name, $kw)) return false;
                    }
                    return true;
                })
                ->map(function ($t) use ($taxLabelMap, $language) {
                    $key   = strtoupper($t['name'] ?? '');
                    $label = $taxLabelMap[$key][$language] ?? $t['name'];
                    return array_merge($t, ['label' => $label]);
                })
                ->values()
                ->toArray();

            $pdf = Pdf::loadView('pdf.receipt', [
                'orderData'     => $orderInfo,
                'receiptRecord' => $receipt,
                'taxes'         => $taxes,
                'language'      => $language,
            ])->setPaper('a4', 'portrait');

            // Store in private storage — not publicly accessible
            $relativePath = 'receipts/' . $receipt->purchase_order_id . '_' . ($receipt->invoice_number ?? $receipt->id) . '.pdf';
            Storage::disk('local')->put($relativePath, $pdf->output());

            $receipt->update([
                'receipt_pdf_path' => $relativePath,
                'pdf_generated_at' => now(),
            ]);

            return $relativePath;

        } catch (\Exception $e) {
            Log::error('PDF generation error for order ' . $receipt->purchase_order_id . ': ' . $e->getMessage());
            return null;
        }
    }
}
