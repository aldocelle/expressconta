<?php

return [
    'default' => env('SESSION_DRIVER', 'file'),
    'lifetime' => env('SESSION_LIFETIME', 120),
    'expire_on_close' => false,
    'encrypt' => false,
    'files' => storage_path('framework/sessions'),
    'cookie' => 'expressconta_session',
    'path' => '/',
    'domain' => env('SESSION_DOMAIN', null),
    'secure' => env('APP_ENV') === 'production',
    'http_only' => true,
    'same_site' => 'lax',
    'partitioned' => false,
];
