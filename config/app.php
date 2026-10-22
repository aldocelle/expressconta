<?php

return ['frontend_url' => env('FRONTEND_URL'), 'name' => 'ExpressConta', 'env' => env('APP_ENV', 'production'), 'debug' => (bool) env('APP_DEBUG', false), 'url' => env('APP_URL', 'http://localhost:8000'), 'timezone' => 'UTC', 'locale' => 'es', 'fallback_locale' => 'en', 'faker_locale' => 'es_CL', 'key' => env('APP_KEY'), 'cipher' => 'AES-256-CBC', 'previous_keys' => [], 'maintenance' => ['driver' => 'file']];
