<?php

declare(strict_types=1);

namespace Zenigata\Http\Test;

use Psr\Http\Message\ServerRequestInterface;
use Zenigata\Http\Application;
use Zenigata\Http\Middleware\MiddlewareDispatcherInterface;
use Zenigata\Http\Routing\RouteMatch;

/**
 * Testable implementation of {@see Zenigata\Http\Application}.
 * 
 * Overrides protected I/O methods to prevent real registration of PHP global
 * handlers and to expose counters for assertion.
 */
final class TestableApplication extends Application
{
    private ?MiddlewareDispatcherInterface $preparedDispatcher = null;

    private ?ServerRequestInterface $enrichedRequest = null;
    
    private int $shutdownRegistrationCount = 0;

    /**
     * Overridden for controlled testing behavior.
     */
    protected function prepareDispatcher(RouteMatch $route): MiddlewareDispatcherInterface
    {
        return $this->preparedDispatcher = parent::prepareDispatcher($route);
    }

    /**
     * Overridden for controlled testing behavior.
     */
    protected function enrichRequest(ServerRequestInterface $request, RouteMatch $route): ServerRequestInterface
    {
        return $this->enrichedRequest = parent::enrichRequest($request, $route);
    }

    /**
     * Overridden for controlled testing behavior.
     */
    protected function registerShutdownHandler(ServerRequestInterface $request): void
    {
        $this->shutdownRegistrationCount++;
    }

    public function getPreparedDispatcher(): ?MiddlewareDispatcherInterface
    {
        return $this->preparedDispatcher;
    }

    public function getEnrichedRequest(): ?ServerRequestInterface
    {
        return $this->enrichedRequest;
    }

    public function getShutdownRegistrationCount(): int
    {
        return $this->shutdownRegistrationCount;
    }
}