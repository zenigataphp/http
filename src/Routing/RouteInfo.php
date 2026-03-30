<?php

declare(strict_types=1);

namespace Zenigata\Http\Routing;

/**
 * Holds metadata about a route.
 */
final class RouteInfo
{
    /**
     * Creates a new route info instance.
     *
     * @param string               $method     HTTP method (e.g., "GET", "POST")
     * @param string               $path       The route path pattern (e.g., /posts/{slug}).
     * @param array<string,string> $parameters Route parameters (e.g., ["slug" => "hello"]).
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $parameters = []
    ) {}
}
