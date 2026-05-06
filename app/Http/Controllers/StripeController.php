<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrderDetail;
use App\Models\PurchaseOrderHeader;
use App\Models\PurchaseOrderHeaderLog;
use App\Models\Product;
use App\Models\ShoppingCart;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Stripe;
use Stripe\Checkout\Session;
use Stripe\Webhook;

class StripeController extends Controller
{
    public function __construct()
    {
        Stripe::setApiKey(env('STRIPE_SECRET_KEY'));
    }

    public function createCheckoutSession(Request $request)
    {
        try {
            $amount  = (int) $request->input('amount');   // cents
            $orderId = $request->input('orderId');
            $email   = $request->input('email');
            $name    = $request->input('name');

            $session = Session::create([
                'ui_mode'    => 'embedded_page',
                'mode'       => 'payment',
                'line_items' => [[
                    'quantity'   => 1,
                    'price_data' => [
                        'currency'     => 'usd',
                        'unit_amount'  => $amount,
                        'product_data' => [
                            'name'        => 'Toggolac Order #' . $orderId,
                            'description' => 'Miami → Your Door. Personal shopper service.',
                        ],
                    ],
                ]],
                'customer_email'  => $email,
                'return_url'      => env('APP_FRONTEND_URL', 'https://toggolac.com')
                                     . '/account/orders?payment=success&session_id={CHECKOUT_SESSION_ID}',
                'metadata' => [
                    'order_id'      => $orderId,
                    'customer_name' => $name,
                ],
            ]);

            return response()->json([
                'client_secret' => $session->client_secret,
            ]);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Stripe createCheckoutSession error: ' . $e->getMessage());
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function sessionStatus(Request $request)
    {
        try {
            $session = Session::retrieve($request->query('session_id'));

            return response()->json([
                'status'         => $session->status,
                'customer_email' => $session->customer_details?->email ?? '',
            ]);

        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/stripe/webhook
     * Stripe sends checkout.session.completed when a US customer pays.
     */
    public function webhook(Request $request)
    {
        $secret  = env('STRIPE_WEBHOOK_SECRET');
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');

        try {
            $event = $secret
                ? Webhook::constructEvent($payload, $sigHeader, $secret)
                : json_decode($payload);
        } catch (\Exception $e) {
            Log::error('Stripe webhook signature failed: ' . $e->getMessage());
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        if ($event->type !== 'checkout.session.completed') {
            return response()->json(['received' => true]);
        }

        $session = $event->data->object;
        $orderId = $session->metadata->order_id ?? null;

        if (!$orderId) {
            Log::warning('Stripe webhook: no order_id in metadata');
            return response()->json(['received' => true]);
        }

        try {
            $order = PurchaseOrderHeader::find($orderId);
            if (!$order) {
                Log::warning("Stripe webhook: order #{$orderId} not found");
                return response()->json(['received' => true]);
            }

            // Idempotency — skip if already marked paid
            $alreadyPaid = PurchaseOrderHeaderLog::where('purchase_order_id', $orderId)
                ->where('status_id', 5)->exists();
            if ($alreadyPaid) {
                return response()->json(['received' => true]);
            }

            // Mark order as paid
            PurchaseOrderHeaderLog::create([
                'purchase_order_id'  => $orderId,
                'previous_status_id' => $order->purchase_status_id,
                'status_id'          => 5,
                'description'        => 'Stripe checkout.session.completed',
            ]);
            $order->update(['purchase_status_id' => 5]);

            // Soft-delete purchased products from catalog
            $details = PurchaseOrderDetail::where('purchase_order_header_id', $orderId)->get();
            foreach ($details as $detail) {
                Product::where('id', $detail->product_id)->delete();
            }

            // Clear shopping cart
            ShoppingCart::where('user_id', $order->client_id)->delete();

            // Send confirmation email + admin notification + fulfillment jobs
            $dlocalCtrl = new DlocalPaymentController();

            $orderCtrl = new PurchaseOrderController();
            $orderReq  = new \Illuminate\Http\Request(['no_paginate' => true]);
            $orderInfo = $orderCtrl->showPurchaseOrder($orderReq, $orderId);

            $dlocalCtrl->sendEmailConfirm($orderId);
            $dlocalCtrl->createFulfillmentJobs($orderId, $orderInfo, 'stripe');

            // Telegram push
            $tgToken  = env('TELEGRAM_BOT_TOKEN');
            $tgChatId = env('TELEGRAM_ADMIN_CHAT_ID');
            if ($tgToken && $tgChatId) {
                $invoiceNumber = $orderInfo['invoice_number'] ?? "ORD-{$orderId}";
                $customerName  = trim(($orderInfo['client']['name'] ?? '') . ' ' . ($orderInfo['client']['last_name'] ?? ''));
                $total         = $orderInfo['total_product']['formatted'] ?? '';
                $text = "🛒 *Nueva compra confirmada (Stripe)*\n"
                      . "📋 Orden: {$invoiceNumber}\n"
                      . "👤 Cliente: {$customerName}\n"
                      . "💰 Total: {$total}\n"
                      . "🔗 [Ver orden](https://adm.toggolac.com/panel/compras/{$orderId})";

                (new \GuzzleHttp\Client())->post(
                    "https://api.telegram.org/bot{$tgToken}/sendMessage",
                    ['json' => ['chat_id' => $tgChatId, 'text' => $text, 'parse_mode' => 'Markdown']]
                );
            }

            Log::info("Stripe payment confirmed for order #{$orderId}");

        } catch (\Exception $e) {
            Log::error("Stripe webhook processing failed for order #{$orderId}: " . $e->getMessage());
        }

        return response()->json(['received' => true]);
    }
}
