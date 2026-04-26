<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

class LotsScraperService
{
    private Client $client;

    public function __construct()
    {
        $this->client = new Client([
            'timeout'         => 15,
            'connect_timeout' => 10,
            'headers'         => [
                'User-Agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
                'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.5',
            ],
        ]);
    }

    /**
     * Fetch current price and stock status for a 888lots product URL.
     *
     * Returns: ['price' => float|null, 'in_stock' => bool, 'error' => string|null]
     */
    public function scrape(string $url): array
    {
        try {
            $response = $this->client->get($url);
            $status   = $response->getStatusCode();

            if ($status === 404) {
                return ['price' => null, 'in_stock' => false, 'error' => '404'];
            }

            if ($status !== 200) {
                return ['price' => null, 'in_stock' => false, 'error' => "HTTP {$status}"];
            }

            $html = (string) $response->getBody();
            return $this->parse($html);

        } catch (RequestException $e) {
            $code = $e->hasResponse() ? $e->getResponse()->getStatusCode() : 0;
            Log::warning("LotsScraperService: request failed for {$url} — " . $e->getMessage());
            return ['price' => null, 'in_stock' => false, 'error' => $code === 404 ? '404' : $e->getMessage()];
        } catch (\Throwable $e) {
            Log::error("LotsScraperService: unexpected error for {$url} — " . $e->getMessage());
            return ['price' => null, 'in_stock' => false, 'error' => $e->getMessage()];
        }
    }

    private function parse(string $html): array
    {
        $price   = null;
        $inStock = true;

        // Price: look for patterns like $12.99 or 12.99 near "price" keywords
        if (preg_match('/\$\s*([\d,]+\.?\d*)/i', $html, $m)) {
            $price = (float) str_replace(',', '', $m[1]);
        }

        // Out of stock signals
        $outOfStockPhrases = [
            'out of stock',
            'sold out',
            'unavailable',
            'no longer available',
            'item not available',
        ];
        $lowerHtml = strtolower($html);
        foreach ($outOfStockPhrases as $phrase) {
            if (str_contains($lowerHtml, $phrase)) {
                $inStock = false;
                break;
            }
        }

        return [
            'price'    => $price,
            'in_stock' => $inStock,
            'error'    => null,
        ];
    }
}
