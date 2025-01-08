<?php

namespace Jansamnan\Graphify;

use Jansamnan\Graphify\BasicShopifyAPI;
use Jansamnan\Graphify\APISession;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

class Graphify
{
    /**
     * The Shopify API instance.
     *
     * @var BasicShopifyAPI
     */
    protected $api;

    /**
     * Constructor.
     *
     * @return void
     */
    public function __construct()
    {
        $this->api = new BasicShopifyAPI(new Options());

        // Set API credentials and version from config
        $this->api->setApiKey(config('graphify.api_key'));
        $this->api->setApiSecret(config('graphify.api_secret'));
        $this->api->setVersion(config('graphify.api_version'));

        // Set session
        $this->api->setSession(new APISession(config('graphify.shop_domain'), config('graphify.access_token')));
    }

    /**
     * Execute a GraphQL query with variables.
     *
     * @param string $query
     * @param array $variables
     * @return mixed
     * @throws \Exception
     */
    public function query(string $query, array $variables = [])
    {
        $maxRetries = config('graphify.max_retries', 5);
        $retryDelay = config('graphify.retry_delay', 1); // in seconds

        $attempt = 0;

        do {
            try {
                $response = $this->api->graph($query, $variables);
                $body = $response['body'];

                // Rate Limiting and Cost Monitoring
                if (isset($body->extensions->cost)) {
                    $cost = $body->extensions->cost;
                    $requestedCost = $cost->requestedQueryCost;
                    $actualCost = $cost->actualQueryCost;
                    $currentlyAvailable = $cost->throttleStatus->currentlyAvailable;
                    $restoreRate = $cost->throttleStatus->restoreRate;

                    Log::info("GraphQL Request - Requested Cost: $requestedCost, Actual Cost: $actualCost, Currently Available: $currentlyAvailable");

                    $threshold = config('graphify.threshold', 50);
                    if ($currentlyAvailable < $threshold) {
                        $waitTime = ceil(($threshold - $currentlyAvailable) / $restoreRate);
                        Log::warning("Approaching rate limit. Waiting for {$waitTime} seconds.");
                        sleep($waitTime);
                    }
                }

                // Process and Return Data
                return $body;

            } catch (RequestException $e) {
                $statusCode = $e->getResponse()->getStatusCode();
                if ($statusCode == 429 && $attempt < $maxRetries) { // Rate limit exceeded
                    $retryAfter = $e->getResponse()->getHeader('Retry-After')[0] ?? null;
                    $waitTime = $retryAfter ? intval($retryAfter) : $retryDelay * pow(2, $attempt);
                    Log::warning("Rate limit exceeded. Retrying after {$waitTime} seconds.");
                    sleep($waitTime);
                    $attempt++;
                    continue; // Retry the request
                }

                // Log and rethrow exception if not rate limit related
                Log::error("GraphQL Query Error: " . $e->getMessage());
                throw $e;
            }
        } while ($attempt < $maxRetries);

        throw new \Exception('Maximum retry attempts reached for GraphQL query.');
    }
}
