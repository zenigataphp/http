<?php

declare(strict_types=1);

namespace Zenigata\Http;

use Throwable;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zenigata\Http\Emitter\Emitter;
use Zenigata\Http\Emitter\EmitterInterface;
use Zenigata\Http\Error\ErrorHandler;
use Zenigata\Http\Error\ErrorHandlerInterface;
use Zenigata\Http\Initializer\Initializer;
use Zenigata\Http\Initializer\InitializerInterface;

/**
 * Implementation of {@see Zenigata\Http\HttpRunnerInterface}.
 *
 * The runner composes a middleware dispatcher, a server request initializer, 
 * and a response emitter to handle the full PSR-15 HTTP lifecycle.
 */
class HttpRunner implements HttpRunnerInterface
{
    /**
     * Creates a new HTTP runner instance.
     *
     * @param RequestHandlerInterface    $handler      The request handler that handles the incoming requests.
     * @param bool                       $debug        Enables detailed error responses.
     * @param InitializerInterface|null  $initializer  Initializer used to create server requests from globals.
     * @param EmitterInterface|null      $emitter      Emitter used to send final responses to the client.
     * @param ErrorHandlerInterface|null $errorHandler Error handler used to catch and format exceptions.
     */
    public function __construct(
        private RequestHandlerInterface $handler,
        private bool $debug = false,
        private ?InitializerInterface $initializer = null,
        private ?EmitterInterface $emitter = null,
        private ?ErrorHandlerInterface $errorHandler = null,
    ) {}

    /**
     * @inheritDoc
     * 
     * Initializes the request if needed, handles it, and emits the response; 
     * errors are delegated to the configured error handler.
     */
    public function run(?ServerRequestInterface $request = null): void
    {
        $this->initializer  ??= new Initializer();
        $this->emitter      ??= new Emitter();
        $this->errorHandler ??= new ErrorHandler();

        $request ??= $this->initializer->serverRequest();
        
        try {
            $response = $this->handler->handle($request);
        } catch (Throwable $error) {
            $response = $this->errorHandler->handle($request, $error, $this->debug);
        }

        $this->emitter->emit($response);
    }
}