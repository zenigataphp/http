<?php

declare(strict_types=1);

namespace Zenigata\Http\Handler;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Defines how a resolved handler should be invoked.
 *
 * Implementations must call the given handler with the provided request
 * and optional route parameters, returning a PSR-7 response.
 */
interface HandlerInvokerInterface
{
    /**
     * Invokes the given handler with the request and route parameters.
     *
     * @param callable               $handler    The resolved callable handler.
     * @param ServerRequestInterface $request    The current request.
     * @param array<string,mixed>    $parameters Optional route parameters.
     *
     * @return ResponseInterface The response returned by the handler.
     */
    public function invoke(callable $handler, ServerRequestInterface $request, array $parameters = []): ResponseInterface;
}
