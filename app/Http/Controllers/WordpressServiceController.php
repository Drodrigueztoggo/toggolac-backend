<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Offer;
use App\Models\Product;
use Illuminate\Http\Request;
use Cknow\Money\Money as MoneyConvert;
use Illuminate\Database\Eloquent\Collection;

class WordpressServiceController extends Controller
{
    public function getWordPressData(Request $request)
    {
        try {
            $translate = new GoogleTranslateController();
            $currencyFunctions = new CurrencyController();
            $TGGlanguage = $request->TGGlanguage;
            $currency = $request->currency;

            $currentDate = now();

            $additionalOffer = Offer::with('product.brand', 'product.evaluations', 'storeMall')
                ->whereNull('deleted_at')
                ->whereNotNull('product_id')
                ->where('start_date', '<=', $currentDate)
                ->where('end_date', '>=', $currentDate)
                // ->whereNotIn('id', $offers->pluck('id')->toArray()) // PARA NO MOSTRAR LAS OFERTAS ANTERIORES
                ->inRandomOrder()
                ->select('id', 'image_offert', 'name', 'description', 'product_id', 'end_date', 'discount_price_from', 'discount_price_to', 'discount_percentage_from', 'discount_percentage_to', 'store_mall_id')
                ->limit(6);




            // Obtener 4 ofertas aleatorias
            $offers = Offer::where('start_date', '<=', $currentDate)
                ->where('end_date', '>=', $currentDate)
                ->inRandomOrder()
                ->limit(4);
            if (isset($additionalOffer)) {
                // $offers->whereNotIn('id', $additionalOffer->pluck("id"));
            }

            $offers = $offers->select('id', 'name', 'image_offert')
                ->get();

            $offersFormat = $offers->map(function ($item) use ($translate, $TGGlanguage) {
                return [
                    "id" => $item['id'],
                    "name" => $TGGlanguage != 'es' ? $translate->translateText($item['name'], $TGGlanguage) : $item['name'],
                    "image" => $item['image']
                ];
            });



            $additionalOffer = $additionalOffer->get();

            $additionalOfferFormat = $additionalOffer->map(function ($offert) use ($translate, $TGGlanguage, $currencyFunctions, $currency) {

                if(isset($offert->product)){
                    $rating =  isset($offert->product->evaluations) && count($offert->product->evaluations) > 0 ? $offert->product->evaluations->avg('rating') : 0;
                }


                return [
                    'id' => $offert->id,
                    "name" => $TGGlanguage != 'es' ? $translate->translateText($offert->name, $TGGlanguage) : $offert->name,
                    "description" => $TGGlanguage != 'es' ? $translate->translateText($offert->description, $TGGlanguage) : $offert->description,
                    'product_id' => $offert->product_id,
                    'end_date' => $offert->end_date,
                    'store_mall_id' => $offert->store_mall_id,
                    'price' => [
                        'min' => $offert->discount_price_from ?  $currencyFunctions->convertAmount('USD', $currency, $offert->discount_price_from) : 0,
                        'max' => $offert->discount_price_to ?  $currencyFunctions->convertAmount('USD', $currency, $offert->discount_price_to) : 0,
                    ],
                    'percentage' => (isset($offert->discount_percentage_from) && isset($offert->discount_percentage_to)) ? $offert->discount_percentage_from . "% - " . $offert->discount_percentage_to . "%" : $offert->discount_percentage_from . "%",
                    'image' => asset($offert->image),
                    'product' => $offert->product ?
                        [
                            'id' => $offert->product->id,
                            'rating' => $rating,
                            'brand' => $offert->product->brand ? [
                                "id" => $offert->product->brand->id,
                                "name_brand" => $TGGlanguage != 'es' ? $translate->translateText($offert->product->brand->name_brand, $TGGlanguage) : $offert->product->brand->name_brand,
                                "description_brand" => $TGGlanguage != 'es' ? $translate->translateText($offert->product->brand->description_brand, $TGGlanguage) : $offert->product->brand->description_brand,
                                "image" => $offert->product->brand->image,
                            ] : null,
                            "name" => $TGGlanguage != 'es' ? $translate->translateText($offert->product->name, $TGGlanguage) : $offert->product->name,
                            'price' => [
                                'min' => $offert->product->price_from ?   $currencyFunctions->convertAmount('USD', $currency, $offert->product->price_from) : 0,
                                'max' => $offert->product->price_to ?   $currencyFunctions->convertAmount('USD', $currency, $offert->product->price_to) : 0,
                            ],
                            'image_product' => $offert->product->image,
                            'image' => asset($offert->product->image),
                        ]
                        : null,
                    'store_mall' => $offert->storeMall,
                ];
            });




            // Combinar los resultados en un solo array
            $data = [
                'offers' => $offersFormat,
                'additionalOffer' => $additionalOfferFormat,
            ];

            return response()->json($data);
        } catch (\Exception $e) {
            dd($e);
            // Manejar cualquier excepción y devolver una respuesta de error
            return response()->json(['error' => 'Ocurrió un error en el servidor.'], 500);
        }
    }

    public function getLastProducts(Request $request)
    {
        try {

            $TGGlanguage = $request->TGGlanguage;
            $currency = $request->currency;
            $translate = new GoogleTranslateController();
            $currencyFunctions = new CurrencyController();



            $filter_limit = $request->query('limit', 10);
            $filter_category = $request->query('category_id');
            $filter_brand = $request->query('brand_id');
            $filter_mall = $request->query('mall_id');
            $filter_store = $request->query('store_id');
            $filter_order = $request->query('order');

            // Obtener los últimos 10 productos agregados
            $productsQuery = Product::with('evaluations', 'brand', 'storeProducts', 'mallProducts.countryInfo', 'categoriesProduct:product_id,category_id', 'categoriesProduct.category:id,name_category,image_category');


            if (isset($filter_category) && $filter_category !== -1) {
                $filter_category_array = explode(',', $filter_category);

                $productsQuery->whereHas('categoriesProduct', function ($q) use ($filter_category_array) {
                    $q->whereIn('category_id', $filter_category_array);
                });
            }
            if (isset($filter_brand)) {
                $filter_brand_array = explode(',', $filter_brand);

                $productsQuery->whereIn('brand_id', $filter_brand_array);
            }

            if (isset($filter_mall)) {
                $filter_mall_array = explode(',', $filter_mall);

                $productsQuery->whereHas('mallProducts', function ($q) use ($filter_mall_array) {
                    $q->whereIn('malls.id', $filter_mall_array);
                });
            }

            if (isset($filter_store)) {
                $filter_store_array = explode(',', $filter_store);

                $productsQuery->whereHas('storeProducts', function ($q) use ($filter_store_array) {
                    $q->whereIn('store_malls.id', $filter_store_array);
                });
            }
            
            if (isset($filter_limit)) {
                $productsQuery->limit($filter_limit);
            }
            
            $productsQuery->select('id', 'name_product AS name', 'price_from', 'price_to', 'image_product', 'brand_id', 'description_product');
            
            if(isset($filter_order) && $filter_order == 'rand'){
                $products = $productsQuery->inRandomOrder()->get();
            }else{
                $products = $productsQuery->orderBy('created_at', 'desc')->get();
            }

            if (!$products->isEmpty()) {



                // Reorganizar la estructura JSON
                $formattedProducts = $products->map(function ($product) use ($TGGlanguage, $translate, $currencyFunctions, $currency) {

                    $rating =  isset($product->evaluations) && count($product->evaluations) > 0 ? $product->evaluations->avg('rating') : 0;

                    $uniqueCountries = null;
                    $malls = null;
                    $categories = null;
                    $stores = null;

                    if (isset($product->mallProducts)) {
                        $uniqueCountries = collect($product->mallProducts)
                            ->map(function ($item) {
                                return $item['countryInfo'];
                            })
                            ->unique('id')
                            ->values();
                    }

                    if (isset($product->mallProducts)) {
                        $malls = collect($product->mallProducts)
                            ->map(function ($mall) use ($TGGlanguage, $translate) {
                                return [
                                    'id' => $mall->id,
                                    "name" => $TGGlanguage != 'es' ? $translate->translateText($mall->name, $TGGlanguage) : $mall->name,
                                    'image' => $mall->image,
                                ];
                            });
                    }

                    if (isset($product->storeProducts)) {
                        $stores = collect($product->storeProducts)
                            ->map(function ($mall) use ($TGGlanguage, $translate) {
                                return [
                                    'id' => $mall->id,
                                    "name" => $TGGlanguage != 'es' ? $translate->translateText($mall->name, $TGGlanguage) : $mall->name,
                                    'image' => $mall->image,
                                ];
                            });
                    }

                    if (isset($product->categoriesProduct)) {


                        $categories = collect($product->categoriesProduct)
                            ->map(function ($category) use ($TGGlanguage, $translate) {
                                return [
                                    'id' => $category->category_id,
                                    "name" => $TGGlanguage != 'es' ? $translate->translateText($category->category->name_category, $TGGlanguage) : $category->category->name_category,
                                    'image' => $category->category->image,
                                ];
                            });
                    }


                    return [
                        'id' => $product->id,
                        'categories' => $categories,
                        'rating' => $rating,
                        'brand' => isset($product->brand) ? [
                            "id" => $product->brand->id,
                            "name_brand" => $TGGlanguage != 'es' ? $translate->translateText($product->brand->name_brand, $TGGlanguage) : $product->brand->name_brand,
                            "image" => $product->brand->image
                        ] : null,
                        'malls' => $malls,
                        'stores' => $stores,
                        'countries' => $uniqueCountries,
                        "name" => $TGGlanguage != 'es' ? $translate->translateText($product->name, $TGGlanguage) : $product->name,
                        "description_product" => $TGGlanguage != 'es' ? $translate->translateText($product->description_product, $TGGlanguage) : $product->description_product,
                        'price' => [
                            // 'min' => $product->price_from ?  MoneyConvert::USD($product->price_from) : 0,
                            'min' => $product->price_from ?  $currencyFunctions->convertAmount('USD', $currency, $product->price_from) : 0,
                            'max' => $product->price_to ? $currencyFunctions->convertAmount('USD', $currency, $product->price_to) : 0
                        ],
                        "price_origin" => $product->price_from,
                        // 'image_product' => $product->image,
                        'image' => asset($product->image)
                    ];
                });

                return response()->json($formattedProducts);
            } else {
                return response()->json(['message' => 'No se encontraron productos.'], 404);
            }
        } catch (\Exception $e) {
            // Manejar cualquier excepción y devolver una respuesta de error
            return response()->json(['error' => 'Ocurrió un error en el servidor.'], 500);
        }
    }
    public function getLastProductsPaginate(Request $request)
    {
        try {

            $TGGlanguage = $request->TGGlanguage;
            $currency = $request->currency;
            $translate = new GoogleTranslateController();
            $currencyFunctions = new CurrencyController();

            $per_page = $request->query('per_page', 10);


            $filter_limit = $request->query('limit');
            $filter_category = $request->query('category_id');
            $filter_brand = $request->query('brand_id');
            $filter_mall = $request->query('mall_id');
            $filter_store = $request->query('store_id');
            $filter_order = $request->query('order');

            $productsQuery = Product::with('evaluations', 'brand', 'storeProducts', 'mallProducts.countryInfo', 'categoriesProduct:product_id,category_id', 'categoriesProduct.category:id,name_category,image_category');


            if (isset($filter_category)) {
                $filter_category_array = explode(',', $filter_category);

                $productsQuery->whereHas('categoriesProduct', function ($q) use ($filter_category_array) {
                    $q->whereIn('category_id', $filter_category_array);
                });
            }
            if (isset($filter_brand)) {
                $filter_brand_array = explode(',', $filter_brand);

                $productsQuery->whereIn('brand_id', $filter_brand_array);
            }

            if (isset($filter_mall)) {
                $filter_mall_array = explode(',', $filter_mall);

                $productsQuery->whereHas('mallProducts', function ($q) use ($filter_mall_array) {
                    $q->whereIn('malls.id', $filter_mall_array);
                });
            }

            if (isset($filter_store)) {
                $filter_store_array = explode(',', $filter_store);

                $productsQuery->whereHas('storeProducts', function ($q) use ($filter_store_array) {
                    $q->whereIn('store_malls.id', $filter_store_array);
                });
            }

          
            if (isset($filter_limit)) {
                $productsQuery->limit($filter_limit);
            }

            $productsQuery->select('id', 'name_product AS name', 'price_from', 'price_to', 'image_product', 'brand_id', 'description_product');


            if(isset($filter_order) && $filter_order == 'rand'){
                $products = $productsQuery->inRandomOrder()->paginate($per_page);
            }else{
                $products = $productsQuery->orderBy('created_at', 'desc')->paginate($per_page);
            }



            // return $products;

            if (!$products->isEmpty()) {



                // Reorganizar la estructura JSON
                $formattedProducts = $products->map(function ($product) use ($TGGlanguage, $translate, $currencyFunctions, $currency) {

                    $rating =  isset($product->evaluations) && count($product->evaluations) > 0 ? $product->evaluations->avg('rating') : 0;

                    $uniqueCountries = null;
                    $malls = null;
                    $categories = null;
                    $stores = null;

                    if (isset($product->mallProducts)) {
                        $uniqueCountries = collect($product->mallProducts)
                            ->map(function ($item) {
                                return $item['countryInfo'];
                            })
                            ->unique('id')
                            ->values();
                    }

                    if (isset($product->mallProducts)) {
                        $malls = collect($product->mallProducts)
                            ->map(function ($mall) use ($TGGlanguage, $translate) {
                                return [
                                    'id' => $mall->id,
                                    "name" => $TGGlanguage != 'es' ? $translate->translateText($mall->name, $TGGlanguage) : $mall->name,
                                    'image' => $mall->image,
                                ];
                            });
                    }

                    if (isset($product->storeProducts)) {
                        $stores = collect($product->storeProducts)
                            ->map(function ($mall) use ($TGGlanguage, $translate) {
                                return [
                                    'id' => $mall->id,
                                    "name" => $TGGlanguage != 'es' ? $translate->translateText($mall->name, $TGGlanguage) : $mall->name,
                                    'image' => $mall->image,
                                ];
                            });
                    }

                    if (isset($product->categoriesProduct)) {


                        $categories = collect($product->categoriesProduct)
                            ->map(function ($category) use ($TGGlanguage, $translate) {
                                return [
                                    'id' => $category->category_id,
                                    "name" => $TGGlanguage != 'es' ? $translate->translateText($category->category->name_category, $TGGlanguage) : $category->category->name_category,
                                    'image' => $category->category->image,
                                ];
                            });
                    }


                    return [
                        'id' => $product->id,
                        'categories' => $categories,
                        'rating' => $rating,
                        'brand' => isset($product->brand) ? [
                            "id" => $product->brand->id,
                            "name_brand" => $TGGlanguage != 'es' ? $translate->translateText($product->brand->name_brand, $TGGlanguage) : $product->brand->name_brand,
                            "image" => $product->brand->image
                        ] : null,
                        'malls' => $malls,
                        'stores' => $stores,
                        'countries' => $uniqueCountries,
                        "name" => $TGGlanguage != 'es' ? $translate->translateText($product->name, $TGGlanguage) : $product->name,
                        "description_product" => $TGGlanguage != 'es' ? $translate->translateText($product->description_product, $TGGlanguage) : $product->description_product,
                        'price' => [
                            // 'min' => $product->price_from ?  MoneyConvert::USD($product->price_from) : 0,
                            'min' => $product->price_from ?  $currencyFunctions->convertAmount('USD', $currency, $product->price_from) : 0,
                            'max' => $product->price_to ? $currencyFunctions->convertAmount('USD', $currency, $product->price_to) : 0
                        ],
                        "price_origin" => $product->price_from,
                        // 'image_product' => $product->image,
                        'image' => asset($product->image)
                    ];
                });


                $responseData = [
                    "data" => $formattedProducts,
                    'current_page' => $products->currentPage(),
                    'first_page_url' => $products->url(1),
                    'from' => $products->firstItem(),
                    'last_page' => $products->lastPage(),
                    'last_page_url' => $products->url($products->lastPage()),
                    'next_page_url' => $products->nextPageUrl(),
                    'path' => $products->url($products->currentPage()),
                    'per_page' => $products->perPage(),
                    'prev_page_url' => $products->previousPageUrl(),
                    'to' => $products->lastItem(),
                    'total' => $products->total(),
                ];



                return response()->json($responseData);
            } else {
                return response()->json(['message' => 'No se encontraron productos.'], 404);
            }
        } catch (\Exception $e) {
            // Manejar cualquier excepción y devolver una respuesta de error
            return response()->json(['error' => 'Ocurrió un error en el servidor.'], 500);
        }
    }


    public function getAllCategories(Request $request)
    {
        try {
            $TGGlanguage = $request->TGGlanguage;
            $translate = new GoogleTranslateController();

            // Obtener todas las categorías
            $categories = Category::select('id', 'name_category AS name', 'image_category', 'description_category')->get();

            $categoryFormat = $categories->map(function ($item) use ($translate, $TGGlanguage) {
                return [
                    "id" => $item['id'],
                    "name" => $TGGlanguage != 'es' ? $translate->translateText($item['name'], $TGGlanguage) : $item['name'],
                    "description" => $TGGlanguage != 'es' ? $translate->translateText($item['description_category'], $TGGlanguage) : $item['description_category'],
                    "image" => $item['image']
                ];
            });

            // Comprobar si se encontraron categorías
            if (!$categories->isEmpty()) {
                return response()->json($categoryFormat);
            } else {
                return response()->json(['message' => 'No se encontraron categorías.'], 404);
            }
        } catch (\Exception $e) {
            // Manejar cualquier excepción y devolver una respuesta de error
            return response()->json(['error' => 'Ocurrió un error en el servidor.'], 500);
        }
    }
}
