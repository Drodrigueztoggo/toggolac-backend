<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateMallRequest;
use App\Http\Requests\UpdateMallRequest;
use App\Models\Mall;
use App\Models\StoreMall;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Exception;
use DB;

class MallController extends Controller
{

    public function getMallList(Request $request)
    {
        try {
            $filter_country_id = $request->query('country_id');

            $mallsQuery = Mall::select('id', 'name_mall as name', 'country_id');
            if($filter_country_id){
                $mallsQuery->where('country_id', $filter_country_id);
            }
            $malls = $mallsQuery->get();
            return response()->json([
                'data' => $malls
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function getMallListPublic(Request $request)
    {
        try {

            $mallsQuery = Mall::select('id', 'name_mall as name', 'country_id');
            $malls = $mallsQuery->get();
            return response()->json($malls);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function getMalls(Request $request)
    {
        try {

            $perPage = $request->query('per_page', 20);
            $malls = Mall::with('countryInfo')->select('id', 'name_mall', 'country_id', 'city_id', 'postal_code', 'address', 'num_phone', 'image_mall')
                ->paginate($perPage);
            return response()->json($malls);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }


    public function addMall(CreateMallRequest $request)
    {
        try {
            $mallData = $request->all();

            if ($request->hasFile('image_mall')) {
                $imagePath = $this->storeImage($request->file('image_mall'));
                $mallData['image_mall'] = $imagePath;
            }

            $inMall = $request->in_mall;

            $mall = null;
            $createMall = null;

            if (!isset($inMall)) {
                // Crear un nuevo registro en la tabla malls
                $mall = Mall::create($mallData);

                // Crear un nuevo registro en la tabla store_malls
                $createMallData = [
                    'store' => $mallData['name_mall'],
                    'num_phone' => $mallData['num_phone'],
                    'quotes' => isset($mallData['quotes']) ? true : false,
                    'mall_id' => $mall->id,
                    'image_store' => $imagePath,
                ];

                $createMall = StoreMall::create($createMallData);
            } else {
                // Crear solo un nuevo registro en la tabla malls
                $mall = Mall::create($mallData);
            }

            return response()->json([
                'mall' => $mall,
                'create_mall' => $createMall,
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }


    public function showMall(Request $request)
    {
        try {
            $mall = Mall::with('countryInfo')->findOrFail($request->id);
            return response()->json($mall);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }


    public function updateMall(UpdateMallRequest $request)
    {
        try {
            $id = $request->id;
            $mall = Mall::findOrFail($id);


            if ($request->hasFile('image_mall')) {
                $this->deleteImage($mall->image_mall);
                $imagePath = $this->storeImage($request->file('image_mall'));
            }

            $mall->update($request->all());
            if (isset($imagePath)) {
                $mall->image_mall = $imagePath;
            }
            $mall->save();

            return response()->json($mall, 200);
        } catch (Exception $e) {

            if (!isset($mall)) {
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


    public function deleteMall($id)
    {
        try {


            $mall = Mall::findOrFail($id);
            // $this->deleteImage($mall->image_mall); // confirmar si se debe eliminar la imagen tambien
            $mall->delete();

            return [
                'status' => 'success',
                'message' => 'Elimination is confirmed'
            ];

        } catch (Exception $e) {

            if (!isset($mall)) {
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
            $imagePath = $image->storeAs('uploads/mall_images/' . now()->format('Y-m-d'), $imageName, 'public');
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
