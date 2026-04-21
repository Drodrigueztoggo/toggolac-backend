<?php

namespace App\Http\Controllers;

use App\Models\NewsletterSubscriber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class NewsletterSubscriberController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'accepted_terms' => ['required', 'accepted'],
            'source' => ['nullable', 'string', 'max:100'],
            'page_url' => ['nullable', 'string', 'max:2048'],
        ]);

        try {
            $subscriber = NewsletterSubscriber::updateOrCreate(
                ['email' => mb_strtolower(trim($validated['email']))],
                [
                    'source' => $validated['source'] ?? 'footer',
                    'page_url' => $validated['page_url'] ?? null,
                    'accepted_terms' => true,
                    'consented_at' => now(),
                    'ip_address' => $request->ip(),
                    'user_agent' => substr((string) $request->userAgent(), 0, 1000),
                ]
            );

            return response()->json([
                'status' => 'success',
                'message' => $subscriber->wasRecentlyCreated
                    ? 'Correo guardado correctamente.'
                    : 'Ese correo ya estaba registrado y fue actualizado.',
                'data' => $subscriber,
            ], $subscriber->wasRecentlyCreated ? 201 : 200);
        } catch (Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se pudo guardar el correo.',
            ], 500);
        }
    }

    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 20);
        $noPaginate = $request->boolean('no_paginate');

        $query = NewsletterSubscriber::query()
            ->when($request->filled('search'), function ($query) use ($request) {
                $query->where('email', 'like', '%' . $request->search . '%');
            })
            ->orderByDesc('created_at');

        $items = $noPaginate ? $query->get() : $query->paginate($perPage);

        if ($noPaginate) {
            return $items;
        }

        return [
            'data' => $items->getCollection(),
            'current_page' => $items->currentPage(),
            'per_page' => $items->perPage(),
            'total' => $items->total(),
            'last_page' => $items->lastPage(),
            'next_page_url' => $items->nextPageUrl(),
            'prev_page_url' => $items->previousPageUrl(),
        ];
    }

    public function destroy(int $id): JsonResponse
    {
        $subscriber = NewsletterSubscriber::find($id);

        if ($subscriber === null) {
            return response()->json([
                'status' => 'error',
                'message' => 'Suscriptor no encontrado.',
            ], 404);
        }

        $subscriber->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Suscriptor eliminado correctamente.',
        ]);
    }
}
