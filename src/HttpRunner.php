<?php

declare(strict_types=1);

namespace Zenigata\Http;

use Throwable;
use Laminas\HttpHandlerRunner\Emitter\EmitterInterface;
use Laminas\HttpHandlerRunner\RequestHandlerRunner;
use Psr\Http\Message\ServerRequestInterface;
use Zenigata\Http\Emitter\CombinedEmitter;
use Zenigata\Http\Middleware\DispatcherInterface;

/**
 * Implementation of {@see HttpRunnerInterface}.
 *
 * The runner composes a middleware dispatcher, a server request initializer, 
 * and a response emitter to handle the full PSR-15 HTTP lifecycle.
 */
class HttpRunner implements HttpRunnerInterface
{
    /**
     * Creates a new HTTP runner instance.
     *
     * @param DispatcherInterface       $dispatcher  Dispatcher responsible for middleware execution.
     * @param bool                      $debug       Enables verbose error responses.
     * @param InitializerInterface|null $initializer Initializer used to create server requests from globals.
     * @param EmitterInterface|null     $emitter     Emitter used to send final responses to the client.
     */
    public function __construct(
        private DispatcherInterface $dispatcher,
        private bool $debug = false,
        private ?InitializerInterface $initializer = null,
        private ?EmitterInterface $emitter = null,
    ) {}

    /**
     * {@inheritDoc}
     * 
     * Creates and runs a {@see RequestHandlerRunner} to handle the HTTP request.
     * Executes middleware, emits the response, and creates a request if none is given.
     */
    public function run(?ServerRequestInterface $request = null): void
    {
        $this->initializer ??= new Initializer(debug: $this->debug);
        $this->emitter ??= new CombinedEmitter();

        $runner = new RequestHandlerRunner(
            $this->dispatcher,
            $this->emitter,
            fn() => $request ?? $this->initializer->createServerRequest(),
            fn(Throwable $e) => $this->initializer->createErrorResponse($e)
        );

        $runner->run();
    }
}