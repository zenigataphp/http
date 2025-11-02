<?php

declare(strict_types=1);

namespace Zenigata\Http\Routing;

use Psr\Http\Server\RequestHandlerInterface;

/**
 * Defines a contract for a PSR-15 compatible HTTP router.
 *
 * Provides methods to register, retrieve, and handle route definitions,
 * resolving them into executable request handlers at runtime.
 */
interface RouterInterface extends RequestHandlerInterface
{
    /**
     * Registers a route, or a group of routes.
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