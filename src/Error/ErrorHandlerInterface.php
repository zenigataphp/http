<?php

declare(strict_types=1);

namespace Zenigata\Http\Error;

use Throwable;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Defines a contract for handling errors within an HTTP context.
 *
 * Implementations are responsible for converting exceptions into PSR-7 responses,
 * using one or more formatter instances to serialize error details.
 */
interface ErrorHandlerInterface
{
    /**
     * Registers a formatter to be used for error serialization.
     *
     * @param FormatterInterface $formatter The formatter instance to register.
     *
     * @return void
     */
    public function addFormatter(FormatterInterface $formatter): void;

    /**
     * Converts an exception into a PSR-7 response.
     *
     * @param ServerRequestInterface $request The incoming request that triggered the error.
     * @param Throwable              $error   The caught exception or error.
     * @param bool                   $debug   When enabled, responses will contain error details.
     *
     * @return ResponseInterface The generated error response.
     */
    public function handle(ServerRequestInterface $request, Throwable $error, bool $debug = false): ResponseInterface;
}