<?php

declare(strict_types=1);

namespace Zenigata\Http\Runtime;

use Psr\Http\Message\ResponseInterface;

/**
 * Defines a contract for a PSR-7 response emitter.
 * 
 * Implementations are responsible for sending a PSR-7 response
 * to the client through the current PHP SAPI environment.
 */
interface ResponseEmitterInterface
{
    /**
     * Emits the given response.
     *
     * @param ResponseInterface $response The response to emit.
     */
    public function emit(ResponseInterface $response): void;
}