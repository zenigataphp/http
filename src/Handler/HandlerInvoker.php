<?php

declare(strict_types=1);

namespace Zenigata\Http\Handler;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use function array_values;

/**
 * Default implementation of {@see HandlerInvokerInterface}.
 *
 * Invokes the callable request handler with the request
 * and optional route parameters.
 */
class HandlerInvoker implements HandlerInvokerInterface
{
    /**
     * Creates a new handler invoker instance.
     *
     * @param bool $positional Determines whether parameters are passed as named or positional arguments.
     */
    public function __construct(
        private bool $positional = false
    ) {}

    /**
     * {@inheritDoc}
     * 
     * Parameters are passed as named or positional arguments,
     * depending on the constructor flag.
     */
    public function invoke(callable $handler, ServerRequestInterface $request, array $parameters = []): ResponseInterface
    {
        if ($this->positional === true) {
            return $handler($request, ...array_values($parameters));    
        }

        return $handler($request, ...$parameters);
    }
}
