<?php

declare(strict_types=1);

namespace Zenigata\Http\Routing;

use InvalidArgumentException;
use LogicException;
use FastRoute\Dispatcher as FastRoute;
use FastRoute\RouteCollector;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Zenigata\Http\Error\HttpError;
use Zenigata\Utility\Helper\ReflectionHelper;
use Zenigata\Http\Handler\HandlerResolver;
use Zenigata\Http\Handler\HandlerResolverInterface;
use Zenigata\Http\Middleware\Dispatcher;

use function array_map;
use function dirname;
use function is_string;
use function sprintf;
use function FastRoute\cachedDispatcher;

class Router implements RouterInterface
{
    /**
     * @var int
     */
    private const FOUND = FastRoute::FOUND;

    /**
     * @var int
     */
    private const NOT_FOUND = FastRoute::NOT_FOUND;

    /**
     * @var int
     */
    private const NOT_ALLOWED = FastRoute::METHOD_NOT_ALLOWED;

    /**
     * FastRoute instance used for matching requests to routes.
     *
     * @var FastRoute|null
     */
    private ?FastRoute $router = null;

    /**
     * Indicates if the routes are flattened to an array of {@see RouteInterface}.
     *
     * @var bool
     */
    private bool $resolved = false;

    /**
     * Creates a new router instance. 
     * 
     * @param RouteInterface[]|GroupInterface[]|string[] $routes        Initial routes (optional).
     * @param ContainerInterface|null                    $container     Optional PSR-11 container for resolving services.
     * @param HandlerResolverInterface|null              $resolver      PSR-15 handler resolver.
     * @param string                                     $attributeName Request attribute name for {@see RouteMatch}.
     * @param bool                                       $enableCache   Enable FastRoute caching.
     * @param string|null                                $cacheFile     FastRoute cache file path.
     */
    public function __construct(
        private array $routes = [],
        private ?ContainerInterface $container = null,
        private ?HandlerResolverInterface $resolver = null,
        private string $attributeName = 'route',
        private bool $enableCache = false,
        private ?string $cacheFile = null,
    ) {}

    /**
     * @inheritDoc
     * 
     * Delegates processing to the matched route's handler
     * and its middleware stack.
     * 
     * @throws HttpError If the request cannot be matched to a route.
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $method = $request->getMethod();
        $path   = $request->getUri()->getPath();

        $result = $this->router()->dispatch($method, $path);

        $status = $result[0];
        $data   = $result[1] ?? [];
        $params = $result[2] ?? [];

        $route = match ($status) {
            self::FOUND       => $this->createMatch($method, $path, $data['handler'], $data['middleware'], $params),
            self::NOT_FOUND   => throw new HttpError($request, 404),
            self::NOT_ALLOWED => throw new HttpError($request, 405, 'Allowed methods: ' . implode(', ', $data) . '.'),
            default           => throw new HttpError($request, 500, 'Unexpected routing error.')
        };

        $request = $this->enrichRequest($request, $route);
        $dispatcher = new Dispatcher($route->middleware, $route->handler, $this->container);

        return $dispatcher->handle($request);
    }

    /**
     * @inheritDoc
     */
    public function register(RouteInterface|GroupInterface|string $route): void
    {
        $this->routes[] = $route;
    }

    /**
     * @inheritDoc
     */
    public function getRoutes(): array
    {
        if ($this->resolved === false) {
            $this->resolveRoutes();
        }

        return $this->routes;
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
        return $this->enableCache;
    }

    /**
     * Lazily builds and returns a FastRoute instance using {@see cachedDispatcher},
     * which compiles all routes into an optimized routing table.
     */
    private function router(): FastRoute
    {
        return $this->router ??= cachedDispatcher(
            fn(RouteCollector $collector) => array_map(
                fn(RouteInterface $route) => $collector->addRoute(
                    $route->getMethod(),
                    $route->getPath(),
                    [
                        'handler'    => $route->getHandler(),
                        'middleware' => $route->getMiddleware()
                    ]
                ),
                $this->getRoutes()
            ),
            [
                'cacheFile'     => $this->cacheFile ?? dirname(__DIR__, 4) . '/.router_cache.php',
                'cacheDisabled' => $this->enableCache === false,
            ]
        );
    }

    /**
     * Flattens all registered routes and groups into a single array
     * of {@see RouteInterface} instances.
     */
    private function resolveRoutes(): void
    {
        $resolved = [];

        foreach ($this->routes as $route) {
            if (is_string($route)) {
                $route = $this->resolveDefinition($route);
            }

            if ($route instanceof GroupInterface) {
                foreach ($route->getRoutes() as $nested) {
                    $resolved[] = $nested;
                }

                continue;
            }

            $resolved[] = $route;
        }

        $this->routes = $resolved;
        $this->resolved = true;
    }

    /**
     * Resolves a string definition into a route or group instance.
     * 
     * @return RouteInterface|GroupInterface The resolved route or group instance.
     * @throws LogicException If no container has been set.
     * @throws InvalidArgumentException If the route is missing or has the wrong type.
     */
    private function resolveDefinition(string $route): RouteInterface|GroupInterface
    {
        $resolved = null;

        if ($this->container !== null && $this->container->has($route)) {
            $resolved = $this->container->get($route);
        }

        if ($resolved === null) {
            $resolved = ReflectionHelper::instantiate($route);
        }

        if (!$resolved instanceof RouteInterface && !$resolved instanceof GroupInterface) {
            throw new InvalidArgumentException(sprintf(
                "Invalid type for '%s'. Expected [%s, %s], got '%s'.",
                $route,
                RouteInterface::class,
                GroupInterface::class,
                $route::class
            ));
        }

        return $resolved;
    }

    /**
     * Creates route metadata with resolved handler
     * and attached middleware and paramaters.
     */
    private function createMatch(
        string $method,
        string $path,
        mixed $handler,
        array $middleware = [],
        array $parameters = [],
    ): RouteMatch
    {
        $this->resolver ??= new HandlerResolver(container: $this->container);

        return new RouteMatch(
            method:     $method,
            path:       $path,
            handler:    $this->resolver->resolve($handler, $parameters),
            middleware: $middleware,
            parameters: $parameters
        ); 
    }

    /**
     * Attaches the matched route and its parameters as request attributes,
     * making them available to subsequent middleware and handlers.
     */
    private function enrichRequest(ServerRequestInterface $request, RouteMatch $route): ServerRequestInterface
    {
        $request = $request->withAttribute($this->attributeName, $route);

        foreach ($route->parameters as $key => $value) {
            $request = $request->withAttribute($key, $value);
        }

        return $request;
    }
}