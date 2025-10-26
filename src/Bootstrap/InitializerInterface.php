<?php

declare(strict_types=1);

namespace Zenigata\Http\Bootstrap;

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
     * @return ServerRequestInterface The initialized server request.
     */
    public function createServerRequest(): ServerRequestInterface;
}