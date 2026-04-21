<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\NotificationDevice;
use App\Models\Notification as LOCAL;
use App\Http\Requests\CreateNotificationRequest;


use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Kreait\Firebase\Exception\MessagingException;
use Kreait\Firebase\Exception\FirebaseException;
use Kreait\Firebase\Messaging\MessageData;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    public function setFcToken(Request $request){
        try {
            $payload = $request->all();
            //save notificacion device
            $query = NotificationDevice::where('device_token', '=', $payload['device'])->first();
            if(is_null($query)){
                $notification_device = new NotificationDevice();
                $notification_device->device_token = $payload['device'];
                $notification_device->user_id = isset($payload['user_id']) && is_null($payload['user_id']) ? null : $payload['user_id'];
                $notification_device->save();
            } else {
                if(!is_null($payload['user_id']) && is_null($query->user_id)){
                    $notification_device =  NotificationDevice::findOrFail($query->id);
                    $notification_device->user_id = $payload['user_id'];
                    $notification_device->save();
                }
                
            }
            return response()->json([
                'status' => 'success',
                'message' => 'Se guardo el token del device'
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                "error" => $th->getMessage()
            ], 500);
        }
    }

    public function sendPushNotification(CreateNotificationRequest $request)
    {   
        $payload = $request->all();
        try{
            DB::beginTransaction();
            $query = NotificationDevice::whereNull("deleted_at")->get();
            $fcmToken = "";
            foreach ($query as $device) {
                $fcmToken = $device->device_token; // Obtén el token de Firebase guardado en la base de datos
                // Create a Firebase factory
                $archivo = base_path('firebase.json');
                $factory = (new Factory)
                ->withServiceAccount($archivo);
                // Create a Firebase Messaging instance
                $messaging = $factory->createMessaging();
                $notification = Notification::create()
                ->withTitle($payload['title'])
                ->withBody($payload['description']);
                // Define the notification message
                $data = [
                    'payload' => (string)json_encode($payload['payload'])
                ];
                $messageData = MessageData::fromArray($data);
                $message = CloudMessage::withTarget('token', $fcmToken)
                ->withNotification($notification)
                ->withData($messageData);
                // Send the notification
                $response = $messaging->send($message);
            }
            $notification = new LOCAL();
            $notification->title = $payload['title'];
            $notification->description = $payload['description'];
            $notification->brand = $payload["payload"]["name_brand"];
            $notification->save();
            DB::commit();
            return response()->json(['message' => 'Notificación enviada']);
        } catch (MessagingException $e) {
            // Captura los errores relacionados con el envío de mensajes
            DB::rollBack();
            $error = $e->errors();
            if($error["error"]["status"] === "NOT_FOUND"){
                $notification_device =  NotificationDevice::where('device_token', '=', $fcmToken)->first();
                $notification_device->delete();
                $data = new CreateNotificationRequest([
                    "title" => $payload['title'],
                    "description" => $payload['description'],
                ]);
                return $this->sendPushNotification($data);
            }
        } catch (FirebaseException $e) {
            DB::rollBack();
            // Captura cualquier otro error relacionado con Firebase
            return response()->json([
                'status' => 'error',
                'type' => "Firebase",
                "error" => $e->getMessage()
            ], 500);
        } catch (\Exception $e) {
            DB::rollBack();
            // Captura cualquier otro error genérico
            return response()->json([
                'status' => 'error',
                'type' => "System",
                "error" => $e->getMessage()
            ], 500);
        }
    }

    public function getNotification(Request $request)
    {
        try {
            $perPage = $request->query('per_page', 20);
            $malls = LOCAL::orderBy("created_at",'DESC')->paginate($perPage);
            return response()->json($malls);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}