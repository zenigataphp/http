<?php

declare(strict_types=1);

namespace Zenigata\Http\Routing;

use InvalidArgumentException;
use Psr\Http\Server\MiddlewareInterface;

use function get_debug_type;
use function is_array;
use function sprintf;
use function trim;

/**
 * Implementation of {@see Zenigata\Http\Routing\GroupInterface}.
 */
class Group implements GroupInterface
{
    /**
     * Path prefix applied to all routes.
     */
    private string $prefix;

    /**
     * Routes belonging to this group.
     * 
     * @var callable
     */
    private $routes;

    /**
     * Creates a new route group instance.
     *
     * @param string                           $prefix     Path prefix applied to all routes.
     * @param callable                         $routes     Routes belonging to this group.
     * @param list<MiddlewareInterface|string> $middleware Middleware stack belonging to this group.
     */
    public function __construct(
        string $prefix,
        callable $routes,
        private array $middleware = []
    ) {
        $this->prefix = '/' . trim($prefix, '/');
        $this->routes = $routes;
    }

    /**
     * @inheritDoc
     */
    public function getPrefix(): string
    {
        return $this->prefix;
    }

    /**
     * @inheritDoc
     */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    /**
     * @inheritDoc
     */
    public function getRoutes(): array
    {
        $routes = ($this->routes)();

        if (!is_array($routes)) {
            throw new InvalidArgumentException(sprintf(
                "Invalid group callable return type. Expected an array of [%s, %s], got '%s'.",
                RouteInterface::class,
                GroupInterface::class,
                get_debug_type($routes)
            ));
        }

        return $routes;
    }
}