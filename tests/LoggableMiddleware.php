<?php

declare(strict_types=1);

namespace Zenigata\Http\Test;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Fake implementation of {@see MiddlewareInterface} (PSR-15).
 *
 * On invocation, it adds its name to a shared stack for asserting call order or count,
 * then delegates to the next request handler without performing any work.
 */
final class LoggableMiddleware implements MiddlewareInterface
{
    /**
     * Creates a new loggable middleware instance.
     *
     * @param array  $stack Reference to an array that will collect the names of executed middleware.
     * @param string $name  Label to append to the stack when this middleware is executed.
     */
    public function __construct(
        private array &$stack,
        private string $name,
    ) {}

    /**
     * {@inheritDoc}
     * 
     * Appends the middleware name in the provided stack.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->stack[] = $this->name;
        
        return $handler->handle($request);
    }
}