<?php

namespace Jansamnan\Graphify\Clients;

use Exception;
use Jansamnan\Graphify\Contracts\ClientAware;
use Jansamnan\Graphify\Contracts\LimitAccesser;
use Jansamnan\Graphify\Contracts\Respondable;
use Jansamnan\Graphify\Contracts\SessionAware;
use Jansamnan\Graphify\Contracts\StateStorage;
use Jansamnan\Graphify\Contracts\TimeAccesser;
use Jansamnan\Graphify\Contracts\TimeDeferrer;
use Jansamnan\Graphify\Options;
use Jansamnan\Graphify\Session;
use Jansamnan\Graphify\Traits\ResponseTransform;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Uri;

/**
 * Base client class.
 */
abstract class AbstractClient implements TimeAccesser, SessionAware, LimitAccesser, ClientAware, Respondable
{
    use ResponseTransform;

    /**
     * The time store implementation.
     *
     * @var StateStorage
     */
    protected $tstore;

    /**
     * The limits store implementation.
     *
     * @var StateStorage
     */
    protected $lstore;

    /**
     * The time deferrer implementation.
     *
     * @var TimeDeferrer
     */
    protected $tdeferrer;

    /**
     * The API session.
     *
     * @var Session|null
     */
    protected $session;

    /**
     * The Guzzle client.
     *
     * @var ClientInterface
     */
    protected $client;

    /**
     * The options.
     *
     * @var Options
     */
    protected $options;

    /**
     * Setup.
     *
     * @param StateStorage $tstore    The time store implementation.
     * @param StateStorage $lstore    The limits store implementation.
     * @param TimeDeferrer $tdeferrer The time deferrer implementation.
     *
     * @return self
     */
    public function __construct(StateStorage $tstore, StateStorage $lstore, TimeDeferrer $tdeferrer)
    {
        $this->tstore = $tstore;
        $this->lstore = $lstore;
        $this->tdeferrer = $tdeferrer;
    }

    /**
     * {@inheritdoc}
     */
    public function getBaseUri(): Uri
    {
        if ($this->session === null || $this->session->getShop() === null) {
            // Shop is required
            throw new Exception('Shopify domain missing for API calls');
        }

        return new Uri("https://{$this->session->getShop()}");
    }

    /**
     * {@inheritdoc}
     */
    public function getTimeDeferrer(): TimeDeferrer
    {
        return $this->tdeferrer;
    }

    /**
     * {@inheritdoc}
     */
    public function getTimeStore(): StateStorage
    {
        return $this->tstore;
    }

    /**
     * {@inheritdoc}
     */
    public function getLimitStore(): StateStorage
    {
        return $this->lstore;
    }

    /**
     * {@inheritdoc}
     */
    public function setSession(Session $session): void
    {
        $this->session = $session;
    }

    /**
     * {@inheritdoc}
     */
    public function getSession(): ?Session
    {
        return $this->session;
    }

    /**
     * {@inheritdoc}
     */
    public function setClient(ClientInterface $client): void
    {
        $this->client = $client;
    }

    /**
     * {@inheritdoc}
     */
    public function getClient(): ClientInterface
    {
        return $this->client;
    }

    /**
     * {@inheritdoc}
     */
    public function setOptions(Options $options): void
    {
        $this->options = $options;
    }

    /**
     * {@inheritdoc}
     */
    public function getOptions(): Options
    {
        return $this->options;
    }
}
