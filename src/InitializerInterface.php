<?php

declare(strict_types=1);

namespace Zenigata\Http;

use Throwable;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Defines a contract for creating PSR-7 server requests and error responses.
 *
 * Implementations are responsible for creating the initial PSR-7 server request
 * and providing a fallback error response if initialization fails.
 */
interface InitializerInterface
{
    /**
     * Creates a server request instance from global PHP variables.
     *
     * @return ServerRequestInterface A PSR-7 server request derived from globals.
     */
    public function createServerRequest(): ServerRequestInterface;

    /**
     * Creates an error response when initialization fails.
     *
     * This method is only intended for bootstrapping errors, not for
     * application-level exception handling.
     *
     * @param Throwable $error The initialization error or exception.
     * 
     * @return ResponseInterface A PSR-7 response representing the bootstrap failure.
     */
    public function createErrorResponse(Throwable $error): ResponseInterface;
}