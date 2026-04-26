<?php

namespace App\Jobs;

use App\Models\CategoryMargin;
use App\Models\Product;
use App\Services\LotsScraperService;
use GuzzleHttp\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProductPriceSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const MAX_CONSECUTIVE_ERRORS   = 3;
    private const OOS_RECHECK_HOURS        = 48;

    public function handle(LotsScraperService $scraper): void
    {
        $products = Product::query()
            ->whereNotNull('supplier_url')
            ->whereNotNull('supplier_cost')
            ->where('sync_frozen', false)
            ->whereNull('deleted_at')
            ->get();

        foreach ($products as $product) {
            $this->syncProduct($product, $scraper);
        }
    }

    private function syncProduct(Product $product, LotsScraperService $scraper): void
    {
        $result = $scraper->scrape($product->supplier_url);

        // ── Rule 7: scraper error / 404 ────────────────────────────────────
        if ($result['error'] !== null) {
            $errors = $product->supplier_consecutive_errors + 1;
            $product->supplier_consecutive_errors = $errors;
            $product->save();

            if ($errors >= self::MAX_CONSECUTIVE_ERRORS) {
                $this->unpublish($product);
                $this->alert($product, '🚫 Archivado — 3 errores consecutivos', [
                    ['text' => '❌ Archivar permanente', 'callback_data' => "archive:{$product->id}"],
                    ['text' => '🔄 Reintentar', 'callback_data' => "retry:{$product->id}"],
                ]);
            }
            return;
        }

        // Reset error counter on successful scrape
        $product->supplier_consecutive_errors = 0;
        $product->supplier_last_checked_at    = now();

        $newSupplierPrice = $result['price'];
        $inStock          = $result['in_stock'];

        // ── Rule 4: out of stock ───────────────────────────────────────────
        if (! $inStock) {
            if ($product->supplier_in_stock) {
                // Just went OOS — hide and alert
                $product->supplier_in_stock = false;
                $this->unpublish($product);
                $this->alert($product, '📦 Sin stock — Ocultado automáticamente', [
                    ['text' => '⏳ Esperar 48h', 'callback_data' => "wait_oos:{$product->id}"],
                    ['text' => '❌ Archivar ya',  'callback_data' => "archive:{$product->id}"],
                ]);
            } elseif ($product->supplier_last_checked_at &&
                      now()->diffInHours($product->supplier_last_checked_at) >= self::OOS_RECHECK_HOURS) {
                // Still OOS after 48h → archive
                $this->archive($product);
                $this->alert($product, '❌ Archivado — Sin stock por más de 48h', []);
            }
            $product->save();
            return;
        }

        // Back in stock
        if (! $product->supplier_in_stock) {
            $product->supplier_in_stock = true;
            $this->republish($product);
            $this->alert($product, '✅ Volvió a stock — Re-publicado automáticamente', []);
        }

        // ── Rules 2 & 3: price change ──────────────────────────────────────
        if ($newSupplierPrice && $newSupplierPrice !== (float) $product->supplier_cost) {
            $oldSupplierPrice = (float) $product->supplier_cost;
            $changePct        = (($newSupplierPrice - $oldSupplierPrice) / $oldSupplierPrice) * 100;

            if ($changePct > 0) {
                $this->handlePriceIncrease($product, $oldSupplierPrice, $newSupplierPrice, $changePct);
            }
            // Price decrease → keep your_price, just update supplier cost silently
        }

        // ── Rule 5: margin floor ───────────────────────────────────────────
        if ($newSupplierPrice) {
            $this->enforceMarginFloor($product, $newSupplierPrice);
            $product->supplier_cost = $newSupplierPrice;
        }

        $product->save();
    }

    private function handlePriceIncrease(
        Product $product,
        float $oldPrice,
        float $newPrice,
        float $changePct
    ): void {
        $margin       = CategoryMargin::forCategory($product->category_id ?? 0);
        $alertThresh  = (float) $margin->price_increase_alert_threshold;
        $unpublishThresh = (float) $margin->price_increase_unpublish_threshold;

        $newYourPrice = round($newPrice * (1 + (float) $margin->min_margin_percent / 100), 2);

        if ($changePct > $unpublishThresh) {
            // Rule 2c: spike too large — unpublish
            $this->unpublish($product);
            $this->alert($product,
                "🚨 Subida de precio +{$this->pct($changePct)} — Auto-ocultado",
                [
                    ['text' => '✅ Re-publicar igual',     'callback_data' => "republish:{$product->id}"],
                    ['text' => '💰 Actualizar precio',     'callback_data' => "update_price:{$product->id}:{$newYourPrice}"],
                    ['text' => '❌ Archivar',              'callback_data' => "archive:{$product->id}"],
                ],
                $oldPrice, $newPrice, $newYourPrice
            );
        } elseif ($changePct > $alertThresh) {
            // Rule 2b: significant increase — adjust and alert
            $product->price_to = $newYourPrice;
            $this->alert($product,
                "⚠️ Precio ajustado +{$this->pct($changePct)}",
                [
                    ['text' => '👁 Ver producto', 'callback_data' => "view:{$product->id}"],
                    ['text' => '❌ Archivar',     'callback_data' => "archive:{$product->id}"],
                ],
                $oldPrice, $newPrice, $newYourPrice
            );
        } else {
            // Rule 2a: small increase — adjust silently
            $product->price_to = $newYourPrice;
            Log::info("PriceSync: silent adjustment on product {$product->id} (+{$this->pct($changePct)})");
        }
    }

    private function enforceMarginFloor(Product $product, float $supplierPrice): void
    {
        $margin   = CategoryMargin::forCategory($product->category_id ?? 0);
        $minPrice = $margin->minSellingPrice($supplierPrice);

        if ((float) $product->price_to < $minPrice) {
            $product->price_to = $minPrice;
            $this->alert($product,
                "🔒 Precio mínimo aplicado — margen protegido",
                [['text' => '👁 Ver producto', 'callback_data' => "view:{$product->id}"]],
                null, $supplierPrice, $minPrice
            );
        }
    }

    // ── Product state helpers ────────────────────────────────────────────────

    private function unpublish(Product $product): void
    {
        // Assuming a `status` or `active` column — adapt to your schema
        if (isset($product->active)) {
            $product->active = false;
        }
    }

    private function republish(Product $product): void
    {
        if (isset($product->active)) {
            $product->active = true;
        }
    }

    private function archive(Product $product): void
    {
        $product->delete(); // soft delete
    }

    // ── Telegram ─────────────────────────────────────────────────────────────

    private function alert(
        Product $product,
        string $event,
        array $buttons,
        ?float $oldSupplierPrice = null,
        ?float $newSupplierPrice = null,
        ?float $yourPrice = null
    ): void {
        $token  = env('TELEGRAM_BOT_TOKEN');
        $chatId = env('TELEGRAM_ADMIN_CHAT_ID');
        if (! $token || ! $chatId) return;

        $lines = [
            "🔔 *{$event}*",
            "📦 Producto: [{$product->name_product}](https://adm.toggolac.com/panel/productos/{$product->id}/editar)",
            "🔗 [Ver en 888lots]({$product->supplier_url})",
        ];

        if ($oldSupplierPrice !== null) {
            $lines[] = "💰 Proveedor: \${$oldSupplierPrice} → \${$newSupplierPrice}";
        } elseif ($newSupplierPrice !== null) {
            $lines[] = "💰 Proveedor: \${$newSupplierPrice}";
        }

        if ($yourPrice !== null) {
            $lines[] = "🏷️ Tu precio: \${$yourPrice}";
        }

        $text = implode("\n", $lines);

        $keyboard = [];
        foreach (array_chunk($buttons, 2) as $row) {
            $keyboard[] = $row;
        }

        try {
            (new Client())->post(
                "https://api.telegram.org/bot{$token}/sendMessage",
                ['json' => [
                    'chat_id'      => $chatId,
                    'text'         => $text,
                    'parse_mode'   => 'Markdown',
                    'reply_markup' => ['inline_keyboard' => $keyboard],
                ]]
            );
        } catch (\Throwable $e) {
            Log::error('PriceSync Telegram alert failed: ' . $e->getMessage());
        }
    }

    private function pct(float $val): string
    {
        return number_format($val, 1) . '%';
    }
}
