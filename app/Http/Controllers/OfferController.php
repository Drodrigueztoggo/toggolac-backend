<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\AddOfferRequest;
use App\Http\Requests\UpdateOfferRequest;
use App\Models\Offer;
use App\Models\Product;
use App\Models\Brand;
use App\Models\StoreMall;
use App\Models\Mall;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Exception;
use Cknow\Money\Money as MoneyConvert;

class OfferController extends Controller
{

    public function getOfferListPublic(Request $request)
    {
        try {

            $translate = new GoogleTranslateController();
            $currencyFunctions = new CurrencyController();

            $TGGlanguage = $request->TGGlanguage;
            $currency = $request->currency;

            $malls = array();
            $stores = array();
            $brands = array();
            $products = array();
            $currentDate = now();

            $offers = Offer::whereNull('deleted_at')
                ->whereNotNull('product_id')
                ->where('start_date', '<=', $currentDate)
                ->where('end_date', '>=', $currentDate)
                ->get();

            foreach ($offers as $key => $offer) {
                $product = Product::where('id', $offer->product_id)->with(
                    'evaluations',
                    'categories',
                    'brand',
                    'mallProducts.countryInfo'
                )->first();
                if (!is_null($product)) {

                    $rating =  isset($product->evaluations) && count($product->evaluations) > 0 ? $product->evaluations->avg('rating') : 0;

                    $product['offert_id'] = $offer->id;
                    $product['rating'] = $rating;
                    $product['off_discount_percentage_from'] = $offer->discount_percentage_from;
                    $product['off_discount_percentage_to'] = $offer->discount_percentage_to;
                    $product['off_discount_price_from'] = $offer->discount_price_from;
                    $product['off_discount_price_to'] = $offer->discount_price_to;
                    $product['image_offert'] = isset($offer->image_offert) ? url('storage/' . $offer->image_offert) :
                        null;
                    array_push($products, $product);
                }


                $brand = Brand::where('id', $offer->brand_id)->first();
                if (!is_null($brand)) {
                    $brand['id'] = $offer->id;
                    $brand['off_discount_percentage_from'] = $offer->discount_percentage_from;
                    $brand['off_discount_percentage_to'] = $offer->discount_percentage_to;
                    $brand['off_discount_price_from'] = $offer->discount_price_from;
                    $brand['off_discount_price_to'] = $offer->discount_price_to;
                    $brand['image_offert'] = isset($offer->image_offert) ? url('storage/' . $offer->image_offert) :
                        null;
                    array_push($brands, $brand);
                }

                $store = StoreMall::where('id', $offer->store_mall_id)->first();
                if (!is_null($store)) {
                    $store['id'] = $offer->id;
                    $store['off_discount_percentage_from'] = $offer->discount_percentage_from;
                    $store['off_discount_percentage_to'] = $offer->discount_percentage_to;
                    $store['off_discount_price_from'] = $offer->discount_price_from;
                    $store['off_discount_price_to'] = $offer->discount_price_to;
                    $store['image_offert'] = isset($offer->image_offert) ? url('storage/' . $offer->image_offert) :
                        null;
                    array_push($stores, $store);
                }

                $mall = Mall::where('id', $offer->mall_id)->first();
                if (!is_null($mall)) {
                    $mall['id'] = $offer->id;
                    $mall['off_discount_percentage_from'] = $offer->discount_percentage_from;
                    $mall['off_discount_percentage_to'] = $offer->discount_percentage_to;
                    $mall['off_discount_price_from'] = $offer->discount_price_from;
                    $mall['off_discount_price_to'] = $offer->discount_price_to;
                    $mall['image_offert'] = isset($offer->image_offert) ? url('storage/' . $offer->image_offert) :
                        null;
                    array_push($malls, $mall);
                }
            }

            // return $products; 
            $formattedProducts = collect($products)->map(function ($product) use ($TGGlanguage, $translate, $currencyFunctions, $currency) {

                $uniqueCountries = null;
                $malls = null;
                $categories = null;

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
                                'country_info' => $mall->countryInfo,
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
                    'rating' => $product->rating,
                    'offert_id' => $product->offert_id,
                    'off_discount_percentage_from' => $product->off_discount_percentage_from,
                    'off_discount_percentage_to' => $product->off_discount_percentage_to,
                    'off_discount_price_from' => isset($product->off_discount_price_from) ? $currencyFunctions->convertAmount('USD', $currency, $product->off_discount_price_from) : 0,
                    'off_discount_price_to' => isset($product->off_discount_price_to) ? $currencyFunctions->convertAmount('USD', $currency, $product->off_discount_price_to) : 0,
                    'categories' => $categories,
                    'brand' => isset($product->brand) ? [
                        "id" => $product->brand->id,
                        "name_brand" => $TGGlanguage != 'es' ? $translate->translateText($product->brand->name_brand, $TGGlanguage) : $product->brand->name_brand,
                        "image" => $product->brand->image
                    ] : null,
                    'mall_products' => $malls,
                    'countries' => $uniqueCountries,
                    "name_product" => $TGGlanguage != 'es' ? $translate->translateText($product->name_product, $TGGlanguage) : $product->name_product,
                    "description_product" => $TGGlanguage != 'es' ? $translate->translateText($product->description_product, $TGGlanguage) : $product->description_product,
                    'price' => [
                        'min' => $product->price_from ?  $currencyFunctions->convertAmount('USD', $currency, $product->price_from) : 0,
                        'max' => $product->price_to ? $currencyFunctions->convertAmount('USD', $currency, $product->price_to) : 0
                    ],
                    // 'image_product' => $product->image,
                    'image' => asset($product->image)
                ];
            });


            $mallsFormat = collect($malls)
                ->map(function ($mall) use ($TGGlanguage, $translate, $currencyFunctions, $currency) {

                    return [
                        "id" => $mall->id,
                        "name_mall" => $TGGlanguage != 'es' ? $translate->translateText($mall->name_mall, $TGGlanguage) : $mall->name_mall,
                        "country_id" => $mall->country_id,
                        "state_id" => $mall->state_id,
                        "city_id" => $mall->city_id,
                        "postal_code" => $mall->postal_code,
                        "address" => $mall->address,
                        "num_phone" => $mall->num_phone,
                        "image" => $mall->image,
                        "image_offert" => $mall->image_offert,
                        "off_discount_percentage_from" => $mall->off_discount_percentage_from,
                        "off_discount_percentage_to" => $mall->off_discount_percentage_to,
                        'off_discount_price_from' => isset($mall->off_discount_price_from) ? $currencyFunctions->convertAmount('USD', $currency, $mall->off_discount_price_from) : 0,
                        'off_discount_price_to' => isset($mall->off_discount_price_to) ? $currencyFunctions->convertAmount('USD', $currency, $mall->off_discount_price_to) : 0,


                    ];
                });

            $storesFormat = collect($stores)
                ->map(function ($store) use ($TGGlanguage, $translate, $currencyFunctions, $currency) {

                    return [
                        "id" => $store->id,
                        "store" => $TGGlanguage != 'es' ? $translate->translateText($store->store, $TGGlanguage) : $store->store,
                        "address" => $store->address,
                        "num_phone" => $store->num_phone,
                        "mall_id" => $store->mall_id,
                        "image_offert" => $store->image_offert,
                        "image" => $store->image,
                        "off_discount_percentage_from" => $store->off_discount_percentage_from,
                        "off_discount_percentage_to" => $store->off_discount_percentage_to,
                        'off_discount_price_from' => isset($store->off_discount_price_from) ? $currencyFunctions->convertAmount('USD', $currency, $store->off_discount_price_from) : 0,
                        'off_discount_price_to' => isset($store->off_discount_price_to) ? $currencyFunctions->convertAmount('USD', $currency, $store->off_discount_price_to) : 0,


                    ];
                });

            $brandsFormat = collect($brands)
                ->map(function ($brand) use ($TGGlanguage, $translate, $currencyFunctions, $currency) {

                    return [
                        "id" => $brand->id,
                        "name_brand" => $TGGlanguage != 'es' ? $translate->translateText($brand->name_brand, $TGGlanguage) : $brand->name_brand,
                        "description_brand" => $TGGlanguage != 'es' ? $translate->translateText($brand->description_brand, $TGGlanguage) : $brand->description_brand,
                        "country_id" => $brand->country_id,
                        "state_id" => $brand->state_id,
                        "city_id" => $brand->city_id,
                        "mall_id" => $brand->mall_id,
                        "image_offert" => $brand->image_offert,
                        "image" => $brand->image,
                        "off_discount_percentage_from" => $brand->off_discount_percentage_from,
                        "off_discount_percentage_to" => $brand->off_discount_percentage_to,
                        'off_discount_price_from' => isset($brand->off_discount_price_from) ? $currencyFunctions->convertAmount('USD', $currency, $brand->off_discount_price_from) : 0,
                        'off_discount_price_to' => isset($brand->off_discount_price_to) ? $currencyFunctions->convertAmount('USD', $currency, $brand->off_discount_price_to) : 0,

                    ];
                });

            return response()->json([
                'products' => $formattedProducts,
                'malls' => $mallsFormat,
                'stores' => $storesFormat,
                'brands' => $brandsFormat
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function getOfferList()
    {
        try {
            $offers = Offer::select('id', 'name')->get();
            return response()->json([
                'data' => $offers
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function getOfferDetail($id)
    {
        try {

            $currentDate = now();

            $additionalOffer = Offer::with('product.brand', 'storeMall')
                ->where('id', $id)
                ->whereNull('deleted_at')
                ->whereNotNull('product_id')
                ->where('start_date', '<=', $currentDate)
                ->where('end_date', '>=', $currentDate)
                ->inRandomOrder()
                ->first(['id', 'name', 'description', 'product_id', 'end_date', 'discount_price_from', 'discount_price_to', 'discount_percentage_from', 'discount_percentage_to', 'store_mall_id']);



            // Obtener 4 ofertas aleatorias
            $offers = Offer::where('start_date', '<=', $currentDate)
                ->where('end_date', '>=', $currentDate)
                ->inRandomOrder()
                ->limit(4);
            if (isset($additionalOffer->id)) {
                $offers->where('id', '!=', $additionalOffer->id);
            }

            $offers = $offers->select('id', 'name', 'image_offert')
                ->get();

            if (isset($additionalOffer)) {
                $additionalOffer['price'] = [
                    'min' => $additionalOffer->discount_price_from ? MoneyConvert::USD($additionalOffer->discount_price_from) : null,
                    'max' => $additionalOffer->discount_price_to ? MoneyConvert::USD($additionalOffer->discount_price_to) : null
                ];

                $additionalOffer['percentage'] = $additionalOffer->discount_percentage_from . "% - " . $additionalOffer->discount_percentage_to . "%";


                if (isset($additionalOffer['product'])) {
                    $additionalOffer['product']['price'] = [
                        'min' => isset($additionalOffer->product->price_from) ? MoneyConvert::USD($additionalOffer->product->price_from) : null,
                        'max' => isset($additionalOffer->product->price_to) ? MoneyConvert::USD($additionalOffer->product->price_to) : null
                    ];


                    // unset($additionalOffer['product']['image_product']);
                    unset($additionalOffer['product']['brand_id']);
                    unset($additionalOffer['product']['price_from']);
                    unset($additionalOffer['product']['price_to']);
                }
            }

            return response()->json($additionalOffer);
        } catch (\Exception $e) {
            // Manejar cualquier excepción y devolver una respuesta de error
            return response()->json(['error' => 'Ocurrió un error en el servidor.'], 500);
        }
    }

    public function getOffers(Request $request)
    {
        try {

            $translate = new GoogleTranslateController();
            $TGGlanguage = $request->TGGlanguage;

            $currencyFunctions = new CurrencyController();
            $currency = $request->currency;


            $filter_mall_id = $request->query('mall_id');
            $filter_store_mall_id = $request->query('store_mall_id');
            $filter_brand_id = $request->query('brand_id');
            $filter_product_id = $request->query('product_id');

            $perPage = $request->query('per_page', 20);

            $query = Offer::with('country', 'mall', 'storeMall', 'brand', 'product.evaluations');

            if ($filter_mall_id) {
                $query->where('mall_id', $filter_mall_id);
            }

            if ($filter_store_mall_id) {
                $query->where('store_mall_id', $filter_store_mall_id);
            }

            if ($filter_brand_id) {
                $query->where('brand_id', $filter_brand_id);
            }

            if ($filter_product_id) {
                $query->where('product_id', $filter_product_id);
            }

            $offers = $query->paginate($perPage);


            $offersFormat = $offers->map(function ($offer) use ($TGGlanguage, $translate,  $currencyFunctions, $currency) {

                $rating = 0;
                
                if(isset($offer['product'])){
                    $rating =  isset($offer['product']['evaluations']) && count($offer['product']['evaluations']) > 0 ? $offer['product']['evaluations']->avg('rating') : 0;
                }


                return [
                    "id" => $offer['id'],
                    "name" => $TGGlanguage != 'es' ? $translate->translateText($offer['name'], $TGGlanguage) : $offer['name'],
                    "description" => $TGGlanguage != 'es' ? $translate->translateText($offer['description'], $TGGlanguage) : $offer['description'],
                    "discount_percentage_from" => $offer['discount_percentage_from'],
                    "discount_percentage_to" => $offer['discount_percentage_to'],
                    "discount_price_from" => $currencyFunctions->convertAmount('USD', $currency, $offer['discount_price_from'] ? $offer['discount_price_from'] : 0),
                    "discount_price_to" => $currencyFunctions->convertAmount('USD', $currency, $offer['discount_price_to'] ? $offer['discount_price_to'] : 0),
                    "image_offert" => $offer['image_offert'],
                    "start_date" => $offer['start_date'],
                    "end_date" => $offer['end_date'],
                    "country_id" => $offer['country_id'],
                    "mall_id" => $offer['mall_id'],
                    "store_mall_id" => $offer['store_mall_id'],
                    "brand_id" => $offer['brand_id'],
                    "product_id" => $offer['product_id'],
                    "created_at" => $offer['created_at'],
                    "updated_at" => $offer['updated_at'],
                    "deleted_at" => $offer['deleted_at'],
                    "image" => $offer['image'],
                    "country" => $offer['country'],
                    "mall" => isset($offer['mall']) ? [
                        "id" => $offer['mall']['id'],
                        "name" => $TGGlanguage != 'es' ? $translate->translateText($offer['mall']['name'], $TGGlanguage) : $offer['mall']['name'],
                    ] : null,
                    "store_mall" => isset($offer['storeMall']) ? [
                        "id" => $offer['storeMall']['id'],
                        "name" => $TGGlanguage != 'es' ? $translate->translateText($offer['storeMall']['name'], $TGGlanguage) : $offer['storeMall']['name'],
                    ] : null,
                    "brand" => isset($offer['brand']) ? [
                        "id" => $offer['brand']['id'],
                        "image_brand" =>isset($offer['brand']['image_brand']) ? $offer['brand']['image_brand'] : null,
                        "image" => isset($offer['brand']['image']) ? $offer['brand']['image'] : null,
                        "name" => $TGGlanguage != 'es' ? $translate->translateText($offer['brand']['name'], $TGGlanguage) : $offer['brand']['name'],
                    ] : null,
                    "product" => isset($offer['product']) ? [
                        "id" => $offer['product']['id'],
                        "name" => $TGGlanguage != 'es' ? $translate->translateText($offer['product']['name'], $TGGlanguage) : $offer['product']['name'],
                        "image_product" => $offer['product']['image_product'],
                        "brand_id" => $offer['product']['brand_id'],
                        "price_from" => $currencyFunctions->convertAmount('USD', $currency, $offer['product']['price_from'] ? $offer['product']['price_from'] : 0),
                        "price_to" => $currencyFunctions->convertAmount('USD', $currency, $offer['product']['price_to'] ? $offer['product']['price_to'] : 0),
                        "image" => $offer['product']['image'],
                        "rating" => $rating
                    ] : null,
                ];
            });

            $responseData = [
                "data" => $offersFormat,
                'current_page' => $offers->currentPage(),
                'first_page_url' => $offers->url(1),
                'from' => $offers->firstItem(),
                'last_page' => $offers->lastPage(),
                'last_page_url' => $offers->url($offers->lastPage()),
                'next_page_url' => $offers->nextPageUrl(),
                'path' => $offers->url($offers->currentPage()),
                'per_page' => $offers->perPage(),
                'prev_page_url' => $offers->previousPageUrl(),
                'to' => $offers->lastItem(),
                'total' => $offers->total(),
            ];


            return response()->json($responseData);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }


    public function addOffer(AddOfferRequest $request)
    {
        try {
            $offerData = $request->all();

            if ($request->hasFile('image_offert')) {
                $imagePath = $this->storeImage($request->file('image_offert'));
                $offerData['image_offert'] = $imagePath;
            }

            $offer = Offer::create($offerData);

            // Cargar las relaciones que deseas incluir en la respuesta JSON
            $offer->load('country', 'mall', 'storeMall', 'brand', 'product');


            return response()->json([
                'offer' => $offer,
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }


    public function showOffer(Request $request)
    {
        try {

            $translate = new GoogleTranslateController();
            $TGGlanguage = $request->TGGlanguage;

            $currencyFunctions = new CurrencyController();
            $currency = $request->currency;

            $offer = Offer::with('country', 'mall', 'storeMall', 'brand', 'product')->findOrFail($request->id);


            
            $offerFormat = [
                "id" => $offer['id'],
                "name" => $TGGlanguage != 'es' ? $translate->translateText($offer['name'], $TGGlanguage) : $offer['name'],
                "description" => $TGGlanguage != 'es' ? $translate->translateText($offer['description'], $TGGlanguage) : $offer['description'],
                "discount_percentage_from" => $offer['discount_percentage_from'],
                "discount_percentage_to" => $offer['discount_percentage_to'],
                "discount_price_from" => $currencyFunctions->convertAmount('USD', $currency, $offer['discount_price_from'] ? $offer['discount_price_from'] : 0),
                "discount_price_to" => $currencyFunctions->convertAmount('USD', $currency, $offer['discount_price_to'] ? $offer['discount_price_to'] : 0),
                "image_offert" => $offer['image_offert'],
                "start_date" => $offer['start_date'],
                "end_date" => $offer['end_date'],
                "country_id" => $offer['country_id'],
                "mall_id" => $offer['mall_id'],
                "store_mall_id" => $offer['store_mall_id'],
                "brand_id" => $offer['brand_id'],
                "product_id" => $offer['product_id'],
                "created_at" => $offer['created_at'],
                "updated_at" => $offer['updated_at'],
                "deleted_at" => $offer['deleted_at'],
                "image" => $offer['image'],
                "country" => $offer['country'],
                "mall" => isset($offer['mall']) ? [
                    "id" => $offer['mall']['id'],
                    "name" => $TGGlanguage != 'es' ? $translate->translateText($offer['mall']['name'], $TGGlanguage) : $offer['mall']['name'],
                ] : null,
                "store_mall" => isset($offer['storeMall']) ? [
                    "id" => $offer['storeMall']['id'],
                    "name" => $TGGlanguage != 'es' ? $translate->translateText($offer['storeMall']['name'], $TGGlanguage) : $offer['storeMall']['name'],
                ] : null,
                "brand" => isset($offer['brand']) ? [
                    "id" => $offer['brand']['id'],
                    "name" => $TGGlanguage != 'es' ? $translate->translateText($offer['brand']['name'], $TGGlanguage) : $offer['brand']['name'],
                ] : null,
                "product" => isset($offer['product']) ? [
                    "id" => $offer['product']['id'],
                    "name" => $TGGlanguage != 'es' ? $translate->translateText($offer['product']['name'], $TGGlanguage) : $offer['product']['name'],
                    "image_product" => $offer['product']['image_product'],
                    "brand_id" => $offer['product']['brand_id'],
                    "price_from" => $currencyFunctions->convertAmount('USD', $currency, $offer['product']['price_from'] ? $offer['product']['price_from'] : 0),
                    "price_to" => $currencyFunctions->convertAmount('USD', $currency, $offer['product']['price_to'] ? $offer['product']['price_to'] : 0),
                    "image" => $offer['product']['image'],
                ] : null,
            ];


            return response()->json($offerFormat);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }


    public function updateOffer(UpdateOfferRequest $request)
    {
        try {
            $id = $request->id;
            $offer = Offer::findOrFail($id);

            if ($request->hasFile('image_offert')) {
                $this->deleteImage($offer->image_offert);
                $imagePath = $this->storeImage($request->file('image_offert'));
            }

            $offer->update($request->all());
            if (isset($imagePath)) {
                $offer->image_offert = $imagePath;
            }
            $offer->save();

            return response()->json($offer, 200);
        } catch (Exception $e) {

            if (!isset($offer)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'El id ' . $id . ' No se encuentra registrado.'
                ], 404);
            }

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }


    public function deleteOffer($id)
    {
        try {
            $offer = Offer::findOrFail($id);
            $this->deleteImage($offer->image_offert);
            $offer->delete();

            return [
                'status' => 'success',
                'message' => 'Elimination is confirmed'
            ];
        } catch (Exception $e) {

            if (!isset($offer)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'El id ' . $id . ' No se encuentra registrado.'
                ], 404);
            }

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    private function storeImage($image)
    {
        try {
            $imageName = uniqid() . '_' . now()->format('YmdHis') . '.' . $image->getClientOriginalExtension();
            $imagePath = $image->storeAs('uploads/offer_images/' . now()->format('Y-m-d'), $imageName, 'public');
            return $imagePath;
        } catch (Exception $e) {
            throw new Exception('Error al guardar la imagen: ' . $e->getMessage());
        }
    }

    private function deleteImage($imagePath)
    {
        try {
            if ($imagePath && Storage::exists('public/' . $imagePath)) {
                Storage::delete('public/' . $imagePath);
            }
        } catch (Exception $e) {
            throw new Exception('Error al eliminar la imagen: ' . $e->getMessage());
        }
    }
}
