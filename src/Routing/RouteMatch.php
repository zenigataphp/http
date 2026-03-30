<?php

declare(strict_types=1);

namespace Zenigata\Http\Routing;

/**
 * Holds metadata about a route match.
 */
final class RouteMatch
{
    /**
     * Creates a new route match instance.
     *
     * @param string                           $method     HTTP method (e.g., "GET", "POST")
     * @param string                           $path       The route path pattern (e.g., /posts/{slug}).
     * @param mixed                            $handler    The request handler.
     * @param list<MiddlewareInterface|string> $middleware The middleware stack.
     * @param array<string,string>             $parameters Route parameters (e.g., ["slug" => "hello"]).
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly mixed $handler,
        public readonly array $middleware = [],
        public readonly array $parameters = []
    ) {}
}
