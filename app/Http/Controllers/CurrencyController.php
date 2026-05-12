<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Magarrent\LaravelCurrencyFormatter\Facades\Currency as CurrencyFormat;



class CurrencyController extends Controller
{

    public function convertAmount($currencySource, $currencyTarget = 'USD', $amount, $inverse = false)
    {
        try {


            $controlConversion = false;
            if ($inverse) {
                $getCurrencies = Currency::where('currency_code', $currencyTarget)->select('conversion_rates', 'currency_code')->first();
                if (isset($getCurrencies) && isset($getCurrencies->conversion_rates)) {
                    $conversionAll = json_decode($getCurrencies->conversion_rates);
                    if (isset($conversionAll->results->$currencySource)) {
                        $conversion = $conversionAll->results->$currencySource;
                        $controlConversion = true;
                    }
                }
            } else {
                if (isset($currencyTarget) && $currencyTarget != 'USD') {
                    $getCurrencies = Currency::where('currency_code', 'USD')->select('conversion_rates', 'currency_code')->first();
                    if (isset($getCurrencies) && isset($getCurrencies->conversion_rates)) {
                        $conversionAll = json_decode($getCurrencies->conversion_rates);
                        if (isset($conversionAll->results->$currencyTarget)) {
                            $conversion = $conversionAll->results->$currencyTarget;
                            $controlConversion = true;
                        }
                    }
                }

            }
            if (!$controlConversion) {
                //SI NO HAY CONVERSION
                // return  money($amount, $currencySource);
                return $amount;

            } else {
                //SE REALIZA LA CONVERSION
                $porcentage = ($conversion * 0.09);
                $totalConversion = $conversion + $porcentage;
                $total = ($amount) * ($totalConversion);
                // return  money($total, $currencyTarget);
                return $total;
            }
        } catch (\Exception $e) {
            report($e);
        }
    }



    public function updateCurrencies()
    {
        try {

            $currencies = ["COP", "USD", "EUR"];

            foreach ($currencies as $currencyCode) {

                $apiURL = "https://open.er-api.com/v6/latest/" . $currencyCode;

                $response = Http::get($apiURL);

                if (!$response->successful()) {
                    \Illuminate\Support\Facades\Log::warning("CurrencyController: failed to fetch rates for {$currencyCode}");
                    continue;
                }

                $data = $response->json();

                // Transform {"rates": {...}} → {"results": {...}} to match convertAmount() expectations
                $results = [];
                foreach ($currencies as $target) {
                    if ($target !== $currencyCode && isset($data['rates'][$target])) {
                        $results[$target] = $data['rates'][$target];
                    }
                }

                $record = Currency::firstOrNew(['currency_code' => $currencyCode]);
                $record->conversion_rates = json_encode(['results' => $results]);
                $record->save();
            }
        } catch (\Exception $e) {
            report($e);
        }
    }
}
