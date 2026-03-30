<?php

declare(strict_types=1);

namespace Zenigata\Http;

use ErrorException;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Throwable;
use Zenigata\Http\Error\ErrorHandler;
use Zenigata\Http\Error\ErrorHandlerInterface;
use Zenigata\Http\Error\ErrorStrategyInterface;
use Zenigata\Http\Handling\ResponseStrategyInterface;
use Zenigata\Http\Handling\RouteHandler;
use Zenigata\Http\Handling\RouteHandlerInterface;
use Zenigata\Http\Middleware\MiddlewareDispatcher;
use Zenigata\Http\Middleware\MiddlewareDispatcherInterface;
use Zenigata\Http\Routing\GroupInterface;
use Zenigata\Http\Routing\RouteInfo;
use Zenigata\Http\Routing\RouteInterface;
use Zenigata\Http\Routing\RouteMatch;
use Zenigata\Http\Routing\Router;
use Zenigata\Http\Routing\RouterInterface;
use Zenigata\Http\Runtime\HttpRunner;
use Zenigata\Http\Runtime\HttpRunnerInterface;
use Zenigata\Utility\Awareness\ContainerAwareInterface;
use Zenigata\Utility\Awareness\ContainerAwareTrait;
use Zenigata\Utility\Awareness\DebugAwareInterface;
use Zenigata\Utility\Awareness\DebugAwareTrait;
use Zenigata\Utility\Helper\ConfigLoader;

use const E_ALL;
use const E_COMPILE_ERROR;
use const E_CORE_ERROR;
use const E_ERROR;
use const E_PARSE;

use function error_get_last;
use function error_reporting;
use function in_array;
use function register_shutdown_function;
use function set_error_handler;

/**
 * HTTP application orchestrator.
 *
 * Coordinates routing, middleware dispatching, route handling,
 * and error handling during the request lifecycle.
 */
class Application implements RequestHandlerInterface
{
    use ContainerAwareTrait;
    use DebugAwareTrait;

    /**
     * Request attribute name that identifies the current route.
     * 
     * @var string
     */
    public const ROUTE_ATTRIBUTE_NAME = '_route';

    /**
     * Indicates whether the shutdown function has already been registered.
     */
    protected bool $shutdownRegistered = false;

    /**
     * Creates a new application instance.
     *
     * @param iterable<MiddlewareInterface|string>           $middleware   The middleware stack.
     * @param iterable<RouteInterface|GroupInterface|string> $routes       Routes or group of routes.
     * @param bool                                           $debug        Enables detailed error responses.
     * @param int                                            $errorLevels  Controls which PHP errors are shown.
     * @param ContainerInterface|null                        $container    Optional container instance.
     * @param MiddlewareDispatcherInterface                  $dispatcher   The middleware dispatcher used to process the middleware pipeline. 
     * @param RouterInterface                                $router       The router used to match the incoming request to a route.
     * @param RouteHandlerInterface                          $routeHandler The route handler used to process the route.
     * @param ErrorHandlerInterface                          $errorHandler The error handler used to catch exceptions.
     * @param HttpRunnerInterface|null                       $runner       The HTTP runner, lazy-created automatically if not provided.
     */
    public function __construct(
        iterable $middleware = [],
        iterable $routes = [],
        bool $debug = false,
        int $errorLevels = E_ALL,
        ?ContainerInterface $container = null,
        protected MiddlewareDispatcherInterface $dispatcher = new MiddlewareDispatcher(),
        protected RouterInterface $router = new Router(),
        protected RouteHandlerInterface $routeHandler = new RouteHandler(),
        protected ErrorHandlerInterface $errorHandler = new ErrorHandler(),
        protected ?HttpRunnerInterface $runner = null
    ) {
        foreach ($middleware as $middleware) {
            $this->dispatcher->addMiddleware($middleware);
        }

        foreach ($routes as $route) {
            $this->router->addRoute($route);
        }

        if ($container !== null) {
            $this->setContainer($container);
        }

        $this->setDebug($debug);

        $this->registerPhpErrorHandler($errorLevels);
    }

    /**
     * Adds a middleware into the stack.
     *
     * @param MiddlewareInterface|string $middleware Middleware instance, or resolvable string identifier.
     * 
     * @return static The application instance.
     */
    public function addMiddleware(MiddlewareInterface|string $middleware): static
    {
        $this->dispatcher->addMiddleware($middleware);

        return $this;
    }

    /**
     * Adds a route, or a group of routes.
     * 
     * @param RouteInterface|GroupInterface|string $route Route, group, or resolvable string identifier.
     * 
     * @return static The application instance.
     */
    public function addRoute(RouteInterface|GroupInterface|string $route): static
    {
        $this->router->addRoute($route);

        return $this;
    }

    /**
     * Adds an error strategy.
     *
     * @param ErrorStrategyInterface|string $strategy Error strategy, or resolvable string identifier.
     * 
     * @return static The application instance.
     * @throws InvalidArgumentException If the strategy definition cannot be resolved.
     */
    public function addErrorStrategy(ErrorStrategyInterface|string $strategy): static
    {
        $this->errorHandler->addStrategy($strategy);

        return $this;
    }

    /**
     * Adds a response strategy.
     *
     * @param ResponseStrategyInterface|string $strategy Response strategy, or resolvable string identifier.
     * 
     * @return static The application instance.
     * @throws InvalidArgumentException If the strategy definition cannot be resolved.
     */
    public function addResponseStrategy(ResponseStrategyInterface|string $strategy): static
    {
        $this->routeHandler->addStrategy($strategy);

        return $this;
    }

    /**
     * Loads middleware definitions from a configuration file.
     *
     * @param string $path Path to the configuration file.
     * 
     * @return static The application instance.
     */
    public function loadMiddleware(string $path): static
    {
        $this->loadFromPath($path, $this->addMiddleware(...));

        return $this;
    }

    /**
     * Loads route definitions from a configuration file.
     *
     * @param string $path Path to the configuration file.
     * 
     * @return static The application instance.
     */
    public function loadRoutes(string $path): static
    {
        $this->loadFromPath($path, $this->addRoute(...));

        return $this;
    }

    /**
     * Loads error strategy definitions from a configuration file.
     *
     * @param string $path Path to the configuration file.
     * 
     * @return static The application instance.
     */
    public function loadErrorStrategies(string $path): static
    {
        $this->loadFromPath($path, $this->addErrorStrategy(...));

        return $this;
    }

    /**
     * Loads response strategy definitions from a configuration file.
     *
     * @param string $path Path to the configuration file.
     * 
     * @return static The application instance.
     */
    public function loadResponseStrategies(string $path): static
    {
        $this->loadFromPath($path, $this->addResponseStrategy(...));

        return $this;
    }

    /**
     * Returns the registered middleware.
     *
     * @return list<MiddlewareInterface|string> List of middleware instances, or resolvable string identifier.
     */
    public function getMiddleware(): array
    {
        return $this->dispatcher->getMiddleware();
    }

    /**
     * Returns the registered routes. 
     *
     * @return list<RouteInterface> List of registered routes.
     */
    public function getRoutes(): array
    {
        return $this->router->getRoutes();
    }

    /**
     * Returns the registered error strategies. 
     *
     * @return array<string,ErrorStrategyInterface> List of registered error strategies. 
     */
    public function getErrorStrategies(): array
    {
        return $this->errorHandler->getStrategies();
    }

    /**
     * Returns the registered response strategies. 
     *
     * @return array<string,ErrorStrategyInterface> List of registered response strategies. 
     */
    public function getResponseStrategies(): array
    {
        return $this->routeHandler->getStrategies();
    }

    /**
     * Sets the container instance.
     * 
     * Internal components inherit the container, if supported.
     * 
     * @param ContainerInterface $container The container instance.
     * 
     * @return static The application instance.
     */
    public function setContainer(ContainerInterface $container): static
    {
        $this->container = $container;

        foreach ($this->listComponents() as $component) {
            if ($component instanceof ContainerAwareInterface) {
                $component->setContainer($container);
            }
        }

        return $this;
    }

    /**
     * Enable or disable debug mode.
     * 
     * Internal components inherit the debug state, if supported.
     * 
     * @param bool $debug The debug state.
     * 
     * @return static The application instance.
     */
    public function setDebug(bool $debug): static
    {
        $this->debug = $debug;

        foreach ($this->listComponents() as $component) {
            if ($component instanceof DebugAwareInterface) {
                $component->setDebug($debug);
            }
        }

        return $this;
    }

    /**
     * Sets the logger instance to log errors.
     *
     * @param LoggerInterface $logger The logger instance.
     * 
     * @return static The application instance.
     */
    public function setErrorLogger(LoggerInterface $logger): static
    {
        $this->errorHandler->setLogger($logger);

        return $this;
    }

    /**
     * Sets the default error strategy.
     *
     * @param string $name The name of the error strategy.
     * 
     * @return static The application instance.
     * @throws InvalidArgumentException If the default strategy is not in the registered.
     */
    public function setDefaultErrorStrategy(string $name): static
    {
        $this->errorHandler->setDefaultStrategy($name);

        return $this;
    }

    /**
     * Sets the default response strategy.
     *
     * @param string $name The name of the response strategy.
     * 
     * @return static The application instance.
     * @throws InvalidArgumentException If the default strategy is not in the registered.
     */
    public function setDefaultResponseStrategy(string $name): static
    {
        $this->routeHandler->setDefaultStrategy($name);

        return $this;
    }

    /**
     * @inheritDoc
     * 
     * Matches the incoming request to a route, then processes the middleware stack
     * and the route handler. Any thrown error is delegated to the error handler.
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if ($this->shutdownRegistered === false) {
            $this->registerShutdownHandler($request);
            $this->shutdownRegistered = true;
        }

        try {
            $route = $this->router->match($request);

            $dispatcher = $this->prepareDispatcher($route);
            $request    = $this->enrichRequest($request, $route);
            $handler    = $this->createHandler($route);

            return $dispatcher->dispatch($request, $handler);
        } catch (Throwable $error) {
            return $this->errorHandler->handle($request, $error);
        }
    }

    /**
     * Runs the full HTTP lifecycle.
     *
     * @param ServerRequestInterface|null $request The incoming request, or automatically created if not provided.
     */
    public function run(?ServerRequestInterface $request = null): void
    {
        $this->runner ??= new HttpRunner();
        $this->runner->run($this, $request);
    }

    /**
     * Loads configuration files and invokes the callable on each of them.
     */
    protected function loadFromPath(string $path, callable $action): void
    {
        foreach (ConfigLoader::load([$path]) as $entry) {
            foreach ((array) $entry as $item) {
                $action($item);
            }
        }
    }

    /**
     * Returns the list of internal components.
     */
    protected function listComponents(): array
    {
        return [
            $this->dispatcher,
            $this->router,
            $this->routeHandler,
            $this->errorHandler,
        ];
    }

    /**
     * Clones the dispatcher and appends route-level middleware.
     */
    protected function prepareDispatcher(RouteMatch $route): MiddlewareDispatcherInterface
    {
        $dispatcher = clone $this->dispatcher;

        foreach ($route->middleware as $middleware) {
            $dispatcher->addMiddleware($middleware);
        }

        return $dispatcher;
    }

    /**
     * Attaches the matched route and its parameters as request attributes,
     * making them available to subsequent middleware and handlers.
     */
    protected function enrichRequest(ServerRequestInterface $request, RouteMatch $route): ServerRequestInterface
    {
        foreach ($route->parameters as $key => $value) {
            $request = $request->withAttribute($key, $value);
        }

        return $request->withAttribute(self::ROUTE_ATTRIBUTE_NAME, new RouteInfo(
            $route->method,
            $route->path,
            $route->parameters,
        ));
    }

    /**
     * Creates a request handler that wraps the route handler and the matched route.
     */
    protected function createHandler(RouteMatch $route): RequestHandlerInterface
    {
        return new class($this->routeHandler, $route) implements RequestHandlerInterface {
            public function __construct(
                private RouteHandlerInterface $routeHandler,
                private RouteMatch $route
            ) {}

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return $this->routeHandler->handle($request, $this->route);
            }
        };
    }

    /**
     * Registers a PHP error handler that converts runtime errors into catchable exceptions.
     * Errors silenced with the @ operator or outside the error levels are ignored.
     */
    protected function registerPhpErrorHandler(int $errorLevels): void
    {
        set_error_handler(
            function (int $errno, string $errstr, string $errfile, int $errline): bool {
                if (!(error_reporting() & $errno)) {
                    return false;
                }

                throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
            },
            $errorLevels
        );
    }

    /**
     * Registers a shutdown handler that converts fatal errors to error responses.
     */
    protected function registerShutdownHandler(ServerRequestInterface $request): void
    {
        register_shutdown_function(function () use ($request): void {
            $error = error_get_last();

            if ($error === null || !$this->isFatalError($error['type'])) {
                return;
            }

            $response = $this->errorHandler->handle($request, new ErrorException(
                $error['message'],
                0,
                $error['type'],
                $error['file'],
                $error['line']
            ));

            $this->runner ??= new HttpRunner();
            $this->runner->emit($response);
        });
    }

    /**
     * Determines if the error type must be handled on shutdown.
     */
    protected function isFatalError(int $type): bool
    {
        return in_array($type, [E_ERROR, E_COMPILE_ERROR, E_CORE_ERROR, E_PARSE], true);
    }
}