<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'google_translate' => [
        'key' => env('GOOGLE_TRANSLATE_API_KEY'),
    ],

    'shippo' => [
        'token'          => env('SHIPPO_API_TOKEN'),
        'origin_name'    => env('SHIPPO_ORIGIN_NAME',   'Toggolac'),
        'origin_street'  => env('SHIPPO_ORIGIN_STREET', '7819 NW 104th Ave Apt 6'),
        'origin_city'    => env('SHIPPO_ORIGIN_CITY',   'Doral'),
        'origin_state'   => env('SHIPPO_ORIGIN_STATE',  'FL'),
        'origin_zip'     => env('SHIPPO_ORIGIN_ZIP',    '33178'),
        'fallback_rate'  => env('SHIPPO_FALLBACK_RATE', 25),
        // Per-pound rate for Colombia shipments (USD per lb)
        'colombia_rate_per_lb' => env('SHIPPO_COLOMBIA_RATE_PER_LB', 4.35),
    ],
];
