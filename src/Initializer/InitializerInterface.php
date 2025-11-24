<?php

declare(strict_types=1);

namespace Zenigata\Http\Initializer;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Defines a contract for initializing the HTTP lifecycle.
 *
 * Implementations are responsible for building the initial PSR-7 server request
 * or other structures required to bootstrap the HTTP handling workflow.
 */
interface InitializerInterface
{
    /**
     * Creates a ServerRequest from PHP globals or provided arrays.
     *
     * @param array<string,mixed> $server Server parameters, usually $_SERVER.
     * @param array<string,mixed> $get    Query parameters, usually $_GET.
     * @param array<string,mixed> $post   Parsed body parameters, usually $_POST.
     * @param array<string,mixed> $cookie Cookies, usually $_COOKIE.
     * @param array<string,mixed> $files  Uploaded files, usually $_FILES.
     * 
     * @return ServerRequestInterface The fully initialized PSR-7 server request.
     */
    public function createServerRequest(
        ?array $server = null,
        ?array $get    = null,
        ?array $post   = null,
        ?array $cookie = null,
        ?array $files  = null,
    ): ServerRequestInterface;
}