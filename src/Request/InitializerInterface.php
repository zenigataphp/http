<?php

declare(strict_types=1);

namespace Zenigata\Http\Request;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Defines a contract for initializing PSR-7 server requests.
 *
 * Implementations are responsible for building the initial request
 * required to bootstrap the HTTP handling process.
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
    public function initialServerRequest(
        ?array $server = null,
        ?array $get    = null,
        ?array $post   = null,
        ?array $cookie = null,
        ?array $files  = null,
    ): ServerRequestInterface;
}