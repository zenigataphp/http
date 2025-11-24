<?php

declare(strict_types=1);

namespace Zenigata\Http\Middleware;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zenigata\Http\Handler\HandlerResolverInterface;
use Zenigata\Http\Router\GroupInterface;
use Zenigata\Http\Router\RouteInterface;
use Zenigata\Http\Router\Router;

/**
 * Middleware wrapping the {@see Zenigata\Http\Router\Router} handler.
 *
 * Matches incoming requests against the registered routing table
 * and delegates execution to the resolved route handler.
 */
class RouterMiddleware implements MiddlewareInterface
{
    /**
     * Internal router handler.
     *
     * @var Router
     */
    private Router $router;

    /**
     * Creates a new router middleware instance. 
     * 
     * @param iterable<RouteInterface|GroupInterface|string> $routes        Initial routes (optional).
     * @param ContainerInterface|null                        $container     Optional PSR-11 container for resolving services.
     * @param HandlerResolverInterface|null                  $resolver      PSR-15 handler resolver.
     * @param string                                         $attributeName Request attribute name to access route metadata.
     * @param bool                                           $enableCache   Enable FastRoute caching.
     * @param string|null                                    $cacheFile     FastRoute cache file path.
     */
    public function __construct(
        iterable $routes = [],
        ?ContainerInterface $container = null,
        ?HandlerResolverInterface $resolver = null,
        string $attributeName = 'route',
        bool $enableCache = false,
        ?string $cacheFile = null,
    ) {
        $this->router = new Router(
            $routes,
            $container,
            $resolver,
            $attributeName,
            $enableCache,
            $cacheFile,
        );
    }

    /**
     * @inheritDoc
     *
     * Delegates processing to internal handler.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $this->router->handle($request);
    }

    /**
     * Registers a route, or a group of routes.
     * 
     * @param RouteInterface|GroupInterface|string $route Route, group, or container-resolvable identifier.
     */
    public function register(RouteInterface|GroupInterface|string $route): void
    {
        $this->router->register($route);
    }

    /**
     * Returns all registered routes.
     *
     * @return RouteInterface[] List of registered routes.
     */
    public function getRoutes(): array
    {
        return $this->router->getRoutes();
    }

    /**
     * Indicates if caching is enabled.
     *
     * When enabled, FastRoute will attempt to read/write the routing table
     * from a cache file instead of recompiling it on every request.
     *
     * @return bool True if caching is enabled, false otherwise.
     */
    public function isCacheEnabled(): bool
    {
        return $this->router->isCacheEnabled();
    }
}