<?php

namespace Jansamnan\Graphify\Clients;

use Illuminate\Support\Facades\Log;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Exception\RequestException;
use Jansamnan\Graphify\Contracts\GraphRequester;

/**
 * GraphQL client.
 */
class Graph extends AbstractClient implements GraphRequester
{
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
     * {@inheritdoc}
     *
     * @throws \Exception When missing api password is missing for private apps.
     * @throws \Exception When missing access key is missing for public apps.
     */
    public function request(string $query, array $variables = [], bool $sync = true)
    {
        $this->maxRetries = config('graphify.max_retries', 5);
        $this->retryDelay = config('graphify.retry_delay', 1);
        /**
         * Run the request as sync or async.
         */
        $requestFn = function (array $request) use ($sync) {
            // Encode the request
            $json = json_encode($request);

            // Run the request
            $fn = $sync ? 'request' : 'requestAsync';

            return $this->getClient()->{$fn}(
                'POST',
                $this->getBaseUri()->withPath('/admin/api/graphql.json'),
                ['body' => $json]
            );
        };

        // Build the request
        $request = ['query' => $query];
        if (count($variables) > 0) {
            $request['variables'] = $variables;
        }

        if ($sync === false) {
            // Async request
            $promise = $requestFn($request);

            return $promise->then([$this, 'handleSuccess'], [$this, 'handleFailure']);
        }

        // Sync request (default)
        try {
            $response = $requestFn($request);
            return $this->handleSuccess($response);
        } catch (RequestException $e) {
            return $this->handleFailure($e);
        }
    }

        /**
     * Execute a GraphQL request synchronously or asynchronously with optional pagination.
     *
     * @param string $query The GraphQL query string.
     * @param array $variables The variables for the GraphQL query.
     * @param bool $sync Whether to execute synchronously. Defaults to true.
     * @param callable|null $callback An optional callback to process each page of data.
     * @return mixed The response data or void if a callback is provided.
     *
     * @throws \Exception When the request fails after retries.
     */
    public function requestPaginate(string $query, array $variables = [], callable $callback = null, bool $sync = true)
    {
        $this->maxRetries = config('graphify.max_retries', 5);
        $this->retryDelay = config('graphify.retry_delay', 1);
        /**
         * Internal function to execute a single request.
         */
        $requestFn = function (array $request) use ($sync) {
            // Encode the request
            $json = json_encode($request);

            // Determine the method to call based on $sync
            $fn = $sync ? 'request' : 'requestAsync';
            return $this->getClient()->{$fn}(
                'POST',
                $this->getBaseUri()->withPath('/admin/api/graphql.json'),
                ['body' => $json]
            );
        };

        /**
         * Internal function to handle retries.
         */
        $handleRetry = function () use (&$attempt, &$requestFn, $sync) {
            $maxRetries = $this->maxRetries;
            $retryDelay = $this->retryDelay;
            $attempt++;

            if ($attempt > $maxRetries) {
                throw new \Exception('Maximum retry attempts reached for GraphQL request.');
            }

            // Calculate exponential backoff delay
            $waitTime = $retryDelay * pow(2, $attempt - 1);
            Log::warning("Retrying request after {$waitTime} seconds (Attempt {$attempt}/{$maxRetries}).");
            sleep($waitTime);
        };

        // Initialize attempt counter
        $attempt = 0;

        // Initialize cursor and pagination flag
        $after = null;
        $hasNextPage = true;

        // Modify variables to include 'after' cursor if available
        $currentVariables = $variables;

        while ($hasNextPage) {
            // Merge 'after' cursor into variables if available
            if ($after) {
                $currentVariables['after'] = $after;
            }

            // Build the request
            $request = [
                'query' => $query,
                'variables' => $currentVariables,
            ];

            try {

                if ($sync === false) {
                    // Async request
                    $promise = $requestFn($request);

                    $response = $promise->then([$this, 'handleSuccess'], [$this, 'handleFailure']);
                }else{

                    $response = $requestFn($request);

                    $response = $this->handleSuccess($response);
                }

                // If a callback is provided, process the page and continue pagination
                if (is_callable($callback)) {
                    // Invoke the callback with the response body
                    call_user_func($callback, $response);
                    $body = $response['body'];
                    // Check for rate limiting and handle if necessary
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

                    // Determine if there are more pages
                    // Adjust 'products' to your specific query root
                    if (isset($body->data->products->pageInfo)) {
                        $pageInfo = $body->data->products->pageInfo;
                        $hasNextPage = $pageInfo->hasNextPage;
                        $after = $pageInfo->endCursor ?? null;
                    } else {
                        // If 'pageInfo' is not present, assume no more pages
                        $hasNextPage = false;
                    }
                } else {
                    // If no callback, return the response body immediately
                    return $body;
                }
            } catch (RequestException $e) {
                $attempt++;
                $statusCode = $e->getResponse() ? $e->getResponse()->getStatusCode() : null;

                if ($statusCode == 429 && $attempt <= $this->maxRetries) { // Rate limit exceeded
                    $retryAfter = $e->getResponse()->getHeader('Retry-After')[0] ?? null;
                    $waitTime = $retryAfter ? intval($retryAfter) : $this->retryDelay * pow(2, $attempt - 1);
                    Log::warning("Rate limit exceeded. Retrying after {$waitTime} seconds. (Attempt {$attempt}/{$this->maxRetries})");
                    sleep($waitTime);
                    continue; // Retry the request
                }

                // Log and rethrow exception if not rate limit related or max retries exceeded
                Log::error("GraphQL Request Error: " . $e->getMessage());
                throw $e;
            }
        }

        // If a callback was provided, no need to return anything
        if (is_callable($callback)) {
            return;
        }

        // If no callback and all pages have been processed, return null or an appropriate response
        return null;
    }

    /**
     * Handle response from request.
     *
     * @param ResponseInterface $resp
     *
     * @return array
     */
    public function handleSuccess(ResponseInterface $resp): array
    {
        // Convert data to response
        $body = $this->toResponse($resp->getBody());

        // Return Guzzle response and JSON-decoded body
        return [
            'errors' => $body->hasErrors() ? $body->getErrors() : false,
            'response' => $resp,
            'status' => $resp->getStatusCode(),
            'body' => $body,
            'timestamps' => $this->getTimeStore()->get($this->getSession()),
        ];
    }

    /**
     * Handle failure of response.
     *
     * @param RequestException $e
     *
     * @return array
     */
    public function handleFailure(RequestException $e): array
    {
        $resp = $e->getResponse();
        $body = null;
        $status = null;

        if ($resp) {
            // Get the body stream
            $rawBody = $resp->getBody();
            $status = $resp->getStatusCode();

            // Build the error object
            if ($rawBody !== null) {
                // Convert data to response
                $body = $this->toResponse($rawBody);
                $body = $body->hasErrors() ? $body->getErrors() : null;
            }
        }

        return [
            'errors' => true,
            'response' => $resp,
            'status' => $status,
            'body' => $body,
            'exception' => $e,
            'timestamps' => $this->getTimeStore()->get($this->getSession()),
        ];
    }
}
