<?php

declare(strict_types=1);

namespace Zenigata\Http\Test;

use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;
use Zenigata\Http\Error\ErrorStrategyInterface;

/**
 * Fake implementation of {@see Zenigata\Http\Error\ErrorStrategyInterface}.
 */
class FakeErrorStrategy implements ErrorStrategyInterface
{
    /**
     * Indicates if the strategy is invoked.
     */
    private bool $invoked = false;

    /**
     * Creates a new fake error strategy instance.
     *
     * @param string $name     The strategy name.
     * @param bool   $supports Forces the return value of supports.
     */
    public function __construct(
        private string $name = 'fake',
        private bool $supports = true,
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function supports(ServerRequestInterface $request, Throwable $error): bool
    {
        return $this->supports;
    }

    public function respond(ServerRequestInterface $request, Throwable $error): ResponseInterface
    {
        $this->invoked = true;

        return new Response(500);
    }

    public function isInvoked(): bool
    {
        return $this->invoked;
    }
}