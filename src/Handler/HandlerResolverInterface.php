<?php

declare(strict_types=1);

namespace Zenigata\Http\Handler;

use Psr\Http\Server\RequestHandlerInterface;

/**
 * Defines a contract for resolving route handler definitions into PSR-15 request handlers.
 *
 * Implementations must transform such definitions into valid request handlers,
 * ensuring they can be executed in a middleware pipeline.
 */
interface HandlerResolverInterface
{
    /**
     * Resolves a handler definition into a {@see RequestHandlerInterface}.
     *
     * @param mixed $handler The handler definition provided by the router.
     *
     * @return RequestHandlerInterface The resolved request handler.
     */
    public function resolve(mixed $handler): RequestHandlerInterface;
}