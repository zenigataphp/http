<?php

declare(strict_types=1);

namespace Zenigata\Http\Handling;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zenigata\Http\Routing\RouteMatch;

/**
 * Defines a contract for a route handler.
 * 
 * Converts an handler result into a PSR-7 response,
 * using the appropriate response strategy.
 */
interface RouteHandlerInterface
{
    /**
     * Handles the result returned from the request handler.
     *
     * @param ServerRequestInterface $request The incoming request.
     * @param RouteMatch             $route   The matched route.
     * 
     * @return ResponseInterface The generated response.
     */
    public function handle(ServerRequestInterface $request, RouteMatch $route): ResponseInterface;

    /**
     * Adds a response strategy.
     *
     * @param ResponseStrategyInterface|string $strategy Response strategy, or resolvable string identifier.
     */
    public function addStrategy(ResponseStrategyInterface|string $strategy): void;

    /**
     * Returns the registered response strategies. 
     *
     * @return array<string,ResponseStrategyInterface> List of registered response strategies.
     */
    public function getStrategies(): array;

    /**
     * Returns the default response strategy.
     *
     * @return ResponseStrategyInterface The default response strategy.
     */
    public function getDefaultStrategy(): ResponseStrategyInterface;

    /**
     * Sets the default response strategy.
     *
     * @param string $name The name of the response strategy.
     */
    public function setDefaultStrategy(string $name): void;
}