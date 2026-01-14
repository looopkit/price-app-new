<?php

return [

    /*
    |--------------------------------------------------------------------------
    | MoySklad API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for MoySklad API integration
    |
    */

    'api' => [
        'base_url' => env('MOYSKLAD_API_URL', 'https://api.moysklad.ru/api/remap/1.2'),
        'timeout' => env('MOYSKLAD_API_TIMEOUT', 30),
        'retry_times' => env('MOYSKLAD_API_RETRY', 3),
        'retry_delay' => env('MOYSKLAD_API_RETRY_DELAY', 1000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Import Configuration
    |--------------------------------------------------------------------------
    */

    'import' => [
        'batch_size' => env('MOYSKLAD_IMPORT_BATCH_SIZE', 100),
        'chunk_size' => env('MOYSKLAD_IMPORT_CHUNK_SIZE', 50),
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Configuration
    |--------------------------------------------------------------------------
    */

    'webhooks' => [
        'enabled' => env('MOYSKLAD_WEBHOOKS_ENABLED', true),
        
        // Entities to create webhooks for
        'entities' => [
            'product' => ['CREATE', 'UPDATE', 'DELETE'],
            'variant' => ['CREATE', 'UPDATE', 'DELETE'],
            'counterparty' => ['CREATE', 'UPDATE', 'DELETE'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Sync Configuration
    |--------------------------------------------------------------------------
    */

    'sync' => [
        'auto_sync_on_create' => env('MOYSKLAD_AUTO_SYNC', true),
        'queue_connection' => env('MOYSKLAD_QUEUE_CONNECTION', 'redis'),
        'queue_name' => env('MOYSKLAD_QUEUE_NAME', 'moysklad'),
    ],

];