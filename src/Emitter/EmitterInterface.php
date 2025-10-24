<?php

declare(strict_types=1);

namespace Zenigata\Http\Emitter;

use Psr\Http\Message\ResponseInterface;

/**
 * Defines a contract for emitting an HTTP response.
 *
 * Implementations are responsible for sending the provided response to the output.
 */
interface EmitterInterface
{
    /**
     * Emits the given PSR-7 response to the client
     * and returns a boolean indicating success.
     *
     * @param ResponseInterface $response The PSR-7 response instance to emit.
     *
     * @return bool True if the response was successfully emitted, false otherwise.
     */
    public function emit(ResponseInterface $response): bool;
}