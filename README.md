# Graphify

Graphify is a Laravel package that integrates Shopify's GraphQL API with built-in rate limiting and retry mechanisms.

## Features

- Execute Shopify GraphQL queries with ease.
- Built-in rate limiting to comply with Shopify's API usage policies.
- Automatic retry on rate limit errors (`429 Too Many Requests`).
- Facade for simple and expressive syntax.
- Artisan commands for common tasks.

## Installation

You can install the package via Composer:

```bash
composer require jansamnan/graphify

php artisan vendor:publish --provider="Jansamnan\Graphify\GraphifyServiceProvider" --tag=config

config/graphyfy.php

SHOPIFY_API_KEY=your-api-key
SHOPIFY_API_SECRET=your-api-secret
SHOPIFY_API_VERSION=2023-10
SHOPIFY_SHOP_DOMAIN=your-shop.myshopify.com
SHOPIFY_ACCESS_TOKEN=your-access-token
SHOPIFY_REST_LIMIT=2
SHOPIFY_GRAPH_LIMIT=50
SHOPIFY_THRESHOLD=50
SHOPIFY_MAX_RETRIES=5
SHOPIFY_RETRY_DELAY=1


use Graphify;

$query = <<<'GRAPHQL'
query getProducts($first: Int!, $after: String) {
  products(first: $first, after: $after) {
    edges {
      cursor
      node {
        id
        title
      }
    }
    pageInfo {
      hasNextPage
      endCursor
    }
  }
}
GRAPHQL;

$variables = [
    'first' => 10,
    'after' => null,
];

try {
    $response = Graphify::query($query, $variables);
    $products = $response->data->products->edges;

    foreach ($products as $productEdge) {
        $productId = $productEdge->node->id;
        $productTitle = $productEdge->node->title;
        echo "Product ID: $productId, Title: $productTitle\n";
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage();
} 

License
MIT
