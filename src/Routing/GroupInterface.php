<?php

declare(strict_types=1);

namespace Zenigata\Http\Routing;

/**
 * Defines a contract for a group of routes.
 *
 * Represents a group of routes with shared prefix
 * and optional middleware stack.
 */
interface GroupInterface
{
    /**
     * Returns the group prefix.
     *
     * @return string The group's prefix.
     */
    public function getPrefix(): string;

    /**
     * Returns the middleware associated with the group.
     *
     * @return list<MiddlewareInterface|string> The group's middleware.
     */
    public function getMiddleware(): array;

    /**
     * Returns the routes defined within the group.
     *
     * @return list<RouteInterface|GroupInterface|string> The group's routes.
     */
    public function getRoutes(): array;
}