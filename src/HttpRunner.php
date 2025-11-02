<?php

declare(strict_types=1);

namespace Zenigata\Http;

use Throwable;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Zenigata\Http\Error\ErrorHandler;
use Zenigata\Http\Error\ErrorHandlerInterface;
use Zenigata\Http\Request\Initializer;
use Zenigata\Http\Request\InitializerInterface;
use Zenigata\Http\Response\Emitter;
use Zenigata\Http\Response\EmitterInterface;

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
     * @param LoggerInterface|null       $logger       Optional PSR-3 logger used to record errors.
     * @param InitializerInterface|null  $initializer  Initializer used to create server requests from globals.
     * @param EmitterInterface|null      $emitter      Emitter used to send final responses to the client.
     * @param ErrorHandlerInterface|null $errorHandler Error handler used to catch and format exceptions.
     */
    public function __construct(
        private RequestHandlerInterface $handler,
        private bool $debug = false,
        private ?LoggerInterface $logger = null,
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
        $this->errorHandler ??= new ErrorHandler(logger: $this->logger);

        try {
            $request ??= $this->initializer->createServerRequest();
            $response = $this->handler->handle($request);
        } catch (Throwable $error) {
            $response = $this->errorHandler->handle($request, $error, $this->debug);
        }

        $this->emitter->emit($response);
    }
}