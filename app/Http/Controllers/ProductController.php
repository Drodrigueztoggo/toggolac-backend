<?php

namespace App\Http\Controllers;

use App\Exports\ProductsExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\AddProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Product;
use App\Models\CategorieProduct;
use App\Models\StoreProduct;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Exception;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Cknow\Money\Money as MoneyConvert;
use Illuminate\Support\Collection;

class ProductController extends Controller
{

    public function getProductsList()
    {
        try {
            $products = Product::with(['storeProducts'])->select('id', 'name_product as name')->get();

            $productsFormat = $products->map(function($product){
                $storeFormat = collect($product['storeProducts'])->map(function ($store) {
                    return [
                        "id" => $store['id'],
                        "name" => $store['name'],
                    ];
                });

                return [
                    "id" => $product['id'],
                    "name" => $product['name'],
                    "stores" => $storeFormat,
                ];

            });

            return response()->json([
                'data' => $productsFormat
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function getProductsListPublic()
    {
        try {
            $products = Product::with('categories', 'brand', 'mall')->get();

            return response()->json(
                $products
            );
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }


    public function downloadProductsExcel()
    {
        try {
            $filter_start_date = request()->query('start_date');
            $filter_end_date = request()->query('end_date');


            $requestFunctionProducts = new Request([
                'filter_start_date' => Carbon::parse($filter_start_date)->format('Y-m-d'),
                'filter_end_date' => Carbon::parse($filter_end_date)->format('Y-m-d'),
                'no_paginate' => true
            ]);

            $productsFormat = $this->getProducts($requestFunctionProducts);

            // return $productsFormat;

            return Excel::download(new ProductsExport($productsFormat), 'Products.xlsx'); // Nombre del archivo Excel

        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'line' => $e->getLine()
            ], 500);
        }
    }


    public function getProducts(Request $request)
    {
        try {

            $TGGlanguage = $request->TGGlanguage;
            $currency = $request->currency;

            $translate = new GoogleTranslateController();
            $currencyFunctions = new CurrencyController();



            $perPage = $request->query('per_page', 20);

            $filter_name = $request->query('name');
            $filter_min_weight = $request->query('min_weight');
            $filter_max_weight = $request->query('max_weight');
            $filter_store_mall_id = $request->query('store_mall_id');
            $filter_brand_id = $request->query('brand_id');
            $filter_category_id = $request->query('category_id');
            $no_paginate = $request->query('no_paginate');

            $filter_start_date = $request->query('filter_start_date');
            $filter_end_date = $request->query('filter_end_date');



            $productsQuery = Product::with(
                'evaluations',
                'categories',
                'brand',
                'storeProducts',
                'mallProducts',
                'cities'
            );

            if (isset($filter_name)) {
                $productsQuery->where('name_product', 'like', '%' . $filter_name . '%');
            }

            if (isset($filter_min_weight) && isset($filter_max_weight)) {
                $productsQuery->whereBetween('weight', [$filter_min_weight, $filter_max_weight]);
            } elseif (isset($filter_min_weight)) {
                $productsQuery->where('weight', '>=', $filter_min_weight);
            } elseif (isset($filter_max_weight)) {
                $productsQuery->where('weight', '<=', $filter_max_weight);
            }
            if (isset($filter_brand_id)) {
                $productsQuery->where('brand_id', $filter_brand_id);
            }



            if (isset($filter_category_id)) {
                $productsQuery->whereHas('categories', function ($query) use ($filter_category_id) {
                    $query->where('categories.id', $filter_category_id);
                });
            }
            if (isset($filter_store_mall_id)) {
                $productsQuery->whereHas('storeProducts', function ($query) use ($filter_store_mall_id) {
                    $query->where('store_mall_id', $filter_store_mall_id);
                });
            }

            if (isset($filter_start_date) && isset($filter_end_date)) {
                $productsQuery->whereDate('created_at', '>=', $filter_start_date)
                    ->whereDate('created_at', '<=', $filter_end_date);
            }


            if ($no_paginate) {
                //NO SE REQUIERE PAGINACION
                $data = $productsQuery->orderBy('created_at', 'desc')->get();
            } else {
                $data = $productsQuery->orderBy('created_at', 'desc')->paginate($perPage);
            }

            $products = $data->map(function ($prod) use ($TGGlanguage, $translate,  $currencyFunctions, $currency) {


                $rating =  isset($prod->evaluations) && count($prod->evaluations) > 0 ? $prod->evaluations->avg('rating') : 0;


                $collection = new Collection($prod['cities']);



                $categoriesFormat = collect($prod['categories'])->map(function ($category) use ($translate, $TGGlanguage) {
                    return [
                        "id" => $category['id'],
                        "name" => $TGGlanguage != 'es' ? $translate->translateText($category['name'], $TGGlanguage) : $category['name'],
                    ];
                });

                $storeFormat = collect($prod['storeProducts'])->map(function ($store) use ($translate, $TGGlanguage) {
                    return [
                        "id" => $store['id'],
                        "name" => $TGGlanguage != 'es' ? $translate->translateText($store['name'], $TGGlanguage) : $store['name'],
                    ];
                });

                $mallFormat = collect($prod['mallProducts'])->map(function ($mall) use ($translate, $TGGlanguage) {
                    return [
                        "id" => $mall['id'],
                        "name" => $TGGlanguage != 'es' ? $translate->translateText($mall['name'], $TGGlanguage) : $mall['name'],
                    ];
                });


                return [
                    "id" => $prod['id'],
                    "rating" => $rating,
                    "created_at" => $prod['created_at'],
                    "name_product" => $TGGlanguage != 'es' ? ($prod['name_product_en'] ?? $prod['name_product']) : $prod['name_product'],
                    "description_product" => $TGGlanguage != 'es' ? ($prod['description_product_en'] ?? $prod['description_product']) : $prod['description_product'],
                    // "price_from" => $prod['price_from'],
                    // "price_to" => $prod['price_to'],

                    'price' => [
                        'min' => $prod['price_from'] ?  $currencyFunctions->convertAmount('USD', $currency, $prod['price_from']) : 0,
                        'max' => $prod['price_to'] ? $currencyFunctions->convertAmount('USD', $currency, $prod['price_to']) : 0
                    ],
                    'price_edit' => [
                        'min' => $prod['price_from'],
                        'max' => $prod['price_to']
                    ],
                    "price_origin" => $prod['price_from'],
                    "weight" => $prod['weight'],
                    "brand_id" => $prod['brand_id'],
                    "mall_id" => $prod['mall_id'],
                    "created_at" => $prod['created_at'],
                    "updated_at" => $prod['updated_at'],
                    "image" => $prod['image'],
                    "categories" => $categoriesFormat,
                    "brand" => isset($prod['brand']) ? [
                        "id" => $prod['brand']['id'],
                        "name_brand" => $TGGlanguage != 'es' ? $translate->translateText($prod['brand']['name_brand'], $TGGlanguage) : $prod['brand']['name_brand'],
                        "description_brand" => $TGGlanguage != 'es' ? $translate->translateText($prod['brand']['description_brand'], $TGGlanguage) : $prod['brand']['description_brand'],
                        "image" => $prod['brand']['image'],
                    ] : null,
                    "store_products" => $storeFormat,
                    "mall_products" => $mallFormat,
                    "cities" => $collection->unique('id')->values()->all()
                ];
            });


            if ($no_paginate) {
                //NO SE REQUIERE PAGINACION
                $responseData = $products;
            } else {
                $responseData = [
                    "data" => $products,
                    'current_page' => $data->currentPage(),
                    'first_page_url' => $data->url(1),
                    'from' => $data->firstItem(),
                    'last_page' => $data->lastPage(),
                    'last_page_url' => $data->url($data->lastPage()),
                    'next_page_url' => $data->nextPageUrl(),
                    'path' => $data->url($data->currentPage()),
                    'per_page' => $data->perPage(),
                    'prev_page_url' => $data->previousPageUrl(),
                    'to' => $data->lastItem(),
                    'total' => $data->total(),
                ];
            }



            return $responseData;
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function addProduct(AddProductRequest $request)
    {
        try {
            DB::beginTransaction();


            // Buscar si ya existe una marca eliminada con el mismo nombre
            $existingProduct = Product::withTrashed()
                ->where('name_product', $request->name_product)
                ->whereNotNull('deleted_at') // Solo marcas eliminadas
                ->first();

            if ($existingProduct) {
                $existingProduct->name_product = $request->name_product;
                $existingProduct->description_product = $request->description_product;
                $existingProduct->price_from = $request->price_from;
                $existingProduct->price_to = $request->price_to;
                $existingProduct->weight = $request->weight;
                $existingProduct->brand_id = $request->brand_id;
                $existingProduct->mall_id = $request->mall_id;
                if ($request->hasFile('image_product')) {
                    $existingProduct->image_product = $this->storeImage($request->file('image_product'));
                }
                $existingProduct->restore(); // Restaurar el producto


                if ($request->has('selected_categories')) {
                    $existingProduct->categoriesRelation()->detach();

                    $selectedCategories = $request->input('selected_categories');
                    $existingProduct->categoriesRelation()->sync($selectedCategories);
                }
                if ($request->has('selected_malls')) {
                    $existingProduct->mallProductsRelation()->detach();

                    $selectedMalls = $request->input('selected_malls');
                    $existingProduct->mallProductsRelation()->sync($selectedMalls);
                }
                if ($request->has('selected_stores')) {
                    $existingProduct->storeProductsRelation()->detach();

                    $selectedStores = $request->input('selected_stores');
                    $existingProduct->storeProductsRelation()->sync($selectedStores);
                }

                DB::commit();
                return response()->json($existingProduct, 201);
            }

            $productData = $request->all();

            if ($request->hasFile('image_product')) {
                $imagePath = $this->storeImage($request->file('image_product'));
                $productData['image_product'] = $imagePath;
            }

            $product = Product::create($productData);

            // Relacionar las categorías seleccionadas con el producto
            if ($request->has('selected_categories')) {
                $selectedCategories = $request->input('selected_categories');
                $product->categoriesRelation()->attach($selectedCategories);
            }

            if ($request->has('selected_malls')) {
                $selectedMalls = $request->input('selected_malls');
                $product->mallProductsRelation()->sync($selectedMalls);
            }

            // Relacionar las tiendas seleccionadas con el producto
            if ($request->has('selected_stores')) {
                $selectedStores = $request->input('selected_stores');
                $product->storeProductsRelation()->attach($selectedStores);
            }

            DB::commit();

            return response()->json($product, 201);
        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function showProduct(Request $request)
    {
        try {
            $id = $request->id;

            $translate = new GoogleTranslateController();
            $TGGlanguage = $request->TGGlanguage;

            $currencyFunctions = new CurrencyController();
            $currency = $request->currency;



            $product = Product::with('evaluations', 'categories', 'mallProducts', 'storeProducts')->findOrFail($id);
            $rating =  isset($product->evaluations) && count($product->evaluations) > 0 ? $product->evaluations->avg('rating') : 0;

            $product['rating'] = $rating;
            
            if (isset($product["categories"])) {
                $categories = collect($product["categories"])
                    ->map(function ($category) use ($TGGlanguage, $translate) {
                        return [
                            'id' => $category->id,
                            "name" => $TGGlanguage != 'es' ? $translate->translateText($category->name, $TGGlanguage) : $category->name,
                        ];
                    });
            } else {
                $categories = null;
            }

            if (isset($product["mallProducts"])) {
                $malls = collect($product["mallProducts"])
                    ->map(function ($category) use ($TGGlanguage, $translate) {
                        return [
                            'id' => $category->id,
                            'image' => $category->image,
                            "name" => $TGGlanguage != 'es' ? $translate->translateText($category->name, $TGGlanguage) : $category->name,
                        ];
                    });
            } else {
                $malls = null;
            }


            $response = [
                "id" => $product["id"],
                "name_product" => $TGGlanguage != 'es' ? ($product["name_product_en"] ?? $product["name_product"]) : $product["name_product"],
                "description_product" => $TGGlanguage != 'es' ? ($product["description_product_en"] ?? $product["description_product"]) : $product["description_product"],
                "price_from" => $currencyFunctions->convertAmount('USD', $currency, $product["price_from"] ? $product["price_from"]: 0),
                "price_to" => $currencyFunctions->convertAmount('USD', $currency, $product["price_to"] ? $product["price_to"]: 0),
                "weight" => $product["weight"],
                "price_origin" => $product["price_from"] ,
                "brand_id" => $product["brand_id"],
                "mall_id" => $product["mall_id"],
                "image_product" => $product["image_product"],
                "created_at" => $product["created_at"],
                "updated_at" => $product["updated_at"],
                "deleted_at" => $product["deleted_at"],
                "rating" => $product["rating"],
                "image" => $product["image"],
                "evaluations" => $product["evaluations"],
                "categories" => $categories,
                "mall_products" => $malls,
                "stores" => isset($product->storeProducts) ? $product->storeProducts: null
            ];


            return response()->json($response);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function showProductPublic(Request $request)
    {
        try {

            $TGGlanguage = $request->TGGlanguage;
            $currency = $request->currency;

            $translate = new GoogleTranslateController();
            $currencyFunctions = new CurrencyController();

            $id = $request->id;

            $product = Product::select()->with('evaluations', 'categories', 'mallProducts', 'storeProducts')->findOrFail($id);
            $rating =  isset($product->evaluations) && count($product->evaluations) > 0 ? $product->evaluations->avg('rating') : 0;

            $data = null;

            if(isset($product)){
                $data = [
                    "id" => $product->id,
                    "rating" => $rating,
                    "name_product" => $TGGlanguage != 'es' ? ($product->name_product_en ?? $product->name_product) : $product->name_product,
                    "description_product" => $TGGlanguage != 'es' ? ($product->description_product_en ?? $product->description_product) : $product->description_product,
                    "price_from" => isset($product->price_from) ? $currencyFunctions->convertAmount('USD', $currency, $product->price_from) : 0,
                    "price_to" => isset($product->price_to) ? $currencyFunctions->convertAmount('USD', $currency, $product->price_to) : 0,
                    "weight" => $product->weight,
                    "price_origin" => $product->price_from,
                    "brand_id" => $product->brand_id,
                    "mall_id" => $product->mall_id,
                    "image" => $product->image,
                    "created_at" => $product->created_at,
                    "categories" => isset($product->categories) ? $product->categories : null,
                    "mall_products" => isset($product->mallProducts) ? $product->mallProducts : null,
                    "stores" => isset($product->storeProducts) ? $product->storeProducts: null
                ];
            }

            return response()->json($data);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function updateProduct(UpdateProductRequest $request)
    {
        try {
            DB::beginTransaction();
            $id = $request->id;


            // Buscar si ya existe una marca eliminada con el mismo nombre
            $existingProduct = Product::withTrashed()
                ->where('name_product', $request->name_product)
                ->whereNotNull('deleted_at') // Solo marcas eliminadas
                ->first();

            if ($existingProduct) {
                $existingProduct->name_product = $request->name_product;
                $existingProduct->description_product = $request->description_product;
                $existingProduct->price_from = $request->price_from;
                $existingProduct->price_to = $request->price_to;
                $existingProduct->weight = $request->weight;
                $existingProduct->brand_id = $request->brand_id;
                $existingProduct->mall_id = $request->mall_id;
                if ($request->hasFile('image_product')) {
                    $existingProduct->image_product = $this->storeImage($request->file('image_product'));
                }
                $existingProduct->restore(); // Restaurar el producto


                if ($request->has('selected_categories')) {
                    $existingProduct->categoriesRelation()->detach();

                    $selectedCategories = $request->input('selected_categories');
                    $existingProduct->categoriesRelation()->sync($selectedCategories);
                }

                if ($request->has('selected_malls')) {
                    $existingProduct->mallProductsRelation()->detach();

                    $selectedMalls = $request->input('selected_malls');
                    $existingProduct->mallProductsRelation()->sync($selectedMalls);
                }

                if ($request->has('selected_stores')) {
                    $existingProduct->storeProductsRelation()->detach();

                    $selectedStores = $request->input('selected_stores');
                    $existingProduct->storeProductsRelation()->sync($selectedStores);
                }

                $this->deleteProduct($id); // se elimina el producto

                DB::commit();
                return response()->json($existingProduct, 201);
            } else {


                $product = Product::findOrFail($id);

                $productData = $request->all();

                if ($request->hasFile('image_product')) {
                    $this->deleteImage($product->image_product);
                    $imagePath = $this->storeImage($request->file('image_product'));
                    $productData['image_product'] = $imagePath;
                }

                $product->update($productData);

                // Sincronizar las categorías seleccionadas con el producto
                if ($request->has('selected_categories')) {
                    $selectedCategories = $request->input('selected_categories');
                    $product->categoriesRelation()->sync($selectedCategories);
                } else {
                    // Si no se seleccionaron categorías, desvincular todas las existentes
                    $product->categoriesRelation()->detach();
                }


                if ($request->has('selected_malls')) {
                    $selectedMalls = $request->input('selected_malls');
                    $product->mallProductsRelation()->sync($selectedMalls);
                } else {
                    // Si no se seleccionaron tiendas, desvincular todas las existentes
                    $product->mallProductsRelation()->detach();
                }

                // Relacionar las tiendas seleccionadas con el producto
                if ($request->has('selected_stores')) {
                    $selectedStores = $request->input('selected_stores');
                    $product->storeProductsRelation()->sync($selectedStores);
                } else {
                    // Si no se seleccionaron tiendas, desvincular todas las existentes
                    $product->storeProductsRelation()->detach();
                }


                DB::commit();
                return response()->json($product, 200);
            }
        } catch (Exception $e) {

            DB::rollBack();

            if (!isset($product)) {
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

    public function deleteProduct($id)
    {
        try {
            $product = Product::findOrFail($id);
            //$this->deleteImage($product->image_product);
            $product->categoriesRelation()->detach();
            $product->mallProductsRelation()->detach();  // Utilizar detach() en lugar de delete()
            $product->storeProductsRelation()->detach();  // Utilizar detach() en lugar de delete()

            $product->delete();

            return [
                'status' => 'success',
                'message' => 'Elimination is confirmed'
            ];
        } catch (Exception $e) {

            if (!isset($product)) {
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
        $imageName = uniqid() . '_' . now()->format('YmdHis') . '.' . $image->getClientOriginalExtension();
        $imagePath = $image->storeAs('uploads/product_images/' . now()->format('Y-m-d'), $imageName, 'public');
        return $imagePath;
    }

    private function deleteImage($imagePath)
    {
        if ($imagePath && Storage::exists('public/' . $imagePath)) {
            Storage::delete('public/' . $imagePath);
        }
    }
}
