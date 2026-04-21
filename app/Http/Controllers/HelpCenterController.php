<?php

namespace App\Http\Controllers;

use App\Models\HelpCenter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Requests\HelpCenterCreateRequest;
use App\Http\Requests\HelpCenterUpdateRequest;
use App\Mail\HelpCenterCommnetMail;
use App\Models\HelpCenterAnswer;
use App\Models\HelpCenterAnswerImage;
use App\Models\HelpCerterImage;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class HelpCenterController extends Controller
{

    public function sendCommentHelpCenter(Request $request)
    {

        try {
            // 
            DB::beginTransaction();
            $this->validate($request, [
                'help_center_id' => 'required|integer',
                'comment' => 'required',
                'images.*' => 'nullable|image|mimes:webp,jpeg,jpg,png,gif,bmp|max:500',
            ]);

            $createUserId = auth()->user()->id;


            $help_center_id = $request->help_center_id;
            $comment = $request->comment;
            $images = $request->images;


            $infoUser = HelpCenter::with(['user' => function ($query) {
                $query->select('id', 'name', 'email'); // Lista los campos que deseas seleccionar
            }])->select('id', 'user_id')->find($help_center_id);

            // return $infoUser;

            $newAnswer = new HelpCenterAnswer();
            $newAnswer->help_center_id =  $help_center_id;
            $newAnswer->comment = $comment;
            $newAnswer->user_id = $createUserId;
            $newAnswer->save();


            $updateStatusHelpCenter = HelpCenter::where('id', $help_center_id)->update([
                "status" => 'En progreso'
            ]);




            if (isset($infoUser) && isset($infoUser->user)) {
                $email = $infoUser->user->email;
                $name = $infoUser->user->name;

                $imagePathsResponse = null;




                if (isset($images)) {
                    foreach ($images as $image) {
                        $imagePath = $this->storeImageComment($image); // Utiliza tu función storeImage para almacenar imágenes
                        $imagePathsResponse[] = $imagePath;

                        $newAnswerImages = new HelpCenterAnswerImage();
                        $newAnswerImages->help_center_answer_id = $newAnswer->id;
                        $newAnswerImages->image_answer = $imagePath;
                        $newAnswerImages->save();
                    }
                }

                // return $imagePathsResponse;


                $mail = new HelpCenterCommnetMail($comment, $help_center_id, $name);

                if ($imagePathsResponse && count($imagePathsResponse) > 0) {
                    foreach ($imagePathsResponse as $image) {
                        $path = storage_path('app/public/' . $image);

                        if (file_exists($path)) {
                            $mail->attach($path);
                        }
                    }
                }


                Mail::to($email)->send($mail);



                DB::commit();

                return ['status' => 'success'];
            } else {
                DB::rollback();
                return response()->json([
                    'status' => 'error',
                    'message' => 'No se ha encontrado el cliente',
                ], 404);
            }

            return $infoUser;
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
            ], 404);
        }
    }



    public function getHelpCenter(Request $request)
    {
        try {
            $helpCenter = HelpCenter::with('images', 'personalShopper:id,name,last_name', 'user:id,name,last_name')->withoutTrashed();

            $reason = $request->query('reason');
            $product = $request->query('product');
            $personal_shopper_id = $request->query('personal_shopper_id');

            $filter_purchase_id = $request->query('purchase_id');
            $filter_date = $request->query('date');
            $filter_client_id = $request->query('client_id');
            $per_page = $request->query('per_page', 20);




            if (isset($filter_purchase_id)) {
                $helpCenter->where('purchase_id', $filter_purchase_id);
            }
            if (isset($filter_date)) {
                $helpCenter->whereDate('created_at', $filter_date);
            }
            if (isset($filter_client_id)) {
                $helpCenter->where('user_id', $filter_client_id);
            }



            if (isset($reason)) {
                $helpCenter->where('reason', 'like', '%' . $reason . '%');
            }
            if (isset($product)) {
                $helpCenter->where('product', 'like', '%' . $product . '%');
            }
            if (isset($personal_shopper_id)) {
                $helpCenter->where('personal_shopper_id', $personal_shopper_id);
            }

            $helpCenter = $helpCenter->orderBy('created_at', 'desc')->paginate($per_page);

            return response()->json($helpCenter);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function addHelpCenter(HelpCenterCreateRequest $request)
    {
        try {
            $createUserId = auth()->user()->id;
            DB::beginTransaction();
            $requestData = $request->all();
            $requestData['user_id'] = $createUserId;

            $header = HelpCenter::create($requestData);


            if (isset($requestData['image'])) {
                foreach ($requestData['image'] as $image) {
                    $imagePath = $this->storeImage($image); // Utiliza tu función storeImage para almacenar imágenes

                    $saveImages = new HelpCerterImage([
                        'help_center_id' => $header->id,
                        'image_help' => $imagePath,
                    ]);
                    $saveImages->save();
                }
            }

            DB::commit();
            return response()->json($header, 201);
        } catch (Exception $e) {
            dd($e);
            DB::rollback();


            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function showHelpCenter($id)
    {
        try {
            $helpCenter = HelpCenter::with('images', 'personalShopper:id,name,last_name', 'user:id,name,last_name')->findOrFail($id);

            if (is_null($helpCenter)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'El id ' . $id . ' No se encuentra registrado.'
                ], 404);
            }

            return response()->json($helpCenter);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function updateHelpCenter(HelpCenterUpdateRequest $request, $id)
    {
        try {



            // $id = $request->id;
            $helpCenter = HelpCenter::findOrFail($id);


            // if ($request->hasFile('image')) {
            //     foreach ($request->file('image') as $image) {
            //         $imagePath = $this->storeImage($image); // Utiliza tu función storeImage para almacenar imágenes

            //         $saveImages = new HelpCerterImage([
            //             'help_center_id' => $id,
            //             'image_help' => $imagePath,
            //         ]);
            //         $saveImages->save();
            //     }
            // }

            $helpCenter->update($request->all());
            // if (isset($imagePath)) {
            //     $helpCenter->image = $imagePath;
            // }
            $helpCenter->save();
            return response()->json($helpCenter, 201);
        } catch (Exception $e) {

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function deleteHelpCenter($id)
    {
        try {
            $helpCenter = HelpCenter::findOrFail($id);

            if (!isset($helpCenter)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'El id ' . $id . ' No se encuentra registrado.'
                ], 404);
            }
            $helpCenter->delete();


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

    public function deleteHelpCenterImage($id)
    {
        try {
            $helpCenterImage = HelpCerterImage::findOrFail($id);

            if (!isset($helpCenterImage)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'El id ' . $id . ' No se encuentra registrado.'
                ], 404);
            }
            $helpCenterImage->delete();


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

    private function storeImageComment($image)
    {
        $imageName = uniqid() . '_' . now()->format('YmdHis') . '.' . $image->getClientOriginalExtension();
        $imagePath = $image->storeAs('uploads/help_center_response/' . now()->format('Y-m-d'), $imageName, 'public');
        return $imagePath;
    }

    private function storeImage($image)
    {
        $imageName = uniqid() . '_' . now()->format('YmdHis') . '.' . $image->getClientOriginalExtension();
        $imagePath = $image->storeAs('uploads/help_center/' . now()->format('Y-m-d'), $imageName, 'public');
        return $imagePath;
    }

    private function deleteImage($imagePath)
    {
        if ($imagePath && Storage::exists('public/' . $imagePath)) {
            Storage::delete('public/' . $imagePath);
        }
    }
}
