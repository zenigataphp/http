<?php

declare(strict_types=1);

namespace Zenigata\Http\Routing;

use Psr\Http\Server\MiddlewareInterface;

/**
 * Defines a contract for an HTTP route.
 *
 * A route binds together an HTTP method, a path, a request handler,
 * and an optional stack of middleware.
 */
interface RouteInterface
{   
    /**
     * Returns the HTTP method.
     *
     * @return string The HTTP method (e.g., GET, POST).
     */
    public function getMethod(): string;

    /**
     * Returns the route path.
     *
     * @return string The normalized route path.
     */
    public function getPath(): string;

    /**
     * Returns the unresolved handler.
     *
     * @return mixed The request handler for this route.
     */
    public function getHandler(): mixed;

    /**
     * Returns the associated middleware.
     *
     * @return MiddlewareInterface[]|string[] The middleware stack for this route.
     */
    public function getMiddleware(): array;
}