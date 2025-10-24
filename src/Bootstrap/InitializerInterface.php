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
     * @return ServerRequestInterface A PSR-7 server request derived from globals.
     */
    public function fromGlobals(): ServerRequestInterface;

    /**
     * Creates a server request instance from array values.
     *
     * @param array $server
     * @param array $get
     * @param array $post
     * @param array $cookies
     * @param array $files
     * 
     * @return ServerRequestInterface
     */
    public function fromArrays(
        array $server  = [],
        array $get     = [],
        array $post    = [],
        array $cookies = [],
        array $files   = [],
    ): ServerRequestInterface;
}