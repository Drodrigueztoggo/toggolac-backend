<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class LotsScraperService
{
    private Client    $client;
    private CookieJar $jar;

    private const LOGIN_URL  = 'https://888lots.com/login/login';
    private const CACHE_KEY  = 'lots_scraper_session';
    private const CACHE_TTL  = 21600; // 6 hours

    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36';

    public function __construct()
    {
        $this->jar    = new CookieJar();
        $this->client = new Client([
            'timeout'         => 20,
            'connect_timeout' => 10,
            'cookies'         => $this->jar,
            'allow_redirects' => ['max' => 5],
            'headers'         => [
                'User-Agent'      => self::USER_AGENT,
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
            $this->ensureLoggedIn();

            $html = $this->fetch($url);
            if ($html === null) {
                return ['price' => null, 'in_stock' => false, 'error' => '404'];
            }

            // Session expired — retry once after fresh login
            if ($this->isLoginPage($html)) {
                Cache::forget(self::CACHE_KEY);
                $this->login();
                $html = $this->fetch($url);
                if ($html === null) {
                    return ['price' => null, 'in_stock' => false, 'error' => '404'];
                }
            }

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

    // ── Auth ──────────────────────────────────────────────────────────────────

    private function ensureLoggedIn(): void
    {
        $cached = Cache::get(self::CACHE_KEY);
        if ($cached) {
            foreach ($cached as $data) {
                $this->jar->setCookie(new SetCookie($data));
            }
            return;
        }
        $this->login();
    }

    private function login(): void
    {
        // 1. GET login page → extract CSRF token
        $loginHtml = (string) $this->client->get(self::LOGIN_URL)->getBody();
        $csrf      = '';
        if (preg_match('/<input[^>]+name=["\']_token["\'][^>]+value=["\'](.*?)["\']/i', $loginHtml, $m) ||
            preg_match('/<input[^>]+value=["\'](.*?)["\'][^>]+name=["\']_token["\']/i', $loginHtml, $m)) {
            $csrf = $m[1];
        }

        // 2. POST credentials
        $this->client->post(self::LOGIN_URL, [
            'form_params' => [
                'email'    => env('LOTS_EMAIL', ''),
                'password' => env('LOTS_PASSWORD', ''),
                '_token'   => $csrf,
            ],
        ]);

        // 3. Persist cookies so the next 6 hours reuse this session
        Cache::put(self::CACHE_KEY, $this->jar->toArray(), self::CACHE_TTL);
        Log::info('LotsScraperService: session established');
    }

    private function isLoginPage(string $html): bool
    {
        return str_contains($html, 'name="email"') && str_contains($html, 'name="password"');
    }

    // ── HTTP ──────────────────────────────────────────────────────────────────

    private function fetch(string $url): ?string
    {
        $response = $this->client->get($url);
        if ($response->getStatusCode() === 404) {
            return null;
        }
        return (string) $response->getBody();
    }

    // ── Parse ─────────────────────────────────────────────────────────────────

    private function parse(string $html): array
    {
        return [
            'price'    => $this->parsePrice($html),
            'in_stock' => $this->parseInStock($html),
            'error'    => null,
        ];
    }

    private function parsePrice(string $html): ?float
    {
        // Regular price: <h3 class="pull-left">$12.99</h3>
        if (preg_match('/<h3[^>]*class=["\'][^"\']*pull-left[^"\']*["\'][^>]*>\s*\$\s*([\d,]+\.?\d*)/i', $html, $m)) {
            return (float) str_replace(',', '', $m[1]);
        }

        // Promo price: class="price price-new" or "price-new price-promo"
        if (preg_match('/class=["\'][^"\']*price-new[^"\']*["\'][^>]*>\s*\$\s*([\d,]+\.?\d*)/i', $html, $m)) {
            return (float) str_replace(',', '', $m[1]);
        }

        // Broad fallback: first $ amount on the page
        if (preg_match('/\$\s*([\d,]+\.\d{2})/i', $html, $m)) {
            return (float) str_replace(',', '', $m[1]);
        }

        return null;
    }

    private function parseInStock(string $html): bool
    {
        $lower = strtolower($html);

        // Positive signal: add-to-cart button present → product is purchasable
        if (str_contains($lower, 'add to cart') || str_contains($lower, 'add-to-cart')) {
            return true;
        }

        // No add-to-cart: check explicit OOS phrases outside of Vue templates
        // (888lots embeds "Out Of Stock" in a v-if template on every page)
        foreach (['sold out', 'no longer available', 'item not available'] as $phrase) {
            if (str_contains($lower, $phrase)) {
                return false;
            }
        }

        return false; // no buy button and no explicit OOS → treat as unavailable
    }
}
