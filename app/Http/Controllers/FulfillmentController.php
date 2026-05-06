<?php

namespace App\Http\Controllers;

use App\Models\FulfillmentJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FulfillmentController extends Controller
{
    // Simple shared secret so the Python worker can authenticate without a user session
    private function authorized(Request $request): bool
    {
        $expected = env('FULFILLMENT_API_KEY');
        if (!$expected) return false;
        return $request->header('X-Fulfillment-Key') === $expected;
    }

    /** GET /api/fulfillment/pending — jobs the worker should act on */
    public function pending(Request $request)
    {
        if (!$this->authorized($request)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $jobs = FulfillmentJob::where('status', 'pending')
            ->where('attempts', '<', 3)
            ->orderBy('created_at')
            ->limit(20)
            ->get();

        return response()->json($jobs);
    }

    /** POST /api/fulfillment/{id}/update — worker reports result */
    public function update(Request $request, int $id)
    {
        if (!$this->authorized($request)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $job = FulfillmentJob::findOrFail($id);

        $status = $request->input('status');
        $allowed = ['processing', 'completed', 'failed', 'skipped'];
        if (!in_array($status, $allowed)) {
            return response()->json(['error' => 'Invalid status'], 422);
        }

        $job->status             = $status;
        $job->attempts           = $job->attempts + 1;
        $job->last_attempted_at  = now();

        if ($request->filled('lots_order_id')) {
            $job->lots_order_id = $request->input('lots_order_id');
        }
        if ($request->filled('notes')) {
            $job->notes = $request->input('notes');
        }
        if ($status === 'completed') {
            $job->completed_at = now();
        }

        $job->save();

        Log::info("FulfillmentJob #{$id} → {$status}" . ($job->lots_order_id ? " lots#{$job->lots_order_id}" : ''));

        return response()->json(['ok' => true]);
    }
}
