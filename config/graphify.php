<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Shopify API Credentials
    |--------------------------------------------------------------------------
    |
    | Here you may specify your Shopify API credentials. These credentials
    | are used to authenticate with Shopify's API.
    |
    */

    'api_key' => env('SHOPIFY_API_KEY', 'your-api-key'),
    'api_secret' => env('SHOPIFY_API_SECRET', 'your-api-secret'),
    'api_version' => env('SHOPIFY_API_VERSION', '2023-10'),

    /*
    |--------------------------------------------------------------------------
    | Shopify Session Details
    |--------------------------------------------------------------------------
    |
    | These settings define the Shopify store domain and access token used
    | for API interactions.
    |
    */

    'shop_domain' => env('SHOPIFY_SHOP_DOMAIN', 'your-shop.myshopify.com'),
    'access_token' => env('SHOPIFY_ACCESS_TOKEN', 'your-access-token'),

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting Configuration
    |--------------------------------------------------------------------------
    |
    | These settings control the rate limiting behavior of the package.
    | Adjust these values based on your application's needs and Shopify's
    | API rate limits.
    |
    */

    'rest_limit' => env('SHOPIFY_REST_LIMIT', 2), // REST requests per second
    'graph_limit' => env('SHOPIFY_GRAPH_LIMIT', 50), // GraphQL points per second
    'threshold' => env('SHOPIFY_THRESHOLD', 50), // Threshold for available points

    /*
    |--------------------------------------------------------------------------
    | Retry Configuration
    |--------------------------------------------------------------------------
    |
    | These settings define how the package should handle retries in case
    | of rate limiting or transient errors.
    |
    */

    'max_retries' => env('SHOPIFY_MAX_RETRIES', 5),
    'retry_delay' => env('SHOPIFY_RETRY_DELAY', 1), // in seconds
];
