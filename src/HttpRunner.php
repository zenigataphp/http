<?php

declare(strict_types=1);

namespace Zenigata\Http;

use Throwable;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Zenigata\Http\Bootstrap\Initializer;
use Zenigata\Http\Bootstrap\InitializerInterface;
use Zenigata\Http\Emitter\CombinedEmitter;
use Zenigata\Http\Emitter\EmitterInterface;
use Zenigata\Http\Error\ErrorHandler;
use Zenigata\Http\Error\ErrorHandlerInterface;

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
     * {@inheritDoc}
     * 
     * Initializes the request if needed, handles it, and emits the response; 
     * errors are delegated to the configured error handler.
     */
    public function run(?ServerRequestInterface $request = null): void
    {
        $this->prepare();

        $request ??= $this->initializer->createServerRequest();

        try {
            $response = $this->handler->handle($request);

            $this->emitter->emit($response);
        } catch (Throwable $error) {
            $this->errorHandler->handle($error, $request);
        }
    }

    /**
     * Ensures that all optional dependencies are properly initialized.
     */
    private function prepare(): void
    {
        $this->initializer  ??= new Initializer();
        $this->emitter      ??= new CombinedEmitter();
        $this->errorHandler ??= new ErrorHandler([], $this->debug, $this->logger);
    }
}