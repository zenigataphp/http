<?php

declare(strict_types=1);

namespace Zenigata\Http\Test;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zenigata\Http\Middleware\MiddlewareDispatcherInterface;
use Zenigata\Utility\Awareness\ContainerAwareInterface;
use Zenigata\Utility\Awareness\ContainerAwareTrait;

/**
 * Fake implementation of {@see Zenigata\Http\Middleware\MiddlewareDispatcherInterface}.
 */
final class FakeMiddlewareDispatcher implements ContainerAwareInterface, MiddlewareDispatcherInterface
{
    use ContainerAwareTrait;

    /**
     * @var list<MiddlewareInterface|string>
     */
    private array $middleware = [];

    public function dispatch(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $handler->handle($request);
    }

    public function addMiddleware(MiddlewareInterface|string $middleware): void
    {
        $this->middleware[] = $middleware;
    }

    public function getMiddleware(): array
    {
        return $this->middleware;
    }
}