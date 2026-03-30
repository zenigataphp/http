<?php

declare(strict_types=1);

namespace Zenigata\Http\Routing;

use Psr\Http\Server\MiddlewareInterface;

/**
 * Defines a contract for an HTTP route.
 * 
 * Represents a single route with its method, path, handler,
 * and optional middleware stack.
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
     * Returns the route handler.
     *
     * @return mixed The request handler for this route.
     */
    public function getHandler(): mixed;

    /**
     * Returns the associated middleware stack.
     *
     * @return list<MiddlewareInterface|string> The middleware stack for this route.
     */
    public function getMiddleware(): array;

    /**
     * Returns a new instance with the group's prefix and middleware applied.
     *
     * @param GroupInterface $group The group containing the prefix and middleware to apply.
     * 
     * @return static The updated route instance.
     */
    public function withGroup(GroupInterface $group): static;
}