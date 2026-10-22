<?php

use Monolog\Handler\StreamHandler;

return ['default' => env('LOG_CHANNEL', 'single'), 'channels' => ['single' => ['driver' => 'single', 'path' => storage_path('logs/laravel.log'), 'level' => env('LOG_LEVEL', 'info'), 'replace_placeholders' => true], 'stderr' => ['driver' => 'monolog', 'handler' => StreamHandler::class, 'with' => ['stream' => 'php://stderr']]]];
