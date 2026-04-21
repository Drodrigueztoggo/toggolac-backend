<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateStoreMallRequest;
use App\Http\Requests\UpdateStoreMallRequest;
use App\Models\StoreMall;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Exception;
use DB;

class StoreMallController extends Controller
{

    public function getStoreMallList(Request $request)
    {

        try {
            $filter_mall_id = $request->query('mall_id');
            $filter_quotes = $request->query('quotes');
            $filter_city_id = $request->query('city_id');
            $filter_country_id = $request->query('country_id');
            $group_country = $request->query('group_country');

            $storeMallsQuery = StoreMall::with('mallInfo:id,country_id', 'mallInfo.countryInfo:id,name')->select('id', 'store as name', 'quotes', 'mall_id', 'image_store', 'quotes');

            if ($filter_country_id) {
                $storeMallsQuery->whereHas('mallInfo', function ($query) use ($filter_country_id) {
                    $query->where('country_id', $filter_country_id);
                });
            }
            if ($filter_city_id) {
                $storeMallsQuery->whereHas('mallInfo', function ($query) use ($filter_city_id) {
                    $query->where('city_id', $filter_city_id);
                });
            }
            if ($filter_mall_id) {
                $storeMallsQuery->where('mall_id', $filter_mall_id);
            }
            if ($filter_quotes) {
                $storeMallsQuery->where('quotes', 1);
            }
            $storeMalls = $storeMallsQuery->get();

           
            if(isset($group_country)){
                $storeMalls = $storeMalls->groupBy(function ($store) {
                    return $store->mallInfo->countryInfo;
                })->map(function ($stores, $countryInfo) {
    
                    $countryInfo = json_decode($countryInfo);
    
                    // dd($countryInfo->id);
    
                    return [
                        'id' => $countryInfo->id,
                        'name' => $countryInfo->name,
                        'stores' => $stores->map(function ($store) {
                            return [
                                'id' => $store->id,
                                'name' => $store->name,
                                'quotes' => $store->quotes,
                                'mall_id' => $store->mall_id,
                                'image_store' => $store->image_store,
                                'image' => $store->image,
                                'mall_info' => [
                                    'id' => $store->mallInfo->id,
                                    'country_id' => $store->mallInfo->country_id,
                                    'image' => $store->mallInfo->image,
                                    'country_info' => [
                                        'id' => $store->mallInfo->countryInfo->id,
                                        'name' => $store->mallInfo->countryInfo->name,
                                    ],
                                ],
                            ];
                        }),
                    ];
                });
            }
           


            // return $groupedStores;

            return response()->json([
                'data' => $storeMalls
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'id' => $e->getLine(),
            ], 500);
        }
    }

    public function getStoreMall(Request $request)
    {
        try {
            $path = $request->getSchemeAndHttpHost() . '/storage/';

            $filter_name = $request->input('name');
            $filter_mall_id = $request->input('mall_id');
            $filter_name_city = $request->input('name_city');
            $perPage = $request->query('per_page', 20);

            $storeMallsQuery = StoreMall::select('id', 'store', 'num_phone', 'mall_id', 'image_store', 'quotes')
                ->with('mallInfo.city')
                ->with('mallInfo.countryInfo')
                ->with('mallInfo.state');

            if (isset($filter_name)) {
                $storeMallsQuery->where('store', 'LIKE', '%' . $filter_name . '%');
            }


            if (isset($filter_mall_id)) {
                $storeMallsQuery->where('mall_id', $filter_mall_id);
            }


            if (isset($filter_name_city)) {
                $storeMallsQuery->whereHas('mallInfo.city', function ($query) use ($filter_name_city) {
                    $query->where('name', 'like', '%' . $filter_name_city . '%');
                });
            }

            $storeMalls = $storeMallsQuery->paginate($perPage);
            return response()->json($storeMalls);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function addStoreMall(CreateStoreMallRequest $request)
    {
        try {
            $storeMallData = $request->all();

            if ($request->hasFile('image_store')) {
                $imagePath = $this->storeImage($request->file('image_store'));
                $storeMallData['image_store'] = $imagePath;
            }

            isset($storeMallData['quotes']) ? $storeMallData['quotes'] = 1 : $storeMallData['quotes'] = 0;

            $storeMall = StoreMall::create($storeMallData);

            return response()->json($storeMall, 201);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function showStoreMall(Request $request)
    {
        try {
            $storeMall = StoreMall::findOrFail($request->id);
            return response()->json($storeMall);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function updateStoreMall(UpdateStoreMallRequest $request)
    {
        try {
            $id = $request->id;
            $storeMall = StoreMall::findOrFail($id);



            if ($request->hasFile('image_store')) {
                $this->deleteImage($storeMall->image_store);
                $imagePath = $this->storeImage($request->file('image_store'));
            }

            $storeMall->update($request->all());
            if (isset($imagePath)) {
                $storeMall->image_store = $imagePath;
            }

            if (isset($request->quotes)) {
                $storeMall->quotes = 1;
            } else {
                $storeMall->quotes = 0;
            }

            $storeMall->save();

            return response()->json($storeMall, 200);
        } catch (Exception $e) {

            if (!isset($storeMall)) {
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

    public function deleteStoreMall($id)
    {
        try {

            $storeMall = StoreMall::findOrFail($id);
            // $this->deleteImage($storeMall->image_store); // Confirmar si se debe eliminar la imagen también
            $storeMall->delete();

            return [
                'status' => 'success',
                'message' => 'Elimination is confirmed'
            ];
        } catch (Exception $e) {

            if (!isset($storeMall)) {
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
            $imagePath = $image->storeAs('uploads/store_mall_images/' . now()->format('Y-m-d'), $imageName, 'public');
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
