<?php

declare(strict_types=1);

namespace Zenigata\Http\Test;

use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zenigata\Http\Handling\ResponseStrategyInterface;

/**
 * Fake implementation of {@see Zenigata\Http\Handling\ResponseStrategyInterface}.
 */
class FakeResponseStrategy implements ResponseStrategyInterface
{
    /**
     * Indicates if the strategy is invoked.
     */
    private bool $invoked = false;

    /**
     * Creates a new fake response strategy instance.
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

    public function supports(ServerRequestInterface $request, mixed $data): bool
    {
        return $this->supports;
    }

    public function respond(ServerRequestInterface $request, mixed $data): ResponseInterface
    {
        $this->invoked = true;

        return new Response(204);
    }

    public function isInvoked(): bool
    {
        return $this->invoked;
    }
}