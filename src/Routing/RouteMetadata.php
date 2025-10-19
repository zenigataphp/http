<?php

declare(strict_types=1);

namespace Zenigata\Http\Routing;

use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Holds metadata about a resolved route.
 */
final class RouteMetadata
{
    /**
     * Creates a new route metadata instance.
     *
     * @param string                         $method     HTTP method (e.g., "GET", "POST")
     * @param string                         $path       The route path pattern (e.g., /posts/{slug}).
     * @param RequestHandlerInterface        $handler    The resolved request handler.
     * @param MiddlewareInterface[]|string[] $middleware The middleware stack.
     * @param array<string,string>           $parameters Route parameters (e.g., ["slug" => "hello"]).
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly RequestHandlerInterface $handler,
        public readonly array $middleware = [],
        public readonly array $parameters = []
    ) {}
}
