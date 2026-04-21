<?php
namespace App\Http\Controllers;

use Illuminate\Support\Facades\Cache;
use Stichoza\GoogleTranslate\GoogleTranslate;

class GoogleTranslateController extends Controller
{
    private const CACHE_VERSION = 'v5';

    public function translateText(string $text, string $target): ?string
    {
        $text = trim($text);
        $target = $this->normalizeTarget($target);

        if ($text === '' || $target === '') {
            return $text;
        }

        if ($target === 'es') {
            return $text;
        }

        $cacheKey = 'translation_' . self::CACHE_VERSION . '_' . md5($text . '_' . $target);
        $cached = Cache::get($cacheKey);

        if (is_string($cached) && trim($cached) !== '') {
            return $cached;
        }

        $translated = $this->translateWithFallbacks($text, $target);

        if ($translated === null || trim($translated) === '') {
            return $text;
        }

        Cache::put($cacheKey, $translated, now()->addDays(30));

        return $translated;
    }

    private function translateWithFallbacks(string $text, string $target): ?string
    {
        $translated = $this->translateOnce($text, $target);
        if ($translated !== null && trim($translated) !== '') {
            return $translated;
        }

        if ($this->shouldChunkTranslate($text)) {
            $chunked = $this->translateChunked($text, $target);
            if ($chunked !== null && trim($chunked) !== '') {
                return $chunked;
            }
        }

        return null;
    }

    private function translateOnce(string $text, string $target): ?string
    {
        try {
            return (new GoogleTranslate($target))
                ->setSource()
                ->setTarget($target)
                ->translate($text);
        } catch (\Throwable $e) {
            report($e);
            return null;
        }
    }

    private function translateChunked(string $text, string $target): ?string
    {
        $chunks = preg_split('/(?<=[.!?])\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        if (!$chunks || count($chunks) <= 1) {
            $chunks = $this->splitByLength($text);
        }

        $translated = [];

        foreach ($chunks as $chunk) {
            $piece = $this->translateOnce(trim($chunk), $target);
            $translated[] = $piece ?? trim($chunk);
        }

        $joined = trim(implode(' ', $translated));

        return $joined !== '' ? $joined : null;
    }

    private function splitByLength(string $text, int $maxLength = 180): array
    {
        $length = mb_strlen($text);
        if ($length <= $maxLength) {
            return [$text];
        }

        $chunks = [];
        for ($offset = 0; $offset < $length; $offset += $maxLength) {
            $chunks[] = mb_substr($text, $offset, $maxLength);
        }

        return $chunks;
    }

    private function shouldChunkTranslate(string $text): bool
    {
        return mb_strlen($text) > 120 || str_contains($text, "\n");
    }

    private function normalizeTarget(string $target): string
    {
        $lang = strtolower(trim($target));

        if ($lang === '') {
            return '';
        }

        if (str_starts_with($lang, 'en')) {
            return 'en';
        }

        if (str_starts_with($lang, 'es')) {
            return 'es';
        }

        return $lang;
    }
}
