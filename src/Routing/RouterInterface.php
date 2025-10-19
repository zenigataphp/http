<?php

declare(strict_types=1);

namespace Zenigata\Http\Routing;

use Psr\Http\Server\RequestHandlerInterface;

/**
 * Defines a contract for a PSR-15 compatible HTTP router.
 *
 * A router is responsible for registering routes or groups of routes,
 * dispatching incoming requests to the correct route handler,
 * and acting as a PSR-15 middleware in the request pipeline.
 */
interface RouterInterface extends RequestHandlerInterface
{
    /**
     * Registers a route, or a group of routes.
     *
     * Routes can be passed directly as a {@see RouteInterface} or {@see GroupInterface} instances,
     * or as a string identifier resolvable via a PSR-11 container.
     * 
     * @param RouteInterface|GroupInterface|string $route Route, group, or container-resolvable identifier.
     *
     * @return void
     */
    public function register(RouteInterface|GroupInterface|string $route): void;

    /**
     * Returns all registered routes.
     *
     * @return RouteInterface[] List of registered routes.
     */
    public function getRoutes(): array;
}