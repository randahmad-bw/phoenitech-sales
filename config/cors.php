<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    | Configured for React frontend on localhost:5173 during development.
    |--------------------------------------------------------------------------
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    /*
     | NOTE: never add '*' here. With 'supports_credentials' => true a wildcard
     | origin is both invalid per the CORS spec and a security hole: it would let
     | any site on the internet call this API with the signed-in user's credentials.
     | Add real deployment origins to CORS_ALLOWED_ORIGINS instead.
     */
    'allowed_origins' => array_values(array_filter(array_map('trim', explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost:5173,http://localhost:3000,http://127.0.0.1:5173,https://sales.phoenitech.sy'))), fn ($origin) => $origin !== '' && $origin !== '*')),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
