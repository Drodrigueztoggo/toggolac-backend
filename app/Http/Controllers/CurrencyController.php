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

            $currencies = [
                "COP",
                "USD",
                "EUR"
            ];

            foreach ($currencies as $key => $currency) {

                $toCurrency = json_decode(json_encode($currencies), true);

                $toCurrency = array_diff($toCurrency, array($currency));

                $apiURL = "https://api.fastforex.io/fetch-multi?from=" .  $currency . "&to=" . implode(',', $toCurrency)  . "&api_key=088dfcbe28-2c8ef67df8-s3x7ei";

                $response = Http::get($apiURL);

                $statusCode = $response->status();
                $responseBody = $response->getBody();

                $currency = Currency::firstOrNew(['currency_code' => $currency]);

                // Establecer los nuevos valores
                $currency->conversion_rates = $responseBody;

                // Guardar el modelo (insertar o actualizar)
                $currency->save();
            }
        } catch (\Exception $e) {
            report($e);
        }
    }
}
