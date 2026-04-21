<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PrismicTranslateController extends Controller
{
    private const CACHE_VERSION = 'v4';

    private const SKIP_TRANSLATION_KEYS = [
        'id',
        'uid',
        'lang',
        'type',
        'url',
        'slug',
        'link_type',
        'linktype',
        'target',
        'href',
        'html',
        'embed_url',
        'author_url',
        'provider_url',
        'thumbnail_url',
        'thumbnail_width',
        'thumbnail_height',
        'width',
        'height',
        'copyright',
        'version',
        'first_publication_date',
        'last_publication_date',
        'tags',
        'slugs',
        'dimensions',
        'edit',
        'data',
        'spans',
        'direction',
    ];

    public function translate(Request $request): JsonResponse
    {
        $payload = $request->input('payload', $request->input('data'));
        $target = $this->normalizeTarget((string) $request->input('target', $request->input('TGGlanguage', 'en-us')));

        if ($payload === null || $target === '') {
            return response()->json(['payload' => $payload]);
        }

        if (str_starts_with($target, 'es')) {
            return response()->json(['payload' => $payload]);
        }

        $cacheKey = 'prismic_translate_' . self::CACHE_VERSION . '_' . $target . '_' . sha1(json_encode($payload));

        $translated = Cache::remember($cacheKey, now()->addDays(30), function () use ($payload, $target) {
            return $this->translateValue($payload, $target);
        });

        return response()->json(['payload' => $translated]);
    }

    private function translateValue(mixed $value, string $target, ?string $key = null): mixed
    {
        if (!is_array($value)) {
            if (is_string($value) && $this->shouldTranslateString($key, $value)) {
                return $this->translateText($value, $target);
            }

            return $value;
        }

        if ($this->isAssoc($value)) {
            $result = [];

            foreach ($value as $key => $item) {
                $result[$key] = $this->translateValue($item, $target, is_string($key) ? $key : null);
            }

            return $result;
        }

        return array_map(function ($item) use ($target, $key) {
            return $this->translateValue($item, $target, $key);
        }, $value);
    }

    private function translateText(string $text, string $target): string
    {
        $translate = new GoogleTranslateController();

        return $translate->translateText($text, $target) ?? $text;
    }

    private function normalizeTarget(string $target): string
    {
        $normalized = strtolower(trim($target));

        if ($normalized === 'en' || $normalized === 'en-us') {
            return 'en-us';
        }

        if ($normalized === 'es' || $normalized === 'es-co') {
            return 'es-co';
        }

        return $normalized;
    }

    private function isAssoc(array $value): bool
    {
        if ($value === []) {
            return false;
        }

        return array_keys($value) !== range(0, count($value) - 1);
    }

    private function shouldTranslateString(?string $key, string $value): bool
    {
        $normalizedKey = strtolower(trim((string) $key));

        if ($normalizedKey !== '' && $this->isTechnicalKey($normalizedKey)) {
            return false;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return false;
        }

        if ($this->looksLikeTechnicalValue($trimmed)) {
            return false;
        }

        return true;
    }

    private function isTechnicalKey(string $key): bool
    {
        if (in_array($key, self::SKIP_TRANSLATION_KEYS, true)) {
            return true;
        }

        foreach (['url', 'uid', 'id', 'lang', 'type', 'slug'] as $suffix) {
            if (str_ends_with($key, '_' . $suffix)) {
                return true;
            }
        }

        return false;
    }

    private function looksLikeTechnicalValue(string $value): bool
    {
        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://') || str_contains($value, '://')) {
            return true;
        }

        if (str_starts_with($value, '<iframe') || str_starts_with($value, '<img') || str_contains($value, '</')) {
            return true;
        }

        if (preg_match('/^[0-9]+([\/\.\-:\s][0-9]+)*$/', $value) === 1) {
            return true;
        }

        return false;
    }
}
