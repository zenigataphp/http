<?php

declare(strict_types=1);

namespace Zenigata\Http\Test;

use Closure;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Fake implementation of {@see Psr\Http\Server\MiddlewareInterface}.
 * 
 * Invokes the optional callback during processing.
 */
class FakeMiddleware implements MiddlewareInterface
{
    /**
     * Creates a new fake middleware instance.
     *
     * @param callable|null $callable Optional callback invoked during processing.
     */
    public function __construct(
        private ?Closure $callback = null
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($this->callback !== null) {
            ($this->callback)($request, $handler);
        }

        return $handler->handle($request);
    }
}
