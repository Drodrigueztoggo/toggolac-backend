<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StoreMall;
use Illuminate\Http\Request;
use Cknow\Money\Money as MoneyConvert;

class AppServiceResourcesController extends Controller
{
    public function searchApp(Request $request)
    {
        try {
            //code...
            $this->validate($request, [
                'search' => 'required',
            ]);


            $currencyFunctions = new CurrencyController();
            $currency = $request->currency;



            $searchTerm = $request->search;

            $products =  Product::with('brand:id,name_brand,image_brand')->where(function ($query) use ($searchTerm) {
                $query->where('name_product', 'LIKE', '%' . $searchTerm . '%')
                    ->orWhere('description_product', 'LIKE', '%' . $searchTerm . '%');
            })->get();

            $productsFormat = $products->map(function ($product) use ($currencyFunctions, $currency) {
                return [
                    "id" => $product->id,
                    "name" => $product->name_product,
                    "description_product" => $product->description_product,
                    "price_from" => isset($product->price_from) ? [
                        "currency" => $currency,
                        "formatted" => $currencyFunctions->convertAmount('USD', $currency, $product->price_from),
                    ] : [
                        "currency" => $currency,
                        "formatted" => "0",
                    ],
                    "price_to" => isset($product->price_to) ? [
                        "currency" => $currency,
                        "formatted" => $currencyFunctions->convertAmount('USD', $currency, $product->price_to),
                    ] : [
                        "currency" => $currency,
                        "formatted" => "0",
                    ],
                    "brand" => $product->brand,
                    "image" => $product->image,

                ];
            });


            $storeMalls =  StoreMall::with(
                'mallInfo.state',
                'mallInfo.city',
                'mallInfo.countryInfo'
            )->where('store', 'LIKE', '%' . $searchTerm . '%')->get();
            $storeMallsFormat = $storeMalls->map(function ($store) {
                return [
                    "id" => $store->id,
                    "name" => $store->store,
                    "image" => $store->image,
                    "state" => isset($store->mallInfo->state) ? $store->mallInfo->state : null,
                    "city" => isset($store->mallInfo->city) ? $store->mallInfo->city : null,
                    "country" => isset($store->mallInfo->countryInfo) ? $store->mallInfo->countryInfo : null

                ];
            });


            return [
                "products" => $productsFormat,
                "stores" => $storeMallsFormat,
            ];
        } catch (\Exception $e) {
            //throw $th;
        }
    }
}
