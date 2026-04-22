<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Stripe\Stripe;
use Stripe\Checkout\Session;

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
}
