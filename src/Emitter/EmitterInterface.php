<?php

declare(strict_types=1);

namespace Zenigata\Http\Emitter;

use Psr\Http\Message\ResponseInterface;

/**
 * Defines a contract for emitting an HTTP response.
 * 
 * Implementations are responsible for sending a PSR-7 response
 * to the client through the current PHP SAPI environment.
 */
interface EmitterInterface
{
    /**
     * Emits the given PSR-7 response to the client.
     *
     * @param ResponseInterface $response The PSR-7 response instance to emit.
     *
     * @return void
     */
    public function emit(ResponseInterface $response): void;
}