<?php

declare(strict_types=1);

namespace Zenigata\Http\Routing;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Defines a contract for a router.
 * 
 * Matches incoming requests to routes and manages
 * the collection of registered routes.
 */
interface RouterInterface
{   
    /**
     * Matches the incoming request to a route.
     *
     * @param ServerRequestInterface $request The incoming request.
     * 
     * @return RouteMatch The matched route.
     * @throws HttpError If the request cannot be matched to a route.
     */
    public function match(ServerRequestInterface $request): RouteMatch;

    /**
     * Adds a route, or a group of routes.
     * 
     * @param RouteInterface|GroupInterface|string $route Route, group, or resolvable string identifier.
     */
    public function addRoute(RouteInterface|GroupInterface|string $route): void;

    /**
     * Returns the registered routes.
     *
     * @return list<RouteInterface> List of registered routes.
     */
    public function getRoutes(): array;
}