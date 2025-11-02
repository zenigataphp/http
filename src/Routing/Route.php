<?php

namespace Zenigata\Http\Routing;

use Psr\Http\Server\MiddlewareInterface;

use function rtrim;
use function strtoupper;

/**
 * Implementation of {@see RouteInterface}.
 * 
 * Represents a single HTTP route with its method, path, handler,
 * and optional middleware stack.
 */
class Route implements RouteInterface
{
    /**
     * The HTTP method.
     *
     * @var string
     */
    private string $method;
    
    /**
     * The route path.
     *
     * @var string
     */
    private string $path;

    /**
     * Creates a new route instance.
     *
     * @param string                         $method     The HTTP method (e.g., "GET", "POST").
     * @param string                         $path       The route path.
     * @param mixed                          $handler    The route handler.
     * @param MiddlewareInterface[]|string[] $middleware Optional middleware stack.
     */
    public function __construct(
        string $method,
        string $path,
        private mixed $handler,
        private array $middleware = []
    ) {
        $this->method = strtoupper($method);
        $this->path = rtrim($path, '/');
    }

    /**
     * Creates a GET route instance. 
     *
     * @param string                         $path       The route path.
     * @param mixed                          $handler    The route handler.
     * @param MiddlewareInterface[]|string[] $middleware Optional middleware stack.
     * 
     * @return static A new route for GET requests.
     */
    public static function get(
        string $path,
        mixed $handler,
        array $middleware = []
    ): static
    {
        return new self('GET', $path, $handler, $middleware);
    }

    /**
     * Creates a POST route instance. 
     *
     * @param string                         $path       The route path.
     * @param mixed                          $handler    The route handler.
     * @param MiddlewareInterface[]|string[] $middleware Optional middleware stack.
     * 
     * @return static A new route for POST requests.
     */
    public static function post(
        string $path,
        mixed $handler,
        array $middleware = []
    ): static
    {
        return new self('POST', $path, $handler, $middleware);
    }

    /**
     * Creates a PUT route instance. 
     *
     * @param string                         $path       The route path.
     * @param mixed                          $handler    The route handler.
     * @param MiddlewareInterface[]|string[] $middleware Optional middleware stack.
     * 
     * @return static A new route for PUT requests.
     */
    public static function put(
        string $path,
        mixed $handler,
        array $middleware = []
    ): static
    {
        return new self('PUT', $path, $handler, $middleware);
    }

    /**
     * Creates a PATCH route instance. 
     *
     * @param string                         $path       The route path.
     * @param mixed                          $handler    The route handler.
     * @param MiddlewareInterface[]|string[] $middleware Optional middleware stack.
     * 
     * @return static A new route for PATCH requests.
     */
    public static function patch(
        string $path,
        mixed $handler,
        array $middleware = []
    ): static
    {
        return new self('PATCH', $path, $handler, $middleware);
    }

    /**
     * Creates a DELETE route instance. 
     *
     * @param string                         $path       The route path.
     * @param mixed                          $handler    The route handler.
     * @param MiddlewareInterface[]|string[] $middleware Optional middleware stack.
     * 
     * @return static A new route for DELETE requests.
     */
    public static function delete(
        string $path,
        mixed $handler,
        array $middleware = []
    ): static
    {
        return new self('DELETE', $path, $handler, $middleware);
    }

    /**
     * Creates a HEAD route instance. 
     *
     * @param string                         $path       The route path.
     * @param mixed                          $handler    The route handler.
     * @param MiddlewareInterface[]|string[] $middleware Optional middleware stack.
     * 
     * @return static A new route for HEAD requests.
     */
    public static function head(
        string $path,
        mixed $handler,
        array $middleware = []
    ): static
    {
        return new self('HEAD', $path, $handler, $middleware);
    }

    /**
     * Creates an OPTIONS route instance. 
     *
     * @param string                         $path       The route path.
     * @param mixed                          $handler    The route handler.
     * @param MiddlewareInterface[]|string[] $middleware Optional middleware stack.
     * 
     * @return static A new route for OPTIONS requests.
     */
    public static function options(
        string $path,
        mixed $handler,
        array $middleware = []
    ): static
    {
        return new self('OPTIONS', $path, $handler, $middleware);
    }

    /**
     * Creates routes for all common HTTP methods.
     * 
     * Useful when a single path and handler need to handle all HTTP methods.
     *
     * @param string                         $path       The route path.
     * @param mixed                          $handler    The route handler.
     * @param MiddlewareInterface[]|string[] $middleware Optional middleware stack.
     * 
     * @return GroupInterface A group containing routes for each HTTP verb.
     */
    public static function any(
        string $path,
        mixed $handler,
        array $middleware = []
    ): GroupInterface
    {
        $methods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD'];

        return self::map($methods, $path, $handler, $middleware);
    }

    /**
     * Creates routes for the specified HTTP methods.
     * 
     * Useful when a single path and handler need to handle multiple HTTP methods.
     *
     * @param string[]                       $methods    List of HTTP methods.
     * @param string                         $path       The route path.
     * @param mixed                          $handler    The route handler.
     * @param MiddlewareInterface[]|string[] $middleware Optional middleware stack.
     * 
     * @return GroupInterface A group containing routes for the specified methods.
     */
    public static function map(
        array $methods,
        string $path,
        mixed $handler,
        array $middleware = []
    ): GroupInterface
    {
        $routes = [];
        
        foreach ($methods as $method) {
            $routes[] = new self($method, $path, $handler, $middleware);
        }

        return new Group('', $routes, $middleware);
    }

    /**
     * Defines a group of routes with a shared path prefix and middleware stack.
     * 
     * The provided callback must return an array of route instances.
     *
     * @param string                         $prefix     The shared path prefix.
     * @param RouteInterface[]               $routes     Array of routes belonging to the group.
     * @param MiddlewareInterface[]|string[] $middleware Optional group middleware stack.
     *
     * @return GroupInterface A group containing the prefixed routes.
     */
    public static function group(string $prefix, array $routes, array $middleware = []): GroupInterface
    {
        return new Group($prefix, $routes, $middleware);
    }

    /**
     * @inheritDoc
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * @inheritDoc
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * @inheritDoc
     */
    public function getHandler(): mixed
    {
        return $this->handler;
    }

    /**
     * @inheritDoc
     */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }
}