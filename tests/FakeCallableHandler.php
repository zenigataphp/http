<?php

declare(strict_types=1);

namespace Zenigata\Http\Test;

use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Fake callable handler implementation.
 */
class FakeCallableHandler
{
    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        return new Response(204);
    }
}