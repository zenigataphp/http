<?php

declare(strict_types=1);

namespace Zenigata\Http\Routing;

/**
 * Defines a contract for grouping multiple routes.
 *
 * A route group allows applying common configuration once
 * and propagating it to all included routes.
 */
interface GroupInterface
{
    /**
     * Returns all routes defined within the group.
     *
     * Each route is expected to have the group’s prefix 
     * and middleware stack already applied.
     *
     * @return RouteInterface[] List of grouped routes.
     */
    public function getRoutes(): array;
}