<?php

declare(strict_types=1);

namespace Zenigata\Http\Runtime;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Implementation of {@see Zenigata\Http\Runtime\HttpRunnerInterface}.
 */
class HttpRunner implements HttpRunnerInterface
{
    /**
     * Creates a new HTTP runner instance.
     *
     * @param RequestInitializerInterface $initializer Initializer used to create server request from PHP globals.
     * @param ResponseEmitterInterface    $emitter     Emitter used to send the final response to the client.
     */
    public function __construct(
        private RequestInitializerInterface $initializer = new RequestInitializer(),
        private ResponseEmitterInterface $emitter = new ResponseEmitter(),
    ) {}

    /**
     * @inheritDoc
     */
    public function run(RequestHandlerInterface $handler, ?ServerRequestInterface $request = null): void
    {
        $response = $handler->handle($request ?? $this->initializer->initialize());

        $this->emit($response);
    }

    /**
     * @inheritDoc
     */
    public function emit(ResponseInterface $response): void
    {
        $this->emitter->emit($response);
    }
}