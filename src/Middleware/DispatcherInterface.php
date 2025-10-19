<?php

declare(strict_types=1);

namespace Zenigata\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Defines a contract for a PSR-15 middleware dispatcher.
 *
 * Implementations must be invokable as request handlers, executing middleware in sequence
 * and delegating to a final handler when the stack is exhausted.
 */
interface DispatcherInterface extends RequestHandlerInterface
{   
    /**
     * Registers a middleware into the stack.
     *
     * Middleware can be passed directly as a {@see MiddlewareInterface}
     * instance, or as a string identifier resolvable via a PSR-11 container.
     *
     * @param MiddlewareInterface|string $middleware Middleware instance, or container-resolvable identifier.
     *
     * @return void
     */
    public function register(MiddlewareInterface|string $middleware): void;
}