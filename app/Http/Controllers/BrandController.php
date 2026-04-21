<?php

namespace App\Http\Controllers;

use App\Exports\BrandExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\AddBrandRequest;
use App\Http\Requests\UpdateBrandRequest;
use App\Models\Brand;
use App\Models\BrandCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Exception;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class BrandController extends Controller
{

    public function getBrandsList(Request $request)
    {
        try {
            $TGGlanguage = $request->TGGlanguage;
            $translate = new GoogleTranslateController();


            $brands = Brand::select('id', 'name_brand as name')->get();


            $brandsFormat = $brands->map(function ($item) use ($translate, $TGGlanguage) {
                return [
                    "id" => $item['id'],
                    "name" => $TGGlanguage != 'es' ? $translate->translateText($item['name'], $TGGlanguage) : $item['name'],
                ];
            });



            return response()->json([
                'data' => $brandsFormat
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function getBrandsListPublic(Request $request)
    {
        try {
            $TGGlanguage = $request->TGGlanguage;
            $translate = new GoogleTranslateController();


            $brands = Brand::select('id', 'name_brand AS name')->get();


            $brandsFormat = $brands->map(function ($item) use ($translate, $TGGlanguage) {
                return [
                    "id" => $item['id'],
                    "name" => $TGGlanguage != 'es' ? $translate->translateText($item['name'], $TGGlanguage) : $item['name'],
                ];
            });



            return response()->json($brandsFormat);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }



    public function downloadBrandExcel(Request $request)
    {
        try {

            $filter_start_date = $request->start_date;
            $filter_end_date = $request->end_date;

            $requestFunctionGetUsers = new Request([
                'role_id' => 2,
                'no_paginate' => true,
                'sh_filter_start_date' => $filter_start_date,
                'sh_filter_end_date' => $filter_end_date,
            ]);
            $data =  $this->getBrands($requestFunctionGetUsers);


            return Excel::download(new BrandExport($data), 'brand.xlsx'); // Nombre del archivo Excel

        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    public function getBrands(Request $request)
    {
        try {
            $TGGlanguage = $request->TGGlanguage;
            $translate = new GoogleTranslateController();


            $perPage = $request->per_page ? $request->per_page : 20;

            $filter_name = $request->name;
            $filter_mall_id = $request->mall_id;
            $filter_country_id = $request->country_id;
            $filter_city_id = $request->city_id;
            $filter_category_id = $request->category_id;
            $filter_store_mall_id = $request->store_mall_id;


            $filter_no_paginate = $request->no_paginate;
            $filter_start_date = $request->start_date;
            $filter_end_date = $request->end_date;



            $brandsQuery = Brand::with(
                'categories',
                'country',
                'city',
                'storeMall',
            );

            if (isset($filter_name)) {
                $brandsQuery->where('name_brand', 'like', '%' . $filter_name . '%');
            }
            if (isset($filter_mall_id)) {
                $brandsQuery->where('mall_id', $filter_mall_id);
            }
            if (isset($filter_country_id)) {
                $brandsQuery->where('country_id', $filter_country_id);
            }
            if (isset($filter_city_id)) {
                $brandsQuery->where('city_id', $filter_city_id);
            }
            if (isset($filter_category_id)) {
                $brandsQuery->whereHas('categoriesRelation', function ($query) use ($filter_category_id) {
                    $query->where('category_id', $filter_category_id);
                });
            }
            if (isset($filter_store_mall_id)) {
                $brandsQuery->whereHas('storeBrandRelation', function ($query) use ($filter_store_mall_id) {
                    $query->where('store_mall_id', $filter_store_mall_id);
                });
            }

            if (isset($filter_start_date) && isset($filter_end_date)) {
                $brandsQuery->whereDate('created_at', '>=', $filter_start_date)
                    ->whereDate('created_at', '<=', $filter_end_date);
            }


            if ($filter_no_paginate) {
                $brands = $brandsQuery->get();
            } else {
                $brands = $brandsQuery->paginate($perPage);
            }

            $brandsFormat = $brands->map(function ($item) use ($translate, $TGGlanguage) {

                $categoriesFormat = collect($item['categories'])->map(function ($category) use ($translate, $TGGlanguage) {
                    return [
                        "id" => $category['id'],
                        "name" => $TGGlanguage != 'es' ? $translate->translateText($category['name'], $TGGlanguage) : $category['name'],
                    ];
                });
           
                $storesFormat = collect($item['storeMall'])->map(function ($store) use ($translate, $TGGlanguage) {
                    return [
                        "id" => $store['id'],
                        "name" => $TGGlanguage != 'es' ? $translate->translateText($store['name'], $TGGlanguage) : $store['name'],
                    ];
                });


                return [
                    "id" => $item['id'],
                    "name_brand" => $TGGlanguage != 'es' ? $translate->translateText($item['name_brand'], $TGGlanguage) : $item['name_brand'],
                    "country_id" => $item['country_id'],
                    "state_id" => $item['state_id'],
                    "city_id" => $item['city_id'],
                    "description_brand" => $TGGlanguage != 'es' ? $translate->translateText($item['description_brand'], $TGGlanguage) : $item['description_brand'],
                    "mall_id" => $item['mall_id'],
                    "image_brand" => $item['image_brand'],
                    "image" => $item['image'],
                    "categories" => $categoriesFormat,
                    "country" => $item['country'],
                    "city" => $item['city'],
                    "store_mall" => $storesFormat,

                ];
            });



            if ($filter_no_paginate) {
                $data = $brandsFormat;
            } else {
                $data = [
                    "data" => $brandsFormat,
                    'current_page' => $brands->currentPage(),
                    'first_page_url' => $brands->url(1),
                    'from' => $brands->firstItem(),
                    'last_page' => $brands->lastPage(),
                    'last_page_url' => $brands->url($brands->lastPage()),
                    'next_page_url' => $brands->nextPageUrl(),
                    'path' => $brands->url($brands->currentPage()),
                    'per_page' => $brands->perPage(),
                    'prev_page_url' => $brands->previousPageUrl(),
                    'to' => $brands->lastItem(),
                    'total' => $brands->total(),
                ];
            }



            return $data;
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function addBrand(AddBrandRequest $request)
    {

        try {
            DB::beginTransaction();
            // Buscar si ya existe una marca eliminada con el mismo nombre
            $existingBrand = Brand::withTrashed()
                ->where('name_brand', $request->name_brand)
                ->whereNotNull('deleted_at') // Solo marcas eliminadas
                ->first();

            if ($existingBrand) {
                // Restaurar la categoría y actualizar los datos
                $existingBrand->name_brand = $request->name_brand;
                $existingBrand->country_id = $request->country_id;
                $existingBrand->state_id = $request->state_id;
                $existingBrand->city_id = $request->city_id;
                $existingBrand->description_brand = $request->description_brand;
                $existingBrand->mall_id = $request->mall_id;

                if (isset($request->image_brand) && $request->hasFile('image_brand')) {
                    $existingBrand->image_brand = $this->storeImage($request->file('image_brand'));
                }

                $existingBrand->restore(); // Restaurar la marca

                // Relacionar las categorías seleccionadas con la marca
                if ($request->has('selected_categories')) {

                    $existingBrand->categoriesRelation()->delete();

                    $selectedCategories = $request->input('selected_categories');

                    foreach ($selectedCategories as $categoryId) {
                        $existingBrand->categoriesRelation()->create(['category_id' => $categoryId]);
                    }
                }

                if ($request->has('selected_store_mall')) {
                    $selectedStoreMallBrand = $request->input('selected_store_mall');

                    // Primero, eliminamos todas las relaciones existentes
                    $existingBrand->storeBrandRelation()->delete();

                    // Luego, creamos nuevas relaciones para las seleccionadas
                    foreach ($selectedStoreMallBrand as $storeMallId) {
                        $existingBrand->storeBrandRelation()->create(['store_mall_id' => $storeMallId]);
                    }
                }

                DB::commit();
                return response()->json($existingBrand, 201);
            }


            $brandData = $request->all();

            if (isset($request->image_brand) && $request->hasFile('image_brand')) {
                $imagePath = $this->storeImage($request->file('image_brand'));
                $brandData['image_brand'] = $imagePath;
            }

            $brand = Brand::create($brandData);

            // Relacionar las categorías seleccionadas con la marca
            if ($request->has('selected_categories')) {
                $selectedCategories = $request->input('selected_categories');

                foreach ($selectedCategories as $categoryId) {
                    $brand->categoriesRelation()->create(['category_id' => $categoryId]);
                }
            }

            if ($request->has('selected_store_mall')) {

                $selectedStoreMallBrand = $request->input('selected_store_mall');

                foreach ($selectedStoreMallBrand as $storeMallId) {
                    $brand->storeBrandRelation()->create(['store_mall_id' => $storeMallId]);
                }
            }
            DB::commit();
            return response()->json($brand, 201);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function showBrand(Request $request)
    {
        try {
            $id = $request->id;


            $brand = Brand::with(
                'categories',
                'country',
                'city',
                'stores',
                'malls'
            )->findOrFail($id);
            return response()->json($brand);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function updateBrand(UpdateBrandRequest $request)
    {
        try {
            DB::beginTransaction();
            $id = $request->id;
            $name = $request->name_brand;



            // Buscar si ya existe una marca eliminada con el mismo nombre
            $existingBrand = Brand::withTrashed()
                ->where('name_brand', $name)
                ->whereNotNull('deleted_at') // Solo marcas eliminadas
                ->first();

            if ($existingBrand) {


                // Restaurar la categoría y actualizar los datos
                $existingBrand->name_brand = $request->name_brand;
                $existingBrand->country_id = $request->country_id;
                $existingBrand->state_id = $request->state_id;
                $existingBrand->city_id = $request->city_id;
                $existingBrand->description_brand = $request->description_brand;
                $existingBrand->mall_id = $request->mall_id;

                if (isset($request->image_brand) && $request->hasFile('image_brand')) {
                    $existingBrand->image_brand = $this->storeImage($request->file('image_brand'));
                }

                $existingBrand->restore(); // Restaurar la categoría

                // Relacionar las categorías seleccionadas con la marca
                if ($request->has('selected_categories')) {

                    $existingBrand->categoriesRelation()->delete();

                    $selectedCategories = $request->input('selected_categories');

                    foreach ($selectedCategories as $categoryId) {
                        $existingBrand->categoriesRelation()->create(['category_id' => $categoryId]);
                    }
                }

                if ($request->has('selected_store_mall')) {
                    $selectedStoreMallBrand = $request->input('selected_store_mall');

                    // Primero, eliminamos todas las relaciones existentes
                    $existingBrand->storeBrandRelation()->delete();

                    // Luego, creamos nuevas relaciones para las seleccionadas
                    foreach ($selectedStoreMallBrand as $storeMallId) {
                        $existingBrand->storeBrandRelation()->create(['store_mall_id' => $storeMallId]);
                    }
                }

                $this->deleteBrand($id);

                DB::commit();
                return response()->json($existingBrand, 201);
            } else {

                $brandData = $request->all();

                $brand = Brand::findOrFail($id);

                if (isset($request->image_brand) && $request->hasFile('image_brand')) {
                    $this->deleteImage($brand->image_brand);
                    $imagePath = $this->storeImage($request->file('image_brand'));
                    $brandData['image_brand'] = $imagePath;
                }

                $brand->update($brandData);

                // Relacionar las categorías seleccionadas con la marca
                if ($request->has('selected_categories')) {
                    $selectedCategories = $request->input('selected_categories');

                    // Primero, eliminamos todas las relaciones existentes
                    $brand->categoriesRelation()->delete();

                    // Luego, creamos nuevas relaciones para las categorías seleccionadas
                    foreach ($selectedCategories as $categoryId) {
                        $brand->categoriesRelation()->create(['category_id' => $categoryId]);
                    }
                } else {
                    // Si no se seleccionaron categorías, eliminamos todas las relaciones
                    $brand->categoriesRelation()->delete();
                }


                // Relacionar las categorías seleccionadas con la marca
                if ($request->has('selected_store_mall')) {
                    $selectedStoreMallBrand = $request->input('selected_store_mall');

                    // Primero, eliminamos todas las relaciones existentes
                    $brand->storeBrandRelation()->delete();

                    // Luego, creamos nuevas relaciones para las seleccionadas
                    foreach ($selectedStoreMallBrand as $storeMallId) {
                        $brand->storeBrandRelation()->create(['store_mall_id' => $storeMallId]);
                    }
                } else {
                    // Si no se seleccionaron, eliminamos todas las relaciones
                    $brand->storeBrandRelation()->delete();
                }
                DB::commit();
                return response()->json($brand, 200);
            }
        } catch (Exception $e) {
            DB::rollBack();

            if (!isset($brand)) {
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

    public function deleteBrand($id)
    {
        try {
            $brand = Brand::findOrFail($id);
            //$this->deleteImage($brand->image_brand);

            // Eliminar todas las categorías relacionadas con la marca
            $brand->categoriesRelation()->delete();
            $brand->storeBrandRelation()->delete();

            $brand->delete();

            return [
                'status' => 'success',
                'message' => 'Elimination is confirmed'
            ];
        } catch (Exception $e) {

            if (!isset($brand)) {
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
        $imagePath = $image->storeAs('uploads/brand_images/' . now()->format('Y-m-d'), $imageName, 'public');
        return $imagePath;
    }

    private function deleteImage($imagePath)
    {
        if ($imagePath && Storage::exists('public/' . $imagePath)) {
            Storage::delete('public/' . $imagePath);
        }
    }
}
