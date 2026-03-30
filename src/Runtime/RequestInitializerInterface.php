<?php

declare(strict_types=1);

namespace Zenigata\Http\Runtime;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Defines a contract for a PSR-7 server request initializer.
 *
 * Creates the initial server request needed to start the HTTP lifecycle.
 */
interface RequestInitializerInterface
{
    /**
     * Creates a server request from PHP globals or provided arrays.
     *
     * @param array<string,mixed> $server Server parameters, usually $_SERVER.
     * @param array<string,mixed> $get    Query parameters, usually $_GET.
     * @param array<string,mixed> $post   Parsed body parameters, usually $_POST.
     * @param array<string,mixed> $cookie Cookies, usually $_COOKIE.
     * @param array<string,mixed> $files  Uploaded files, usually $_FILES.
     * 
     * @return ServerRequestInterface The generated PSR-7 server request.
     */
    public function initialize(
        ?array $server = null,
        ?array $get    = null,
        ?array $post   = null,
        ?array $cookie = null,
        ?array $files  = null,
    ): ServerRequestInterface;
}