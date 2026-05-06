<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Offer;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Cknow\Money\Money as MoneyConvert;
use Illuminate\Database\Eloquent\Collection;

class WordpressServiceController extends Controller
{
    public function getWordPressData(Request $request)
    {
        try {
            $TGGlanguage = $request->TGGlanguage ?? 'es';
            $currency    = $request->currency    ?? 'COP';
            $isEn        = str_starts_with(strtolower((string) $TGGlanguage), 'en');

            // Full response cache — keyed by currency+language, TTL 5 minutes.
            // This is the primary fix: translations only run once per cache window
            // instead of on every request.
            $cacheKey = 'offers_v2_' . md5($currency . '_' . $TGGlanguage);
            $cached   = Cache::get($cacheKey);
            if ($cached !== null) {
                return response()->json($cached);
            }

            $translate        = new GoogleTranslateController();
            $currencyFunctions = new CurrencyController();

            // Limit to 300 rows — enough to fill all categories (max ~20 × 6 = 120 needed)
            // while avoiding a full-table scan on large offer sets.
            $activeOffers = Offer::with([
                'product.brand',
                'product' => fn($q) => $q->withAvg('evaluations', 'rating'),
                'product.categoriesProduct.category',
                'storeMall',
            ])
                ->whereNull('deleted_at')
                ->whereNotNull('product_id')
                ->inRandomOrder()
                ->limit(300)
                ->select('id', 'image_offert', 'name', 'description', 'product_id', 'end_date',
                         'discount_price_from', 'discount_price_to',
                         'discount_percentage_from', 'discount_percentage_to', 'store_mall_id')
                ->get();

            // Group offers by primary category (up to 6 per category)
            $grouped = [];
            foreach ($activeOffers as $offer) {
                if (!$offer->product) continue;

                $cats = $offer->product->categoriesProduct;
                if ($cats && $cats->isNotEmpty() && $cats->first()->category) {
                    $cat     = $cats->first()->category;
                    $catId   = $cat->id;
                    $catName = $cat->name_category;
                } else {
                    $catId   = 45;
                    $catName = 'Tecnología';
                }

                if (!isset($grouped[$catId])) {
                    $grouped[$catId] = ['id' => $catId, 'name' => $catName, 'items' => []];
                }
                if (count($grouped[$catId]['items']) < 6) {
                    $grouped[$catId]['items'][] = $offer;
                }
            }

            // Format helper — uses pre-translated name_product_en when available,
            // falls back to translateText only for offer name/description (not brands,
            // which are almost always English already).
            $formatOffer = function ($offert) use ($translate, $TGGlanguage, $isEn, $currencyFunctions, $currency) {
                $rating = $offert->product->evaluations_avg_rating ?? 0;

                return [
                    'id'            => $offert->id,
                    'name'          => $isEn ? $translate->translateText($offert->name, $TGGlanguage) : $offert->name,
                    'description'   => $isEn ? $translate->translateText($offert->description, $TGGlanguage) : $offert->description,
                    'product_id'    => $offert->product_id,
                    'end_date'      => $offert->end_date,
                    'store_mall_id' => $offert->store_mall_id,
                    'price' => [
                        'min' => $offert->discount_price_from ? $currencyFunctions->convertAmount('USD', $currency, $offert->discount_price_from) : 0,
                        'max' => $offert->discount_price_to   ? $currencyFunctions->convertAmount('USD', $currency, $offert->discount_price_to)   : 0,
                    ],
                    'percentage' => isset($offert->discount_percentage_to)
                        ? $offert->discount_percentage_from . '% - ' . $offert->discount_percentage_to . '%'
                        : $offert->discount_percentage_from . '%',
                    'image'   => asset($offert->image),
                    'product' => $offert->product ? [
                        'id'     => $offert->product->id,
                        'rating' => round((float) $rating, 1),
                        'brand'  => $offert->product->brand ? [
                            'id'         => $offert->product->brand->id,
                            'name_brand' => $offert->product->brand->name_brand,
                            'image'      => $offert->product->brand->image,
                        ] : null,
                        'name'  => $isEn
                            ? ($offert->product->name_product_en ?? $offert->product->name_product)
                            : $offert->product->name_product,
                        'price' => [
                            'min' => $offert->product->price_from ? $currencyFunctions->convertAmount('USD', $currency, $offert->product->price_from) : 0,
                            'max' => $offert->product->price_to   ? $currencyFunctions->convertAmount('USD', $currency, $offert->product->price_to)   : 0,
                        ],
                        'image_product' => $offert->product->image,
                        'image'         => asset($offert->product->image),
                        'gallery'       => $offert->product->gallery ?? [],
                    ] : null,
                    'store_mall' => $offert->storeMall,
                ];
            };

            // Build categoryOffers array (only categories with ≥1 offer)
            $categoryOffers = collect(array_values($grouped))
                ->map(fn($group) => [
                    'id'     => $group['id'],
                    'name'   => $group['name'],
                    'offers' => collect($group['items'])->map($formatOffer)->values(),
                ])
                ->filter(fn($g) => count($g['offers']) > 0)
                ->values();

            $result = ['categoryOffers' => $categoryOffers];
            Cache::put($cacheKey, $result, now()->addMinutes(5));

            return response()->json($result);
        } catch (\Exception $e) {
            report($e);
            return response()->json(['error' => 'Ocurrió un error en el servidor.'], 500);
        }
    }

    public function getLastProducts(Request $request)
    {
        try {
            $TGGlanguage = $request->TGGlanguage;
            $currency    = $request->currency;
            $isEn        = str_starts_with(strtolower((string)($TGGlanguage ?? '')), 'en');
            $currencyFunctions = new CurrencyController();

            $filter_limit    = $request->query('limit', 10);
            $filter_category = $request->query('category_id');
            $filter_brand    = $request->query('brand_id');
            $filter_mall     = $request->query('mall_id');
            $filter_store    = $request->query('store_id');
            $filter_order    = $request->query('order');

            $cacheKey = 'products_v2_' . md5(implode('_', [
                $currency, $TGGlanguage, $filter_limit, $filter_category,
                $filter_brand, $filter_mall, $filter_store, $filter_order,
            ]));
            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                return response()->json($cached);
            }

            $productsQuery = Product::withAvg('evaluations', 'rating')
                ->with('brand', 'storeProducts', 'mallProducts.countryInfo',
                       'categoriesProduct:product_id,category_id',
                       'categoriesProduct.category:id,name_category,image_category');

            if (isset($filter_category) && $filter_category !== -1) {
                $filter_category_array = explode(',', $filter_category);
                $productsQuery->whereHas('categoriesProduct', function ($q) use ($filter_category_array) {
                    $q->whereIn('category_id', $filter_category_array);
                });
            }
            if (isset($filter_brand)) {
                $productsQuery->whereIn('brand_id', explode(',', $filter_brand));
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

            $productsQuery->select('id', 'name_product AS name', 'name_product_en', 'price_from', 'price_to', 'image_product', 'brand_id', 'description_product', 'description_product_en');

            $products = ($filter_order === 'rand')
                ? $productsQuery->inRandomOrder()->get()
                : $productsQuery->orderBy('created_at', 'desc')->get();

            if ($products->isEmpty()) {
                return response()->json(['message' => 'No se encontraron productos.'], 404);
            }

            $formattedProducts = $products->map(function ($product) use ($isEn, $currencyFunctions, $currency) {
                $rating = round((float)($product->evaluations_avg_rating ?? 0), 1);

                $uniqueCountries = isset($product->mallProducts)
                    ? collect($product->mallProducts)->map(fn($i) => $i['countryInfo'])->unique('id')->values()
                    : null;

                $malls = isset($product->mallProducts)
                    ? collect($product->mallProducts)->map(fn($mall) => ['id' => $mall->id, 'name' => $mall->name, 'image' => $mall->image])
                    : null;

                $stores = isset($product->storeProducts)
                    ? collect($product->storeProducts)->map(fn($s) => ['id' => $s->id, 'name' => $s->name, 'image' => $s->image])
                    : null;

                $categories = isset($product->categoriesProduct)
                    ? collect($product->categoriesProduct)->map(fn($c) => ['id' => $c->category_id, 'name' => $c->category->name_category, 'image' => $c->category->image])
                    : null;

                return [
                    'id'         => $product->id,
                    'categories' => $categories,
                    'rating'     => $rating,
                    'brand'      => $product->brand ? ['id' => $product->brand->id, 'name_brand' => $product->brand->name_brand, 'image' => $product->brand->image] : null,
                    'malls'      => $malls,
                    'stores'     => $stores,
                    'countries'  => $uniqueCountries,
                    'name'       => $isEn ? ($product->name_product_en ?? $product->name) : $product->name,
                    'description_product' => $isEn ? ($product->description_product_en ?? $product->description_product) : $product->description_product,
                    'price' => [
                        'min' => $product->price_from ? $currencyFunctions->convertAmount('USD', $currency, $product->price_from) : 0,
                        'max' => $product->price_to   ? $currencyFunctions->convertAmount('USD', $currency, $product->price_to)   : 0,
                    ],
                    'price_origin' => $product->price_from,
                    'image'        => asset($product->image),
                ];
            });

            Cache::put($cacheKey, $formattedProducts, now()->addMinutes(5));
            return response()->json($formattedProducts);
        } catch (\Exception $e) {
            report($e);
            return response()->json(['error' => 'Ocurrió un error en el servidor.'], 500);
        }
    }

    public function getLastProductsPaginate(Request $request)
    {
        try {
            $TGGlanguage = $request->TGGlanguage;
            $currency    = $request->currency;
            $isEn        = str_starts_with(strtolower((string)($TGGlanguage ?? '')), 'en');
            $currencyFunctions = new CurrencyController();

            $per_page        = $request->query('per_page', 10);
            $filter_limit    = $request->query('limit');
            $filter_category = $request->query('category_id');
            $filter_brand    = $request->query('brand_id');
            $filter_mall     = $request->query('mall_id');
            $filter_store    = $request->query('store_id');
            $filter_order    = $request->query('order');
            $page            = $request->query('page', 1);

            $cacheKey = 'products_paginate_v2_' . md5(implode('_', [
                $currency, $TGGlanguage, $per_page, $page, $filter_limit,
                $filter_category, $filter_brand, $filter_mall, $filter_store, $filter_order,
            ]));
            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                return response()->json($cached);
            }

            $productsQuery = Product::withAvg('evaluations', 'rating')
                ->with('brand', 'storeProducts', 'mallProducts.countryInfo',
                       'categoriesProduct:product_id,category_id',
                       'categoriesProduct.category:id,name_category,image_category');

            if (isset($filter_category)) {
                $productsQuery->whereHas('categoriesProduct', function ($q) use ($filter_category) {
                    $q->whereIn('category_id', explode(',', $filter_category));
                });
            }
            if (isset($filter_brand)) {
                $productsQuery->whereIn('brand_id', explode(',', $filter_brand));
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

            $productsQuery->select('id', 'name_product AS name', 'name_product_en', 'price_from', 'price_to', 'image_product', 'brand_id', 'description_product', 'description_product_en');

            $products = ($filter_order === 'rand')
                ? $productsQuery->inRandomOrder()->paginate($per_page)
                : $productsQuery->orderBy('created_at', 'desc')->paginate($per_page);

            if ($products->isEmpty()) {
                return response()->json(['message' => 'No se encontraron productos.'], 404);
            }

            $formattedProducts = $products->map(function ($product) use ($isEn, $currencyFunctions, $currency) {
                $rating = round((float)($product->evaluations_avg_rating ?? 0), 1);

                $uniqueCountries = isset($product->mallProducts)
                    ? collect($product->mallProducts)->map(fn($i) => $i['countryInfo'])->unique('id')->values()
                    : null;

                $malls = isset($product->mallProducts)
                    ? collect($product->mallProducts)->map(fn($m) => ['id' => $m->id, 'name' => $m->name, 'image' => $m->image])
                    : null;

                $stores = isset($product->storeProducts)
                    ? collect($product->storeProducts)->map(fn($s) => ['id' => $s->id, 'name' => $s->name, 'image' => $s->image])
                    : null;

                $categories = isset($product->categoriesProduct)
                    ? collect($product->categoriesProduct)->map(fn($c) => ['id' => $c->category_id, 'name' => $c->category->name_category, 'image' => $c->category->image])
                    : null;

                return [
                    'id'         => $product->id,
                    'categories' => $categories,
                    'rating'     => $rating,
                    'brand'      => $product->brand ? ['id' => $product->brand->id, 'name_brand' => $product->brand->name_brand, 'image' => $product->brand->image] : null,
                    'malls'      => $malls,
                    'stores'     => $stores,
                    'countries'  => $uniqueCountries,
                    'name'       => $isEn ? ($product->name_product_en ?? $product->name) : $product->name,
                    'description_product' => $isEn ? ($product->description_product_en ?? $product->description_product) : $product->description_product,
                    'price' => [
                        'min' => $product->price_from ? $currencyFunctions->convertAmount('USD', $currency, $product->price_from) : 0,
                        'max' => $product->price_to   ? $currencyFunctions->convertAmount('USD', $currency, $product->price_to)   : 0,
                    ],
                    'price_origin' => $product->price_from,
                    'image'        => asset($product->image),
                ];
            });

            $responseData = [
                'data'          => $formattedProducts,
                'current_page'  => $products->currentPage(),
                'first_page_url'=> $products->url(1),
                'from'          => $products->firstItem(),
                'last_page'     => $products->lastPage(),
                'last_page_url' => $products->url($products->lastPage()),
                'next_page_url' => $products->nextPageUrl(),
                'path'          => $products->url($products->currentPage()),
                'per_page'      => $products->perPage(),
                'prev_page_url' => $products->previousPageUrl(),
                'to'            => $products->lastItem(),
                'total'         => $products->total(),
            ];

            Cache::put($cacheKey, $responseData, now()->addMinutes(5));
            return response()->json($responseData);
        } catch (\Exception $e) {
            report($e);
            return response()->json(['error' => 'Ocurrió un error en el servidor.'], 500);
        }
    }


    public function getAllCategories(Request $request)
    {
        try {
            $TGGlanguage = $request->TGGlanguage ?? 'es';

            $cacheKey = 'categories_v2_' . md5($TGGlanguage);
            $cached   = Cache::get($cacheKey);
            if ($cached !== null) {
                return response()->json($cached);
            }

            $categories = Category::select('id', 'name_category AS name', 'image_category', 'description_category')->get();

            if ($categories->isEmpty()) {
                return response()->json(['message' => 'No se encontraron categorías.'], 404);
            }

            // Category names are translated on the frontend via CATEGORY_NAMES_EN map.
            // We return the raw Spanish names here regardless of language; the frontend
            // handles display translation to avoid per-request Google Translate calls.
            $categoryFormat = $categories->map(fn($item) => [
                'id'          => $item['id'],
                'name'        => $item['name'],
                'description' => $item['description_category'],
                'image'       => $item['image'],
            ]);

            Cache::put($cacheKey, $categoryFormat, now()->addMinutes(30));
            return response()->json($categoryFormat);
        } catch (\Exception $e) {
            report($e);
            return response()->json(['error' => 'Ocurrió un error en el servidor.'], 500);
        }
    }
}
