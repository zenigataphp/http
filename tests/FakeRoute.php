<?php

declare(strict_types=1);

namespace Zenigata\Http\Test;

use Zenigata\Http\Routing\GroupInterface;
use Zenigata\Http\Routing\RouteInterface;

/**
 * Fake implementation of {@see Zenigata\Http\Routing\RouteInterface}.
 */
class FakeRoute implements RouteInterface
{
    public function getMethod(): string
    {
        return 'GET';
    }

    public function getPath(): string
    {
        return '/hello';
    }

    public function getHandler(): mixed
    {
        return fn() => null;
    }

    public function getMiddleware(): array
    {
        return [];
    }

    public function withGroup(GroupInterface $group): static
    {
        return clone $this;
    }
}