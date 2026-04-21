<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateQuoteRequest;
use App\Http\Requests\UpdateQuoteStatusRequest;
use App\Models\Quote;
use App\Models\AvailabilityPersonalShopper;
use App\Models\QuoteLogStatus;
use App\Models\QuoteStatus;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;


class QuoteController extends Controller
{
    public function getQuoteStatus(Request $request)
    {
        return  response()->json([
            'data' => QuoteStatus::select('id', 'name')->get()
        ]);
    }

    public function getQuotes(Request $request)
    {

        try {
            $perPage = $request->query('per_page', 20);

            $filter_id = $request->query('id');
            $filter_user_id = $request->query('user_id');
            $filter_name_customer = $request->query('name_customer');
            $filter_personal_shopper_id = $request->query('personal_shopper_id');
            $filter_store_mall_id = $request->query('store_mall_id');
            $filter_country_id = $request->query('country_id');
            $filter_state_id = $request->query('state_id');
            $filter_name_city = $request->query('city_name');
            $filter_date = $request->query('date');
            $filter_status_id = $request->query('status_id');

            $quoteQuery = Quote::select(
                'id',
                'availability_personal_shopper_id',
                'store_mall_id',
                'country_id',
                'state_id',
                'city_id',
                'user_id',
                'create_user_id',
                'date',
                'start_time',
                'end_time',
                'quantity_products',
                'comment',
                'status_id'
            )
                ->with(
                    'store.mall',
                    'country',
                    'state',
                    'city',
                    'status',
                    'customer',
                    'infoPersonalShopper'
                );


            if (isset($filter_id)) {
                $quoteQuery->where('id', $filter_id);
            }
            if (isset($filter_user_id)) {
                $quoteQuery->where('user_id', $filter_user_id);
            }
            if (isset($filter_name_customer)) {
                $quoteQuery->whereHas('customer', function ($query) use ($filter_name_customer) {
                    $query->where('name', 'like', '%' . $filter_name_customer . '%');
                });
            }
            if (isset($filter_personal_shopper_id)) {
                $quoteQuery->where('availability_personal_shopper_id', $filter_personal_shopper_id);
            }
            if (isset($filter_store_mall_id)) {
                $quoteQuery->where('store_mall_id', $filter_store_mall_id);
            }
            if (isset($filter_country_id)) {
                $quoteQuery->where('country_id', $filter_country_id);
            }
            if (isset($filter_state_id)) {
                $quoteQuery->where('state_id', $filter_state_id);
            }
            if (isset($filter_date)) {
                $quoteQuery->where('date', Carbon::parse($filter_date)->format('Y-m-d'));
            }
     


            if(isset($filter_name_city)){
                $quoteQuery->whereHas('city', function ($query) use ($filter_name_city) {
                    $query->where('name', 'like', '%' . $filter_name_city . '%');
                });
            }


            if (isset($filter_status_id)) {
                $quoteQuery->where('status_id', $filter_status_id);
            }


            $quotes = $quoteQuery->orderBy('created_at', 'desc')->paginate($perPage);


            return response()->json([
                'data' => $quotes
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }


    public function create(CreateQuoteRequest $request)
    {
        $personalShopperId = $request->input('availability_personal_shopper_id');
        $startTime = $request->input('start_time'); // Format: H:i:s
        $quantityProducts = $request->input('quantity_products');
        $date =  Carbon::parse($request->input('date'))->format('Y-m-d');
        $cityId = $request->input('city_id');

        $tasaProductosPorHora = 3;
        $endTime = Carbon::createFromFormat('H:i:s', $startTime)
            ->addHours(ceil($quantityProducts / $tasaProductosPorHora))
            ->format('H:i:s');

        $occupiedCount = $this->getOccupiedCount($personalShopperId, $startTime, $endTime, $date, null);


        if ($occupiedCount >= 3) {
            return response()->json(['error' => 'No se pueden agregar más citas en esta franja horaria.'], 422);
        }

        if (!$this->isTimeSlotAvailable($personalShopperId, $startTime, $endTime, $date, $cityId, null)) {
            return response()->json(['error' => 'El horario no está disponible.'], 422);
        }

        try {
            DB::beginTransaction();

            $authenticatedUser = Auth::user(); // Obtener el usuario autenticado

            $quote = new Quote([
                'availability_personal_shopper_id' => $personalShopperId,
                // 'mall_id' => $request->input('mall_id'),
                'store_mall_id' => $request->input('store_mall_id'),
                'country_id' => $request->input('country_id'),
                'state_id' => $request->input('state_id'),
                'city_id' => $cityId,
                'user_id' => $request->input('user_id'),
                'create_user_id' => $authenticatedUser->id,
                'date' => $date,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'quantity_products' => $quantityProducts,
                'comment' => $request->input('comment'),
                'status_id' => 1, // Valor fijo
            ]);

            $quote->save();



            // Crear un registro en QuoteLogStatus para el estado pendiente (status_id = 1)
            $quoteLogStatus = new QuoteLogStatus([
                'quote_id' => $quote->id,
                'status_id' => 1, // Estado pendiente
                'user_id' => $authenticatedUser->id, // Usar el ID del usuario autenticado
            ]);

            $quoteLogStatus->save();


            DB::commit(); // Confirmar la transacción

            return response()->json(['message' => 'Cita creada correctamente.', 'data' => $quote], 201);
        } catch (\Exception $e) {
            DB::rollBack(); // Revertir la transacción en caso de error

            // Manejar el error y devolver una respuesta adecuada
            return response()->json(['error' => 'Error al crear la cita: ' . $e->getMessage()], 500);
        }
    }



    public function updateStatus(UpdateQuoteStatusRequest $request, $quoteId)
    {

        try {
            DB::beginTransaction();

            // Obtener la cita relacionada por su ID
            $quote = Quote::findOrFail($quoteId);

            // Actualizar el estado de la cita en la tabla QuoteLogStatus
            $authenticatedUser = Auth::user(); // Obtener el usuario autenticado
            $statusId = $request->input('status_id');

            $quoteLogStatus = new QuoteLogStatus([
                'quote_id' => $quote->id,
                'status_id' => $statusId,
                'user_id' => $authenticatedUser->id,
            ]);

            $quoteLogStatus->save();

            // Actualizar el estado de la cita en el modelo Quote
            $quote->status_id = $statusId;
            $quote->save();

            DB::commit(); // Confirmar la transacción

            return response()->json(['message' => 'Estado de la cita actualizado correctamente.', 'data' => $quote], 200);
        } catch (\Exception $e) {
            DB::rollBack(); // Revertir la transacción en caso de error

            // Manejar el error y devolver una respuesta adecuada
            return response()->json(['error' => 'Error al actualizar el estado de la cita: ' . $e->getMessage()], 500);
        }
    }


    public function updateDateTime(Request $request, $quoteId)
    {
        // $request->validate([
        //     'start_time' => 'required|date_format:H:i:s',
        //     'date' => 'required|date',
        // ]);

        $startTime = $request->input('start_time');
        // $date = $request->input('date');
        $date =  Carbon::parse($request->input('date'))->format('Y-m-d') ;

        $availability_personal_shopper_id = $request->input('availability_personal_shopper_id');
        $comment = $request->input('comment');

        // Obtener la cita relacionada por su ID
        $quote = Quote::find($quoteId);

        if (!$quote) {
            return response()->json(['error' => 'La cita no se encontró.'], 404);
        }

        // Validar si la nueva fecha y hora son válidas
        $personalShopperId = isset($availability_personal_shopper_id) ? $availability_personal_shopper_id : $quote->availability_personal_shopper_id;
        $quantityProducts = $quote->quantity_products;
        $cityId = $quote->city_id;

        $tasaProductosPorHora = 3;
        $endTime = Carbon::createFromFormat('H:i:s', $startTime)
            ->addHours(ceil($quantityProducts / $tasaProductosPorHora))
            ->format('H:i:s');

        $occupiedCount = $this->getOccupiedCount($personalShopperId, $startTime, $endTime, $date, $quoteId);

        if ($occupiedCount >= 3) {
            return response()->json(['error' => 'No se pueden actualizar la cita a esta fecha y hora.'], 422);
        }

        if (!$this->isTimeSlotAvailable($personalShopperId, $startTime, $endTime, $date, $cityId, $quoteId)) {
            return response()->json(['error' => 'La nueva fecha y hora no están disponibles.'], 422);
        }

        try {
            DB::beginTransaction();

            // Actualizar la fecha y hora de la cita
            $quote->date = $date;
            $quote->start_time = $startTime;
            $quote->end_time = $endTime;
            $quote->availability_personal_shopper_id = $personalShopperId;
            if (isset($comment)) {
                $quote->comment = $comment;
            }

            $quote->save();

            DB::commit(); // Confirmar la transacción

            return response()->json(['message' => 'Fecha y hora de la cita actualizadas correctamente.', 'data' => $quote], 200);
        } catch (\Exception $e) {
            DB::rollBack(); // Revertir la transacción en caso de error

            // Manejar el error y devolver una respuesta adecuada
            return response()->json(['error' => 'Error al actualizar la fecha y hora de la cita: ' . $e->getMessage()], 500);
        }
    }


    private function getOccupiedCount($personalShopperId, $startTime, $endTime, $date, $quoteId)
    {
        $existingQuotes = Quote::where('availability_personal_shopper_id', $personalShopperId)
            ->where('date', $date)
            ->whereIn('status_id', [1, 2]);
        if ($quoteId) {
            $existingQuotes->where('id', '!=', $quoteId);
        }
        $existingQuotes = $existingQuotes->get();

        $count = 0;

        foreach ($existingQuotes as $quote) {
            $quoteStartTime = Carbon::parse($quote->start_time)->format('H:i:s');
            $quoteEndTime = Carbon::parse($quote->end_time)->format('H:i:s');

            if (($startTime >= $quoteStartTime && $startTime < $quoteEndTime) ||
                ($endTime > $quoteStartTime && $endTime <= $quoteEndTime) ||
                ($startTime <= $quoteStartTime && $endTime >= $quoteEndTime)
            ) {
                $count++;
            }
        }

        return $count;
    }

    private function isTimeSlotAvailable($personalShopperId, $startTime, $endTime, $date, $cityId, $quoteId)
    {
        $existingQuotes = Quote::where('availability_personal_shopper_id', $personalShopperId)
            ->where('date', $date);

        if ($quoteId) {
            $existingQuotes->where('id',  '!=', $quoteId);
        }
        $existingQuotes = $existingQuotes->get();


        $occupiedCount = 0;

        foreach ($existingQuotes as $quote) {
            $quoteStartTime = Carbon::parse($quote->start_time)->format('H:i:s');
            $quoteEndTime = Carbon::parse($quote->end_time)->format('H:i:s');

            if (($startTime >= $quoteStartTime && $startTime < $quoteEndTime) ||
                ($endTime > $quoteStartTime && $endTime <= $quoteEndTime) ||
                ($startTime <= $quoteStartTime && $endTime >= $quoteEndTime)
            ) {
                $occupiedCount++;

                if ($occupiedCount >= 3) {
                    return false;
                }
            }
        }

        $dayOfWeek = strftime("%A", strtotime($date));


        $userAvailabilitySlots = AvailabilityPersonalShopper::with('user')->where('user_id', $personalShopperId)
            ->whereHas('user', function ($query) use ($cityId) {
                $query->where('city_id', $cityId);
            })
            // ->where('date', $date)
            ->where('day', $dayOfWeek)
            ->get();

        foreach ($userAvailabilitySlots as $availability) {
            $availabilityStartTime = Carbon::parse($availability->start_time)->format('H:i:s');
            $availabilityEndTime = Carbon::parse($availability->end_time)->format('H:i:s');

            if (($startTime >= $availabilityStartTime && $startTime < $availabilityEndTime) &&
                ($endTime > $availabilityStartTime && $endTime <= $availabilityEndTime)
            ) {
                return true;
            }
        }

        return false;
    }
}
