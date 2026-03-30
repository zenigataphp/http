<?php

declare(strict_types=1);

namespace Zenigata\Http\Test;

use Psr\Http\Message\ResponseInterface;
use Zenigata\Http\Runtime\ResponseEmitterInterface;

/**
 * Fake implementation of {@see Zenigata\Http\Runtime\ResponseEmitterInterface}.
 * 
 * Captures the outgoing response for later assertion.
 */
final class FakeResponseEmitter implements ResponseEmitterInterface
{
    private ?ResponseInterface $response = null;

    public function emit(ResponseInterface $response): void
    {
        $this->response = $response;
    }

    public function getResponse(): ?ResponseInterface
    {
        return $this->response;
    }
}