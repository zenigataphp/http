<?php

declare(strict_types=1);

namespace Zenigata\Http\Test;

use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Fake controller implementation.
 */
class FakeController
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return new Response(204);
    }
}