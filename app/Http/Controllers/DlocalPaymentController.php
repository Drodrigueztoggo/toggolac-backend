<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Mail\AdminNewOrderMail;
use App\Mail\AdminSmsOrderMail;
use App\Mail\PaymentConfirmOrderMail;
use App\Models\Commission;
use App\Models\Product;
use App\Models\PurchaseOrderDetail;
use App\Models\PurchaseOrderHeader;
use App\Models\PurchaseOrderHeaderLog;
use App\Models\ShoppingCart;
use App\Models\Transaction;
use App\Models\TransactionLog;
use GrahamCampbell\ResultType\Success;
use Illuminate\Http\Request;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Cknow\Money\Money as MoneyConvert;

class DlocalPaymentController extends Controller
{
    // ── SmartFields helpers ──────────────────────────────────────────────────

    /** Build Bearer auth headers for the dLocal Go SmartFields API. */
    private function dlocalHeaders(string $apiKey, string $secretKey = '', string $body = ''): array
    {
        $auth = $secretKey ? $apiKey . ':' . $secretKey : $apiKey;
        return [
            'Authorization' => 'Bearer ' . $auth,
            'Content-Type'  => 'application/json',
        ];
    }

    private function sfBaseUrl(): string
    {
        return config('app.env') === 'production'
            ? 'https://api.dlocalgo.com/v1'
            : 'https://api-sbx.dlocalgo.com/v1';
    }

    private function sfCredentials(): array
    {
        $isSandbox = config('app.env') !== 'production';
        return [
            'key'    => $isSandbox ? env('DLOCAL_API_KEY_TEST')    : env('DLOCAL_API_KEY'),
            'secret' => $isSandbox ? env('DLOCAL_SECRET_KEY_TEST') : env('DLOCAL_SECRET_KEY'),
        ];
    }

    // ── SmartFields: Step 1 — create payment & return checkout token ─────────

    public function createCheckout(Request $request)
    {
        try {
            $data_amount    = $request->amount;
            $data_currency  = $request->currency  ?? 'USD';
            $data_country   = $request->country   ?? 'CO';
            $data_order_id  = $request->orderId;
            $data_email     = $request->customerEmail;
            $data_name      = $request->customerName;

            // Validate that this order hasn't already been paid
            $alreadyPaid = PurchaseOrderHeaderLog::where('purchase_order_id', $data_order_id)
                ->where('status_id', '5')->count();
            if ($alreadyPaid > 0) {
                return response()->json(['status' => 'error', 'message' => 'La orden ya ha sido pagada'], 400);
            }

            $orderToken = Str::random(8);
            PurchaseOrderHeader::where('id', $data_order_id)->update(['order_token' => $orderToken]);
            Transaction::where('purchase_order_id', $data_order_id)->delete();

            $url_base = env('APP_URL');
            $creds    = $this->sfCredentials();

            $payload = [
                'amount'            => round((float)$data_amount, 2),
                'currency'          => $data_currency,
                'country'           => $data_country,
                'allow_transparent' => true,
                'payer'             => ['name' => $data_name, 'email' => $data_email],
                'order_id'          => $orderToken,
                'notification_url'  => $url_base . '/api/d-local/notification-url/' . $data_order_id,
                'success_url'       => 'https://adm.toggolac.com/panel/mis-compras?payment=success&order_id=' . $data_order_id . '&token=' . $orderToken,
            ];

            $body    = json_encode($payload);
            $headers = $this->dlocalHeaders($creds['key'], $creds['secret'], $body);
            $client  = new Client();

            $response     = $client->post($this->sfBaseUrl() . '/payments', [
                'headers' => $headers,
                'body'    => $body,
            ]);
            $responseData = json_decode($response->getBody()->getContents());

            // Persist transaction so notification webhook can resolve it later
            $authenticatedUser = Auth::user();
            $tx = new Transaction();
            $tx->order_token              = $orderToken;
            $tx->user_id                  = $authenticatedUser->id;
            $tx->purchase_order_id        = $data_order_id;
            $tx->payment_id               = $responseData->id;
            $tx->merchant_checkout_token  = $responseData->merchant_checkout_token;
            $tx->status                   = $responseData->status;
            $tx->amount                   = $responseData->amount;
            $tx->currency                 = $responseData->currency;
            $tx->created_date             = $responseData->created_date ?? now();
            $tx->save();

            $log = new TransactionLog();
            $log->transaction_id  = $tx->id;
            $log->previous_status = null;
            $log->new_status      = $responseData->status;
            $log->description     = 'SmartFields checkout created';
            $log->user_id         = $authenticatedUser->id;
            $log->save();

            return response()->json([
                'merchant_checkout_token' => $responseData->merchant_checkout_token,
                'payment_id'              => $responseData->id,
            ]);

        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $body = $e->getResponse()->getBody()->getContents();
            Log::error('dLocal createCheckout 4xx: ' . $body);
            return response()->json(['status' => 'error', 'message' => $body], 500);
        } catch (\Exception $e) {
            Log::error('dLocal createCheckout error: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    // ── SmartFields: Step 2 — confirm with card token ────────────────────────

    public function confirmSmartFieldsPayment(Request $request)
    {
        try {
            $checkoutToken  = $request->checkoutToken;
            $cardToken      = $request->cardToken;
            $firstName      = $request->clientFirstName;
            $lastName       = $request->clientLastName;
            $email          = $request->clientEmail;
            $installmentsId = $request->installmentsId;

            $creds = $this->sfCredentials();

            $payload = array_filter([
                'cardToken'          => $cardToken,
                'clientFirstName'    => $firstName,
                'clientLastName'     => $lastName,
                'clientEmail'        => $email,
                'clientDocumentType' => $request->clientDocumentType ?: null,
                'clientDocument'     => $request->clientDocument     ?: null,
                'installmentsId'     => $installmentsId              ?: null,
            ]);

            $body     = json_encode($payload);
            $headers  = $this->dlocalHeaders($creds['key'], $creds['secret'], $body);
            $client   = new Client();

            $response     = $client->post($this->sfBaseUrl() . '/payments/confirm/' . $checkoutToken, [
                'headers' => $headers,
                'body'    => $body,
            ]);
            $responseData = json_decode($response->getBody()->getContents());

            // Return only what the frontend needs
            return response()->json([
                'status'       => $responseData->status       ?? null,
                'redirect_url' => $responseData->redirect_url ?? null,
                'message'      => $responseData->message      ?? null,
            ]);

        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $body = $e->getResponse()->getBody()->getContents();
            $err  = json_decode($body);
            $msg  = $err->message ?? $body;
            Log::error('dLocal confirmSmartFields error: ' . $body);
            return response()->json(['status' => 'error', 'message' => $msg], 422);
        } catch (\Exception $e) {
            Log::error('dLocal confirmSmartFields error: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    // ── Exchange rates ────────────────────────────────────────────────────────

    public function getExchangeRates()
    {
        try {
            $rates = \Illuminate\Support\Facades\Cache::remember('usd_cop_rate', 1800, function () {
                $client   = new Client(['timeout' => 5]);
                $response = $client->get('https://cdn.jsdelivr.net/npm/@fawazahmed0/currency-api@latest/v1/currencies/usd.json');
                $data     = json_decode($response->getBody()->getContents(), true);
                return $data['usd']['cop'] ?? 0;
            });

            return response()->json(['usd_to_cop' => (float)$rates]);

        } catch (\Exception $e) {
            return response()->json(['usd_to_cop' => 0]);
        }
    }

    // ── Legacy ────────────────────────────────────────────────────────────────

    public function readPayment(Request $request)
    {
        try {

            $dlocalApiUrl = env('DLOCAL_API_URL');
            $dlocalApiKey = env('DLOCAL_API_KEY');
            $dlocalSecretKey = env('DLOCAL_SECRET_KEY');
            

            // $dlocalApiUrl = 'https://api-sbx.dlocalgo.com/v1/payments';
            // $dlocalApiKey = 'dbKCnBijhBvloQZFyxYnLpgPFfyEQOYL';
            // $dlocalSecretKey = '23yWaPYonn7UCVNcp83yYSEfBH3eYxqWAYi7WgKz';


            $data_payment_id = $request->payment_id;
            // Crea una instancia de GuzzleHttp\Client
            $client = new Client();

            // Realiza la solicitud POST a la API de dLocal
            $response = $client->get($dlocalApiUrl . '/' . $data_payment_id, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $dlocalApiKey . ':' . $dlocalSecretKey,
                    'Content-Type' => 'application/json',
                ]
            ]);

            // Obtiene el código de estado HTTP
            $statusCode = $response->getStatusCode();

            // Obtiene la respuesta de la API
            $responseBody = $response->getBody()->getContents();
            $responseData = json_decode($responseBody);


            if ($statusCode >= 400) {
                // Si el código de estado es un error, captura el mensaje de la respuesta JSON
                $errorMessage = $responseData->message;
                return response()->json(['error' => $errorMessage], $statusCode);
            }



            // Si la solicitud fue exitosa, devuelve la respuesta
            return $responseData;
        } catch (\Exception $e) {


            // Extraer la parte JSON de la cadena
            $jsonStart = strpos($e->getMessage(), '{');
            $jsonEnd = strrpos($e->getMessage(), '}');
            $jsonLength = $jsonEnd - $jsonStart + 1;
            $jsonString = substr($e->getMessage(), $jsonStart, $jsonLength);

            // Decodificar el JSON
            $errorData = json_decode($jsonString, true);

            // Manejar el error aquí, por ejemplo, registrarlo o devolver una respuesta de error
            return response()->json(['error' => $errorData, 'code' => $e->getCode()], 500);
        }
    }

    public function makePayment(Request $request)
    {
        try {
            $currencyFunctions = new CurrencyController();
            $data_amount = $request->amount;
            $data_currency = $request->currency;
            $data_country = $request->country;
            $data_name = $request->name;
            $data_email = $request->email;
            $data_phone = $request->phone;
            $data_order_id = $request->order_id;
            $currency = $request->query("currency");


            $orderToken = Str::random(8);

            //VALIDAR QUE LA ORDEN DE COMPRA NO HAYA SIDO PAGADA
            $validatePayment = PurchaseOrderHeaderLog::where('purchase_order_id', $data_order_id)->where('status_id', '5')->count();

            if ($validatePayment > 0) {
                //SI YA PRESENTA UN LOG CON ESTADO DE PAGADA SE DETIENE EL PROCESO
                return response()->json(['status' => 'error', 'message' => 'La orden de compra ya ha sido pagada'], 500);
            }

            // Store final sale acknowledgment
            $finalSaleAcknowledged   = filter_var($request->final_sale_acknowledged, FILTER_VALIDATE_BOOLEAN);
            $finalSaleAcknowledgedAt = $request->final_sale_acknowledged_at
                ? \Carbon\Carbon::parse($request->final_sale_acknowledged_at)
                : ($finalSaleAcknowledged ? now() : null);

            //ACTUALIZAMOS EL TOKEN EN LA ORDEN DE COMPRA
            $orderDetail = PurchaseOrderHeader::where('id', $data_order_id)->update([
                'order_token'                => $orderToken,
                'final_sale_acknowledged'    => $finalSaleAcknowledged,
                'final_sale_acknowledged_at' => $finalSaleAcknowledgedAt,
            ]);

            //ELIMINAMOS OTRAS TRANSACCIONES
            $deleteTransactions = Transaction::where('purchase_order_id', $data_order_id)->delete();

            $authenticatedUser = Auth::user(); // Obtener el usuario autenticado

            $dlocalApiUrl = env('DLOCAL_API_URL');
            $dlocalApiKey = env('DLOCAL_API_KEY');
            $dlocalSecretKey = env('DLOCAL_SECRET_KEY');
            $url_base = env('APP_URL');

            // Datos del pago
            $data = [
                'amount' => round($data_amount, 0, PHP_ROUND_HALF_UP),
                'currency' => $data_currency,
                // 'country' => $data_country,
                'payer' => [
                    'name' => $data_name,
                    'email' => $data_email,
                    'phone' => $data_phone
                ],
                'expiration_type' => 'MINUTES',
                'expiration_value' => 10,
                'back_url' => 'https://adm.toggolac.com/panel/mis-compras',
                'success_url' => 'https://adm.toggolac.com/panel/mis-compras?payment=success&order_id=' . $data_order_id . '&token=' . $orderToken,
                'notification_url' => $url_base.'/api/d-local/notification-url/' . $data_order_id,
                'order_id' => $orderToken,

            ];
            //dd($data);
            // return 'entro3';
            // Crea una instancia de GuzzleHttp\Client
            $client = new Client();

            // Realiza la solicitud POST a la API de dLocal
            $response = $client->post($dlocalApiUrl, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $dlocalApiKey . ':' . $dlocalSecretKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $data,
            ]);

            // Obtiene el código de estado HTTP
            $statusCode = $response->getStatusCode();

            // Obtiene la respuesta de la API
            $responseBody = $response->getBody()->getContents();
            $responseData = json_decode($responseBody);

            if ($statusCode >= 400) {
                // Si el código de estado es un error, captura el mensaje de la respuesta JSON
                $errorMessage = $responseData->message;
                return response()->json(['error' => $errorMessage], $statusCode);
            }




            //  return $responseData;

            $saveTransaction = new Transaction();
            $saveTransaction->order_token = $orderToken;
            $saveTransaction->user_id = $authenticatedUser->id;
            $saveTransaction->purchase_order_id = $data_order_id;
            $saveTransaction->payment_id = $responseData->id;
            $saveTransaction->merchant_checkout_token = $responseData->merchant_checkout_token;
            $saveTransaction->status = $responseData->status;
            $saveTransaction->amount = $responseData->amount;
            $saveTransaction->currency = $responseData->currency;
            $saveTransaction->created_date = $responseData->created_date;
            $saveTransaction->save();



            $log = new TransactionLog();
            $log->transaction_id = $saveTransaction->id; // Utiliza el ID de la transacción creada anteriormente
            $log->previous_status = null; // Estado anterior
            $log->new_status = $responseData->status; // Nuevo estado (tomado de la transacción)
            $log->description = "Transacción creada"; // Una descripción opcional
            $log->user_id = $authenticatedUser->id;
            $log->save();



            // Si la solicitud fue exitosa, devuelve la respuesta
            return response()->json(['response' => $responseData]);
        } catch (\Exception $e) {


            // Extraer la parte JSON de la cadena
            $jsonStart = strpos($e->getMessage(), '{');
            $jsonEnd = strrpos($e->getMessage(), '}');
            $jsonLength = $jsonEnd - $jsonStart + 1;
            $jsonString = substr($e->getMessage(), $jsonStart, $jsonLength);

            // Decodificar el JSON
            $errorData = json_decode($jsonString, true);

            // Manejar el error aquí, por ejemplo, registrarlo o devolver una respuesta de error
            return response()->json(['error' => $e->getMessage(), 'code' => $e->getCode()], 500);
        }
    }


    public function sendEmailConfirm($order_id, ?string $paymentLast4 = null, ?string $paymentBrand = null)
    {

        try {

            $order = new PurchaseOrderController();

            $requestFunctionPurchase = new Request([
                'no_paginate' => true,
            ]);

            $infoOrder = $order->showPurchaseOrder($requestFunctionPurchase, $order_id);
            if (isset($infoOrder) && isset($infoOrder['client'])) {


                $emailUser = $infoOrder['client']['email'] ?? null;
                $nameUser  = ($infoOrder['client']['name'] ?? '') . ' ' . ($infoOrder['client']['last_name'] ?? '');
                $products  = $infoOrder['purchase_order_details'] ?? [];

                $totalFormat = $infoOrder['total_product']['formatted'] ?? ($infoOrder['total_product']['amount'] ?? 0);

                // Build a readable shipping address string
                $addressParts = array_filter([
                    $infoOrder['destination_address']          ?? null,
                    $infoOrder['destinationCity']['name']      ?? null,
                    $infoOrder['destinationState']['name']     ?? null,
                    $infoOrder['destinationCountry']['name']   ?? null,
                ]);
                $shippingAddress = implode(', ', $addressParts) ?: null;

                // Determine language and destination type from country
                $destinationCountryName = strtolower($infoOrder['destinationCountry']['name'] ?? '');
                $isUSDestination = str_contains($destinationCountryName, 'united states')
                                || str_contains($destinationCountryName, 'estados unidos')
                                || $destinationCountryName === 'us'
                                || $destinationCountryName === 'usa';
                $isColombiaDestination = str_contains($destinationCountryName, 'colombia');
                $language = $isColombiaDestination ? 'es' : 'en';

                // Bilingual tax label map (keyed by uppercase Spanish name OR code)
                $taxLabelMap = [
                    'TOTAL PRODUCTOS' => ['en' => 'Product Total',    'es' => 'Total productos'],
                    'COSTO SERVICIO'  => ['en' => 'Service Fee',      'es' => 'Costo de servicio'],
                    'COSTO DE ENVÍO'  => ['en' => 'Shipping Cost',    'es' => 'Costo de envío'],
                    'COSTO DE ENVIO'  => ['en' => 'Shipping Cost',    'es' => 'Costo de envío'],
                    'TAXES EN USA'    => ['en' => 'USA Taxes (7%)',   'es' => 'Impuestos USA (7%)'],
                    'IVA COLOMBIA'    => ['en' => 'Colombia VAT',     'es' => 'IVA Colombia'],
                    'ARANCELES'       => ['en' => 'Customs Duties',   'es' => 'Aranceles'],
                ];

                // Colombia-only codes to hide for US orders (mirrors frontend logic)
                $colombiaOnlyCodes = ['IVA', 'IVA_CO', 'ARANCELES', 'CUSTOMS', 'TARIFF'];
                $colombiaOnlyNames = ['COLOMBIA', 'IVA', 'ARANCELES', 'ARANCEL'];

                $taxes = collect($infoOrder['taxes']['taxesList'] ?? [])
                    ->filter(function ($t) use ($isUSDestination, $colombiaOnlyCodes, $colombiaOnlyNames) {
                        if (($t['amount'] ?? 0) <= 0) return false;
                        if (!$isUSDestination) return true;
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

                // Format order date per language
                $orderDate = null;
                if (isset($infoOrder['created_at'])) {
                    $dt = \Carbon\Carbon::parse($infoOrder['created_at']);
                    $orderDate = $language === 'es'
                        ? $dt->locale('es')->isoFormat('D [de] MMMM [de] YYYY')
                        : $dt->format('F j, Y');
                }

                if ($emailUser) {
                    Mail::to($emailUser)->send(new PaymentConfirmOrderMail(
                        name:               trim($nameUser),
                        products:           $products,
                        total:              $totalFormat,
                        invoiceNumber:      $infoOrder['invoice_number']  ?? null,
                        orderToken:         $infoOrder['order_token']     ?? null,
                        orderDate:          $orderDate,
                        destinationAddress: $shippingAddress,
                        taxes:              $taxes,
                        language:           $language,
                        paymentLast4:       $paymentLast4,
                        paymentBrand:       $paymentBrand,
                    ));
                }
            }
        } catch (\Exception $e) {
            //throw $th;
        }
    }


    public function notificationPayment(Request $request, $order_id)
    {

        // Log::error('Request Data: ' . json_encode($request->all()));


        try {
            DB::beginTransaction();

            $payment_id = $request->payment_id;


            $requestFunction = new Request([
                'payment_id' => $payment_id,
            ]);

            $readPayment = $this->readPayment($requestFunction);
            // $responseData = json_decode($readPayment, true); // Decodifica el JSON en un arreglo asociativo


            if (isset($readPayment)) {


                $updateTransaction = Transaction::where('payment_id', $readPayment->id);
                $infoTransaction = $updateTransaction->first();

                $status_id = 1;

                if ($readPayment->status == 'PENDING' || $readPayment->status == 'AUTHORIZED' || $readPayment->status == 'VERIFIED') {
                    $status_id = 1; //PENDIENTE
                }
                if ($readPayment->status == 'PAID') {
                    $status_id = 5; //COMPLETADO
                }
                if ($readPayment->status == 'REJECTED' || $readPayment->status == 'CANCELLED') {
                    $status_id = 3; //FALLIDA
                }

                // return $readPayment;

                $updateOrder = PurchaseOrderHeader::where('id', $infoTransaction->purchase_order_id);
                $updateOrderStatus = $updateOrder->select('purchase_status_id', 'id', 'client_id', 'personal_shopper_id', 'shipment_price')->first();

                // return $infoTransaction;



                //LOG DE LA ORDEN DE COMPRA, CAMBIO DE ESTADO
                PurchaseOrderHeaderLog::create([
                    "purchase_order_id" => $infoTransaction->purchase_order_id,
                    "previous_status_id" => $updateOrderStatus->purchase_status_id,
                    "description" => "Actualizado desde la pasarela de pago",
                    "status_id" => $status_id
                ]);

                $updateOrder->update([
                    'purchase_status_id' => $status_id
                ]);

                $updateTransaction = $updateTransaction->update([
                    "payment_method_type" => $readPayment->payment_method_type ? $readPayment->payment_method_type  : null,
                    "status" => $readPayment->status,
                    "created_date" => $readPayment->created_date,
                    "approved_date" => isset($readPayment->approved_date) ? $readPayment->approved_date : null
                ]);


                if ($readPayment->status == 'REJECTED' || $readPayment->status == 'CANCELLED') {
                    // Payment failed — restore any products that may have been soft-deleted
                    // prematurely (e.g. by the admin when the order was created).
                    // Products should only leave the catalog upon confirmed payment.
                    $failedOrderDetails = PurchaseOrderDetail::where('purchase_order_header_id', $infoTransaction->purchase_order_id)->get();
                    foreach ($failedOrderDetails as $detail) {
                        Product::withTrashed()->where('id', $detail->product_id)->restore();
                    }
                }

                if ($readPayment->status == 'PAID') {
                    // Payment confirmed — now remove the products from the public catalog
                    // (soft-delete). This is the only place where a product should be
                    // considered "sold" and hidden from the storefront.
                    $paidOrderDetails = PurchaseOrderDetail::where('purchase_order_header_id', $infoTransaction->purchase_order_id)->get();
                    foreach ($paidOrderDetails as $detail) {
                        Product::where('id', $detail->product_id)->delete();
                    }

                    //SE REMUEVE EL CARRITO ACTUAL DEL USUARIO CUANDO SE COMPLETA EL PAGO
                    ShoppingCart::where('user_id', $updateOrderStatus->client_id)->delete();

                    // Extract card last-4, brand, and authorization code from dLocal response
                    $paymentLast4   = $readPayment->card->pan              ?? null;
                    $paymentBrand   = $readPayment->card->brand            ?? null;
                    $paymentAuthCode = $readPayment->authorization_id      ?? ($readPayment->authorization_code ?? null);
                    if (!$paymentBrand && isset($readPayment->payment_method_type)) {
                        $paymentBrand = $readPayment->payment_method_type;
                    }
                    $paymentApprovedAt = $readPayment->approved_date ?? null;

                    // ── Create immutable receipt record + generate PDF ──────────
                    $receiptCtrl = new ReceiptController();
                    $orderCtrl   = new PurchaseOrderController();
                    $orderReq    = new \Illuminate\Http\Request(['no_paginate' => true]);
                    $orderInfo   = $orderCtrl->showPurchaseOrder($orderReq, $infoTransaction->purchase_order_id);

                    $receiptCtrl->createAndStore(
                        orderInfo:            $orderInfo,
                        paymentTransactionId: $readPayment->id,
                        paymentAuthCode:      $paymentAuthCode,
                        paymentMethodType:    $readPayment->payment_method_type ?? null,
                        paymentCardBrand:     $paymentBrand,
                        paymentLast4:         $paymentLast4,
                        paymentAmount:        (float)($readPayment->amount ?? 0),
                        paymentCurrency:      $readPayment->currency ?? 'USD',
                        paymentApprovedAt:    $paymentApprovedAt,
                        customerIp:           $request->ip(),
                        userAgent:            $request->userAgent(),
                    );

                    //ENVÍO DE EMAIL CUANDO SE COMPLETA LA COMPRA
                    $this->sendEmailConfirm($infoTransaction->purchase_order_id, $paymentLast4, $paymentBrand);

                    // ── Dispatch supplier fulfillment (fire-and-forget) ────────
                    $this->dispatchFulfillment($infoTransaction->purchase_order_id, $paidOrderDetails);
                }

                $log = new TransactionLog();
                $log->transaction_id = $infoTransaction->id; // Estado anterior
                $log->previous_status = $infoTransaction->status; // Estado anterior
                $log->new_status = $readPayment->status; // Nuevo estado (tomado de la transacción)
                $log->description = "Estado actualizado"; // Una descripción opcional
                $log->user_id = null;
                $log->save();
            }

            DB::commit();
            return ['status' => 'success'];


            //code...
        } catch (\Exception $e) {
            // dd($e);
            DB::rollback();
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Notify admin of a confirmed purchase and queue any automated fulfillment.
     * Called fire-and-forget after the DB transaction commits so a failure here
     * never rolls back the payment confirmation.
     */
    private function dispatchFulfillment(int $orderId, $orderDetails): void
    {
        try {
            $orderCtrl = new PurchaseOrderController();
            $orderReq  = new \Illuminate\Http\Request(['no_paginate' => true]);
            $orderInfo = $orderCtrl->showPurchaseOrder($orderReq, $orderId);

            if (!isset($orderInfo['client'])) {
                Log::warning("dispatchFulfillment: could not load order {$orderId}");
                return;
            }

            $customerName  = trim(($orderInfo['client']['name'] ?? '') . ' ' . ($orderInfo['client']['last_name'] ?? ''));
            $customerEmail = $orderInfo['client']['email'] ?? '';
            $invoiceNumber = $orderInfo['invoice_number'] ?? "ORD-{$orderId}";
            $totalFormat   = $orderInfo['total_product']['formatted'] ?? ($orderInfo['total_product']['amount'] ?? 0);
            $products      = $orderInfo['purchase_order_details'] ?? [];

            $addressParts = array_filter([
                $orderInfo['destination_address']        ?? null,
                $orderInfo['destinationCity']['name']    ?? null,
                $orderInfo['destinationState']['name']   ?? null,
                $orderInfo['destinationCountry']['name'] ?? null,
            ]);
            $shippingAddress = implode(', ', $addressParts) ?: null;

            // Retrieve card details from the transaction already updated in the webhook
            $tx = \App\Models\Transaction::where('purchase_order_id', $orderId)
                ->orderBy('id', 'desc')->first();
            $paymentBrand = $tx->payment_method_type ?? null;
            $paymentLast4 = null; // stored on receipt, not on transaction

            $adminEmail = env('ADMIN_NOTIFICATION_EMAIL', 'toggolac@gmail.com');

            Mail::to($adminEmail)->send(new AdminNewOrderMail(
                orderId:         $orderId,
                invoiceNumber:   $invoiceNumber,
                customerName:    $customerName,
                customerEmail:   $customerEmail,
                total:           $totalFormat,
                products:        $products,
                shippingAddress: $shippingAddress,
                paymentBrand:    $paymentBrand,
                paymentLast4:    $paymentLast4,
            ));

            Log::info("Admin notified of new order #{$orderId} ({$invoiceNumber})");

            // Telegram push notification
            $tgToken  = env('TELEGRAM_BOT_TOKEN');
            $tgChatId = env('TELEGRAM_ADMIN_CHAT_ID');
            if ($tgToken && $tgChatId) {
                $text = "🛒 *Nueva compra confirmada*\n"
                      . "📋 Orden: {$invoiceNumber}\n"
                      . "👤 Cliente: {$customerName}\n"
                      . "💰 Total: {$totalFormat}\n"
                      . "🔗 [Ver orden](https://adm.toggolac.com/panel/compras/{$orderId})";

                (new \GuzzleHttp\Client())->post(
                    "https://api.telegram.org/bot{$tgToken}/sendMessage",
                    ['json' => ['chat_id' => $tgChatId, 'text' => $text, 'parse_mode' => 'Markdown']]
                );
            }

        } catch (\Exception $e) {
            // Log but never let this kill the payment confirmation response
            Log::error("dispatchFulfillment failed for order {$orderId}: " . $e->getMessage());
        }
    }
}
