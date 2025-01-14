<?php

namespace Jansamnan\Graphify;

use Jansamnan\Graphify\Options;
use Jansamnan\Graphify\Session;
use Illuminate\Support\Facades\Log;
use Jansamnan\Graphify\BasicShopifyAPI;
use GuzzleHttp\Exception\RequestException;

class Graphify
{
    /**
     * The Shopify API instance.
     *
     * @var BasicShopifyAPI
     */
    protected $api;

     /**
     * The Shopify API instance.
     *
     * @var Options
     */
    protected $options;

     /**
     * The Shopify API instance.
     *
     * @var int
     */
    protected $maxRetries;

     /**
     * The Shopify API instance.
     *
     * @var int
     */
    protected $retryDelay;


    /**
     * Constructor.
     *
     * @return void
     */
    public function __construct()
    {
        $this->options = new Options();
        $this->options->setApiKey(config('graphify.api_key'));
        $this->options->setApiSecret(config('graphify.api_secret'));
        $this->options->setVersion(config('graphify.api_version'));
        $this->api = new BasicShopifyAPI($this->options);
        $this->maxRetries = config('graphify.max_retries', 5);
        $this->retryDelay = config('graphify.retry_delay', 1);
    }

    public function setCredentials(array $data)
    {
        $this->api->setSession(new Session($data['domain'], $data['token']));
        return $this;
    }


    public function graph(string $query, array $variables = [], bool $sync = true)
    {
        return $this->api->getGraphClient()->request($query, $variables, $sync);
    }

    public function graphPaginate(string $query, array $variables = [], callable $callback = null, bool $sync = true)
    {
        return $this->api->getGraphClient()->requestPaginate($query, $variables, $callback , $sync);
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
                if ($statusCode == 429 && $attempt < $this->maxRetries) { // Rate limit exceeded
                    $retryAfter = $e->getResponse()->getHeader('Retry-After')[0] ?? null;
                    $waitTime = $retryAfter ? intval($retryAfter) : $this->retryDelay * pow(2, $attempt);
                    Log::warning("Rate limit exceeded. Retrying after {$waitTime} seconds.");
                    sleep($waitTime);
                    $attempt++;
                    continue; // Retry the request
                }

                // Log and rethrow exception if not rate limit related
                Log::error("GraphQL Query Error: " . $e->getMessage());
                throw $e;
            }
        } while ($attempt < $this->maxRetries);

        throw new \Exception('Maximum retry attempts reached for GraphQL query.');
    }
}
