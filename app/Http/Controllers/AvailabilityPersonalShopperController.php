<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateAvailabilityRequest;
use App\Models\AvailabilityPersonalShopper;
use App\Models\Quote;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AvailabilityPersonalShopperController extends Controller
{

    public function getDispoPersonalShopper(Request $request)
    {
        try {
            //code...
            $UserId = auth()->user()->id;


            $availability = AvailabilityPersonalShopper::where('user_id', $UserId)->select('day', 'id', 'start_time', 'end_time')->get();


            return $availability;



        } catch (\Exception $th) {
            return response()->json(['message' => 'Error al procesar la solicitud'], 500);

        }
    }

    public function create(CreateAvailabilityRequest $request)
    {
        try {
            // Obtener los datos del JSON de la solicitud.
             $data = $request->all();

            // Validar que se proporcionó 'user_id' y 'availability'.
            if (!isset($data['user_id']) || !isset($data['availability'])) {
                return response()->json(['message' => 'Faltan datos requeridos'], 400);
            }

            // Obtener el 'user_id' y 'availability' del JSON.
            $userId = $data['user_id'];
            $availabilityData = $data['availability'];

            AvailabilityPersonalShopper::where('user_id', $userId)->delete();

            // Iterar sobre las disponibilidades proporcionadas.
            foreach ($availabilityData as $availabilityItem) {
                $day = $availabilityItem['day'];
                $startTime = $availabilityItem['start_time'];
                $endTime = $availabilityItem['end_time'];

                // Buscar una disponibilidad existente para el mismo usuario y día.
                $existingAvailability = AvailabilityPersonalShopper::where('user_id', $userId)
                    ->where('day', $day)
                    ->first();

                if ($existingAvailability) {
                    // Si la disponibilidad existe, actualízala.
                    $existingAvailability->update([
                        'start_time' => $startTime,
                        'end_time' => $endTime,
                    ]);
                } else {
                    // Si la disponibilidad no existe, crea una nueva.
                    AvailabilityPersonalShopper::create([
                        'user_id' => $userId,
                        'day' => $day,
                        'start_time' => $startTime,
                        'end_time' => $endTime,
                    ]);
                }
            }

            return response()->json(['message' => 'Disponibilidad actualizada/creada con éxito'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al procesar la solicitud'], 500);
        }
    }

    public function getFullDayAvailability(Request $request)
{
    try {
        $city_id = $request->input('city_id');
        $numeroProductos = $request->input('quantity_product', 0);
        $numeroProductos = max(1, $numeroProductos); // Asegura que sea al menos 1

        $tasaProductosPorHora = 3;

        $horasRequeridas = ceil($numeroProductos / $tasaProductosPorHora);

        $date = $request->input('date');
        $user_id = $request->input('user_id'); // Usuario opcional

        // Convertir la fecha en el nombre del día de la semana.
        $dayOfWeek = strftime("%A", strtotime($date));

        // Consulta todas las disponibilidades que coinciden con el día de la semana, user_id y city_id.
        $availabilities = AvailabilityPersonalShopper::with('user')
            ->where('day', $dayOfWeek);

        if ($user_id) {
            $availabilities->where('user_id', $user_id);
        }

        $availabilities = $availabilities->whereHas('user', function ($query) use ($city_id) {
            $query->where('city_id', $city_id);
        })
        ->get();

        // Ahora, vamos a procesar las franjas horarias en base al valor de $horasRequeridas
        // y agruparlas por usuario, y también verificar las citas.
        $groupedAvailability = [];

        foreach ($availabilities as $availability) {
            $user_id = $availability->user->id;
            $user_name = $availability->user->name;
            $user_availability = [
                'user_id' => $user_id,
                'user_name' => $user_name,
                'available_time_slots' => []
            ];

            $start_time = strtotime($availability->start_time);
            $end_time = strtotime($availability->end_time);

            while ($start_time + $horasRequeridas * 3600 <= $end_time) {
                $slotStart = date('H:i:s', $start_time);
                $slotEnd = date('H:i:s', $start_time + $horasRequeridas * 3600);

                // Verifica cuántas citas tiene el usuario en esta franja horaria.
                $quoteCount = Quote::where('availability_personal_shopper_id', $user_id)
                    ->where('date', $date)
                    ->where('start_time', '<', $slotEnd)
                    ->where('end_time', '>', $slotStart)
                    ->whereIn('status_id', [1, 2])
                    ->count();

                $user_availability['available_time_slots'][] = [
                    'start_time' => $slotStart,
                    'end_time' => $slotEnd,
                    'count_quote' => $quoteCount, // Cantidad de citas en esta franja.
                    'available' => $quoteCount >= 3 ? false : true,
                ];

                $start_time += $horasRequeridas * 3600;
            }

            $groupedAvailability[] = $user_availability;
        }

        // Devuelve los resultados como respuesta JSON.
        return response()->json(['data' => $groupedAvailability], 200);
    } catch (\Exception $e) {
        return response()->json(['message' => 'Error al procesar la solicitud'], 500);
    }
}


    // public function create(CreateAvailabilityRequest $request)
    // {

    //     $validatedData = $request->validated();

    //     // Validar si la nueva franja horaria está dentro del rango de disponibilidad para ese día
    //     $overlappingAvailability = AvailabilityPersonalShopper::where('user_id', $validatedData['user_id'])
    //         ->where('date', $validatedData['date'])
    //         ->where(function ($query) use ($validatedData) {
    //             $query->whereBetween('start_time', [$validatedData['start_time'], $validatedData['end_time']])
    //                 ->orWhereBetween('end_time', [$validatedData['start_time'], $validatedData['end_time']])
    //                 ->orWhere(function ($query) use ($validatedData) {
    //                     $query->where('start_time', '<', $validatedData['start_time'])
    //                         ->where('end_time', '>', $validatedData['end_time']);
    //                 });
    //         })->exists();

    //     if ($overlappingAvailability) {
    //         return response()->json(['error' => 'La franja horaria se superpone con una existente.'], 422);
    //     }

    //     // Crear la nueva franja horaria
    //     $availability = AvailabilityPersonalShopper::create($validatedData);

    //     return response()->json(['message' => 'Franja horaria creada correctamente.', 'data' => $availability], 201);
    // }


    // public function getFullDayAvailability(Request $request)
    // {
    //     $request->validate([
    //         'date' => 'required|date',
    //     ]);

    //     $cityId = $request->input('city_id');
    //     $numeroProductos = $request->input('quantity_product', 0);
    //     $numeroProductos = max(1, $numeroProductos); // Asegura que sea al menos 1

    //     $tasaProductosPorHora = 3;

    //     $horasRequeridas = ceil($numeroProductos / $tasaProductosPorHora);

    //     $date = $request->input('date');
    //     $userId = $request->input('user_id'); // Usuario opcional


    //     $availabilitySlotsQuery  = AvailabilityPersonalShopper::with('user')->where('date', $date)
    //         ->whereHas('user', function ($query) use ($cityId) {
    //             $query->where('city_id', $cityId);
    //         })
    //         ->orderBy('user_id')
    //         ->orderBy('start_time');
    //     // ->get();

    //     if ($userId) {
    //         $availabilitySlotsQuery->where('user_id', $userId);
    //     }

    //     $availabilitySlots = $availabilitySlotsQuery->get();


    //     $occupiedSlots = $this->getAllOccupiedSlots($date, $cityId);

    //     $fullDayAvailability = [];

    //     foreach ($availabilitySlots->pluck('user_id')->unique() as $userId) {
    //         $user = User::select('id', 'city_id', 'name')->find($userId);

    //         if ($user) {
    //             $userAvailableTimeSlots = [];

    //             foreach ($availabilitySlots as $slot) {
    //                 if ($slot->user_id == $userId) {
    //                     $startTime = Carbon::parse($slot->start_time);
    //                     $endTime = Carbon::parse($slot->end_time);

    //                     // Asegurarse de que el intervalo ajustado no exceda el turno del shopper
    //                     $adjustedEndTime = $endTime->copy()->subHours($horasRequeridas - 1);

    //                     $currentTime = $startTime->copy();

    //                     while ($adjustedEndTime >= $currentTime) {
    //                         $endInterval = $currentTime->copy()->addHours($horasRequeridas);

    //                         if ($endInterval <= $endTime) {
    //                             $occupiedCount = $this->getOccupiedCount($occupiedSlots, $userId, $currentTime, $endInterval);
    //                             $isAvailable = $occupiedCount < 3;

    //                             $userAvailableTimeSlots[] = [
    //                                 'start_time' => $currentTime->format('H:i:s'),
    //                                 'end_time' => $endInterval->format('H:i:s'),
    //                                 'count_quote' => $occupiedCount,
    //                                 'available' => $isAvailable,
    //                             ];
    //                         }

    //                         $currentTime->addHours(2);  // Avanzar dos horas
    //                     }
    //                 }
    //             }

    //             $fullDayAvailability[] = [
    //                 'user' => $user,
    //                 'available_time_slots' => $userAvailableTimeSlots,
    //             ];
    //         }
    //     }



    //     return response()->json(['full_day_availability' => $fullDayAvailability, 'horas' => $horasRequeridas], 200);
    // }

    private function getOccupiedCount($occupiedSlots, $userId, $start_time, $end_time)
    {
        $count = 0;

        if (isset($occupiedSlots[$userId])) {
            foreach ($occupiedSlots[$userId] as $slot) {
                $slotStartTime = Carbon::parse($slot['start_time']);
                $slotEndTime = Carbon::parse($slot['end_time']);

                if (($start_time >= $slotStartTime && $start_time < $slotEndTime) ||
                    ($end_time > $slotStartTime && $end_time <= $slotEndTime) ||
                    ($start_time <= $slotStartTime && $end_time >= $slotEndTime)
                ) {
                    $count++;
                }
            }
        }

        return $count;
    }

    private function getAllOccupiedSlots($date, $cityId)
    {
        return Quote::where('date', $date)
            ->where('city_id', $cityId)
            ->whereIn('status_id', [1, 2])
            ->get(['availability_personal_shopper_id', 'start_time', 'end_time'])
            ->groupBy('availability_personal_shopper_id')
            ->toArray();
    }
}
