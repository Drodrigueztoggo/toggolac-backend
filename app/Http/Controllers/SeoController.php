<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class SeoController extends Controller
{
    private const INDEXNOW_KEY  = '4f8e2c1d9a7b3e6f0d5c8a2b1e4f7d9c';
    private const INDEXNOW_HOST = 'toggolac.com';

    public function pingIndexNow(Request $request)
    {
        $url = $request->query('url');
        if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
            return response()->json(['status' => 'invalid url'], 400);
        }

        try {
            Http::timeout(5)->get('https://api.indexnow.org/indexnow', [
                'url' => $url,
                'key' => self::INDEXNOW_KEY,
            ]);
        } catch (\Throwable) {
            // Best-effort — never fail the user request over an SEO ping
        }

        return response()->json(['status' => 'ok']);
    }

    public function pingIndexNowBatch(Request $request)
    {
        $urls = $request->input('urlList', []);
        if (empty($urls)) {
            return response()->json(['status' => 'no urls'], 400);
        }

        try {
            Http::timeout(5)->post('https://api.indexnow.org/indexnow', [
                'host'    => self::INDEXNOW_HOST,
                'key'     => self::INDEXNOW_KEY,
                'urlList' => array_slice($urls, 0, 10000),
            ]);
        } catch (\Throwable) {
            // Best-effort
        }

        return response()->json(['status' => 'ok']);
    }
}
