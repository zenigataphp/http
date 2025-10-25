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
    public function fromGlobals(): ServerRequestInterface;

    /**
     * Creates a server request instance from provided arrays.
     *
     * @param array<string,string>          $server  Server parameters (e.g., $_SERVER values).
     * @param array<string,string|string[]> $get     Query parameters (e.g., $_GET values).
     * @param array<string,string|string[]> $post    POST parameters (e.g., $_POST values).
     * @param array<string,string>          $cookies Cookie parameters (e.g., $_COOKIE values).
     * @param array<string,array>           $files   Uploaded files (e.g., $_FILES values).
     * 
     * @return ServerRequestInterface The initialized server request.
     */
    public function fromArrays(
        array $server  = [],
        array $get     = [],
        array $post    = [],
        array $cookies = [],
        array $files   = [],
    ): ServerRequestInterface;
}