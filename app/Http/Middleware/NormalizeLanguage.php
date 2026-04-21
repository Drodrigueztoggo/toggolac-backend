<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NormalizeLanguage
{
    public function handle(Request $request, Closure $next): Response
    {
        $language = $this->normalizeLanguage($request->input('TGGlanguage', $request->query('TGGlanguage')));

        if ($language) {
            $request->merge(['TGGlanguage' => $language]);

            if ($request->query->has('TGGlanguage')) {
                $request->query->set('TGGlanguage', $language);
            }

            if ($request->request->has('TGGlanguage')) {
                $request->request->set('TGGlanguage', $language);
            }
        }

        return $next($request);
    }

    private function normalizeLanguage(mixed $value): string
    {
        $lang = is_string($value) ? strtolower(trim($value)) : '';

        if (in_array($lang, ['en', 'en-us', 'en_us', 'en-us', 'en-us'], true)) {
            return 'en';
        }

        if (in_array($lang, ['es', 'es-co', 'es_co', 'es-es'], true)) {
            return 'es';
        }

        if (str_starts_with($lang, 'en')) {
            return 'en';
        }

        if (str_starts_with($lang, 'es')) {
            return 'es';
        }

        return $lang ?: 'es';
    }
}
