<?php

declare(strict_types=1);

namespace Zenigata\Http\Routing;

use Psr\Http\Server\MiddlewareInterface;

use function array_merge;
use function rtrim;

/**
 * Implementation of {@see GroupInterface}.
 *
 * Represents a collection of routes sharing a common path prefix and middleware stack.
 */
class Group implements GroupInterface
{
    /**
     * Path prefix applied to all routes.
     *
     * @var string
     */
    private string $prefix;

    /**
     * Routes belonging to this group.
     * 
     * @var RouteInterface[]
     */
    private array $routes;

    /**
     * Middleware stack belonging to this group.
     *
     * @var MiddlewareInterface[]|string[]
     */
    private array $middleware;

    /**
     * Creates a new route group instance.
     *
     * @param string                         $prefix     Path prefix applied to all routes.
     * @param RouteInterface[]               $routes     Initial routes to register.
     * @param MiddlewareInterface[]|string[] $middleware Group-level middleware stack.
     */
    public function __construct(string $prefix, array $routes = [], array $middleware = [])
    {
        $this->prefix = rtrim($prefix, '/');
        $this->middleware = $middleware;

        foreach ($routes as $route) {
            $this->addRoute($route);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    /**
     * Adds a route to the group.
     *
     * The route is built with the group’s prefix and middleware stack applied,
     * so inherits the shared configuration.
     */
    private function addRoute(RouteInterface $route): void
    {
        $this->routes[] = new Route(
            method:     $route->getMethod(),
            path:       $this->prefix . $route->getPath(),
            handler:    $route->getHandler(),
            middleware: array_merge($this->middleware, $route->getMiddleware())
        );
    }
}