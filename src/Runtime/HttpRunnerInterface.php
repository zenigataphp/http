<?php

declare(strict_types=1);

namespace Zenigata\Http\Runtime;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Defines a contract for an HTTP runner.
 * 
 * Initializes the request if necessary, delegates it to a handler,
 * and emits the returned response.
 */
interface HttpRunnerInterface
{
    /**
     * Runs the full HTTP lifecycle.
     * 
     * @param RequestHandlerInterface     $handler The request handler that handles the incoming requests.
     * @param ServerRequestInterface|null $request The incoming request, or automatically created if not provided.
     */
    public function run(RequestHandlerInterface $handler, ?ServerRequestInterface $request = null): void;

    /**
     * Emits the given response.
     *
     * @param ResponseInterface $response The response to emit.
     */
    public function emit(ResponseInterface $response): void;
}