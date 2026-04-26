<?php

namespace App\Http\Controllers;

use App\Models\Product;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TelegramCallbackController extends Controller
{
    public function handle(Request $request)
    {
        $body = $request->all();

        if (! isset($body['callback_query'])) {
            return response()->json(['ok' => true]);
        }

        $query      = $body['callback_query'];
        $callbackId = $query['id'];
        $data       = $query['data'] ?? '';
        $messageId  = $query['message']['message_id'] ?? null;
        $chatId     = $query['message']['chat']['id'] ?? null;

        [$action, $productId, $extra] = array_pad(explode(':', $data, 3), 3, null);

        $product = Product::withTrashed()->find((int) $productId);

        $responseText = match(true) {
            !$product                    => '❌ Producto no encontrado.',
            $action === 'republish'      => $this->republish($product),
            $action === 'archive'        => $this->archive($product),
            $action === 'update_price'   => $this->updatePrice($product, (float) $extra),
            $action === 'wait_oos'       => '⏳ OK — revisaremos en 48h automáticamente.',
            $action === 'view'           => "👉 adm.toggolac.com/panel/productos/{$product->id}/editar",
            $action === 'retry'          => $this->retry($product),
            default                      => '❓ Acción desconocida.',
        };

        $this->answerCallback($callbackId, $responseText);

        if ($chatId && $messageId) {
            $this->appendToMessage($chatId, $messageId, "✅ {$responseText}");
        }

        return response()->json(['ok' => true]);
    }

    private function republish(Product $product): string
    {
        $product->restore();
        if (isset($product->active)) $product->active = true;
        $product->save();
        return "Re-publicado: {$product->name_product}";
    }

    private function archive(Product $product): string
    {
        $product->delete();
        return "Archivado: {$product->name_product}";
    }

    private function updatePrice(Product $product, float $newPrice): string
    {
        if ($newPrice <= 0) return '❌ Precio inválido.';
        $product->restore();
        if (isset($product->active)) $product->active = true;
        $product->price_to = $newPrice;
        $product->save();
        return "Precio actualizado a \${$newPrice} y re-publicado.";
    }

    private function retry(Product $product): string
    {
        $product->supplier_consecutive_errors = 0;
        $product->save();
        return "Contador de errores reiniciado para {$product->name_product}.";
    }

    private function answerCallback(string $callbackId, string $text): void
    {
        try {
            (new Client())->post(
                "https://api.telegram.org/bot" . env('TELEGRAM_BOT_TOKEN') . "/answerCallbackQuery",
                ['json' => ['callback_query_id' => $callbackId, 'text' => $text]]
            );
        } catch (\Throwable $e) {
            Log::error('TelegramCallback answerCallback failed: ' . $e->getMessage());
        }
    }

    private function appendToMessage(string $chatId, int $messageId, string $note): void
    {
        try {
            (new Client())->post(
                "https://api.telegram.org/bot" . env('TELEGRAM_BOT_TOKEN') . "/sendMessage",
                ['json' => [
                    'chat_id'           => $chatId,
                    'text'              => $note,
                    'reply_to_message_id' => $messageId,
                ]]
            );
        } catch (\Throwable $e) {
            Log::error('TelegramCallback appendToMessage failed: ' . $e->getMessage());
        }
    }
}
