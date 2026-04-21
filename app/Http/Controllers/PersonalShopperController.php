<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PersonalShopper;
use App\Http\Requests\CreatePersonalShopperRequest;
use App\Http\Requests\UpdatePersonalShopperRequest;
use Illuminate\Support\Facades\Storage;
use Hash;
use DB;

class PersonalShopperController extends Controller
{
    public function getPersonalShopper(Request $request)
    {
        try {
            $personalShoppers = PersonalShopper::withoutTrashed()
                                ->select(
                                    DB::raw('CONCAT(personal_shoper.first_name, " ", personal_shoper.last_name) AS user'),
                                    'personal_shoper.email as email',
                                    DB::raw('DATE_FORMAT(personal_shoper.created_at, "%b %d, %Y") as date_create'),
                                    'c.name as city',
                                    DB::raw("0" . ' as qualification'),
                                    DB::raw("0" . ' as number_of_purchases'),
                                    DB::raw("0" . ' as average_sales'),
                                    DB::raw("0" . ' as commissions')
                                )
                                ->leftJoin('cities as c', 'c.id', 'personal_shoper.city_id');

            $first_name = $request->query('first_name');
            $last_name = $request->query('last_name');
            $personal_id_number = $request->query('personal_id_number');

            if (isset($first_name)) {
                $personalShoppers->where('first_name', 'like', '%' . $first_name . '%');
            }
            if (isset($last_name)) {
                $personalShoppers->where('last_name', 'like', '%' . $last_name . '%');
            }
            if (isset($personal_id_number)) {
                $personalShoppers->where('personal_id_number', 'like', '%' . $personal_id_number . '%');
            }

            $personalShoppers = $personalShoppers->paginate(20);

            return response()->json($personalShoppers);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function addPersonalShopper(CreatePersonalShopperRequest $request)
    {
        try {
        $request = $request->all();

        if ($request['image']) {
            $imagePath = $this->storeImage($request['image']);
            $request['image'] = $imagePath;
        }

        $request['password'] = Hash::make($request['password']);

        $personalShopper = PersonalShopper::create( $request );

        return response()->json($request, 201);

        } catch (Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage()
            ], 500);
        }
    }

    public function showPersonalShopper($id)
    {
        try {
            $personalShopper = PersonalShopper::findOrFail($id);

            if(is_null($personalShopper)) {
               return response()->json([
                    'status' => 'error',
                    'message' => 'El id ' . $id . ' No se encuentra registrado.'
                ], 404);
            }

            return response()->json($personalShopper);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function updatePersonalShopper(UpdatePersonalShopperRequest $request)
    {
        try {
            $id = $request->id;
            $personalShopper = PersonalShopper::findOrFail($id);

            if ($request->hasFile('image')) {
                $this->deleteImage($personalShopper->image);
                $imagePath = $this->storeImage($request->file('image'));
            }
            $request = $request->all();
            if(is_null($request['password'])){
               $request['password'] = $personalShopper->password;
            } else {
               $request['password'] = Hash::make($request['password']);
            }

            $personalShopper->update($request);
            if (isset($imagePath)) {
                $personalShopper->image = $imagePath;
            }
            $personalShopper->save();
            return response()->json($request, 201);

        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function deletePersonalShopper($id)
    {
        try {
            $personalShopper = PersonalShopper::findOrFail($id);

            if (!isset($personalShopper)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'El id ' . $id . ' No se encuentra registrado.'
                ], 404);
            }
            $personalShopper->delete();


            return [
                'status' => 'success',
                'message' => 'Se confirma la eliminación'
            ];

        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    private function storeImage($image)
    {
        $imageName = uniqid() . '_' . now()->format('YmdHis') . '.' . $image->getClientOriginalExtension();
        $imagePath = $image->storeAs('uploads/personal_shoper/' . now()->format('Y-m-d'), $imageName, 'public');
        return $imagePath;
    }

    private function deleteImage($imagePath)
    {
        if ($imagePath && Storage::exists('public/' . $imagePath)) {
            Storage::delete('public/' . $imagePath);
        }
    }
}
