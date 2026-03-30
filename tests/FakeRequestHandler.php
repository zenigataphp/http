<?php

declare(strict_types=1);

namespace Zenigata\Http\Test;

use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Fake implementation of {@see Psr\Http\Server\RequestHandlerInterface}.
 * 
 * Captures the incoming request for later assertion.
 */
class FakeRequestHandler implements RequestHandlerInterface
{
    private ?ServerRequestInterface $request = null;

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->request = $request;

        return new Response(204);
    }

    public function getRequest(): ?ServerRequestInterface
    {
        return $this->request;
    }
}
