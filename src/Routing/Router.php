<?php

declare(strict_types=1);

namespace Zenigata\Http\Routing;

use FastRoute\Dispatcher as FastRoute;
use FastRoute\RouteCollector;
use InvalidArgumentException;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;
use Zenigata\Http\Error\HttpError;
use Zenigata\Utility\Helper\ReflectionResolver;
use Zenigata\Utility\Awareness\ContainerAwareInterface;
use Zenigata\Utility\Awareness\ContainerAwareTrait;

use function dirname;
use function FastRoute\cachedDispatcher;
use function implode;
use function is_string;
use function sprintf;

/**
 * Implementation of {@see Zenigata\Http\Routing\RouterInterface}.
 *  
 * Internally relies on {@see FastRoute\Dispatcher}
 * to match requests to routes.
 */
class Router implements RouterInterface, ContainerAwareInterface
{
    use ContainerAwareTrait;

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
     */
    private ?FastRoute $router = null;

    /**
     * Creates a new router instance. 
     * 
     * @param list<RouteInterface|GroupInterface|string> $routes      Routes or group of routes.
     * @param bool                                       $enableCache Enable FastRoute caching.
     * @param string|null                                $cacheFile   FastRoute cache file path.
     */
    public function __construct(
        private array $routes = [],
        private bool $enableCache = false,
        private ?string $cacheFile = null,
    ) {}

    /**
     * @inheritDoc
     */
    public function match(ServerRequestInterface $request): RouteMatch
    {
        $method = $request->getMethod();
        $path   = $request->getUri()->getPath();

        $result = $this->router()->dispatch($method, $path);

        $status = $result[0];
        $data   = $result[1] ?? [];
        $params = $result[2] ?? [];

        return match ($status) {
            self::FOUND       => new RouteMatch($method, $path, $data['handler'], $data['middleware'], $params),
            self::NOT_FOUND   => throw new HttpError($request, 404),
            self::NOT_ALLOWED => throw new HttpError($request, 405, sprintf('Allowed methods: %s.', implode(', ', $data))),
            default           => throw new HttpError($request, 500, 'Unexpected routing error.')
        };
    }

    /**
     * @inheritDoc
     */
    public function addRoute(RouteInterface|GroupInterface|string $route): void
    {
        $this->routes[] = $route;
        $this->router   = null;
    }

    /**
     * @inheritDoc
     * 
     * Resolves definitions and route groups.
     */
    public function getRoutes(): array
    {
        return $this->resolveRoutes($this->routes);
    }

    /**
     * Indicates if caching is enabled.
     *
     * @return bool True if caching is enabled, false otherwise.
     */
    public function isCacheEnabled(): bool
    {
        return $this->enableCache;
    }

    /**
     * Lazily builds and returns a FastRoute instance using {@see FastRoute\cachedDispatcher()},
     * which compiles all routes into an optimized routing table, if cache is enabled.
     */
    private function router(): FastRoute
    {
        return $this->router ??= cachedDispatcher(
            function (RouteCollector $collector) {
                foreach ($this->getRoutes() as $route) {
                    $collector->addRoute(
                        $route->getMethod(),
                        $route->getPath(),
                        [
                            'handler'    => $route->getHandler(),
                            'middleware' => $route->getMiddleware()
                        ]
                    );
                }
            },
            [
                'cacheFile'     => $this->cacheFile ?? dirname(__DIR__, 4) . '/router.cache',
                'cacheDisabled' => $this->enableCache === false,
            ]
        );
    }

    /**
     * Recursively resolves a list of route definitions into concrete route instances.
     *
     * @param list<RouteInterface|GroupInterface|string> $routes List of route definitions to resolve.
     *
     * @return list<RouteInterface> The resolved routes.
     * @throws InvalidArgumentException If a definition cannot be resolved or has the wrong type.
     */
    private function resolveRoutes(array $routes): array
    {
        $resolved = [];

        foreach ($routes as $route) {
            if (is_string($route)) {
                $route = $this->resolveRoute($route);
            }

            if ($route instanceof GroupInterface) {
                foreach ($this->resolveGroup($route) as $nested) {
                    $resolved[] = $nested;
                }

                continue;
            }

            $resolved[] = $route;
        }

        return $resolved;
    }

    /**
     * Resolves a string definition into a route or group instance.
     * 
     * @return RouteInterface|GroupInterface The resolved route or group instance.
     * @throws LogicException If no container has been set.
     * @throws InvalidArgumentException If the route cannot be resolved or has the wrong type.
     */
    private function resolveRoute(string $route): RouteInterface|GroupInterface
    {
        $instance = $this->container?->has($route)
            ? $this->container->get($route)
            : ReflectionResolver::resolve($route);

        if (!$instance instanceof RouteInterface && !$instance instanceof GroupInterface) {
            throw new InvalidArgumentException(sprintf(
                "Invalid type for '%s'. Expected [%s, %s], got '%s'.",
                $route,
                RouteInterface::class,
                GroupInterface::class,
                $instance::class
            ));
        }

        return $instance;
    }

    /**
     * Resolves all routes within a group and applies the group's prefix and middleware to each.
     *
     * @return list<RouteInterface> The resolved group routes.
     */
    private function resolveGroup(GroupInterface $group): array
    {
        $resolved = [];

        foreach ($this->resolveRoutes($group->getRoutes()) as $route) {
            $resolved[] = $route->withGroup($group);
        }

        return $resolved;
    }
}