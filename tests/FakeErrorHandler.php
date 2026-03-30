<?php

declare(strict_types=1);

namespace Zenigata\Http\Test;

use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Throwable;
use Zenigata\Http\Error\ErrorHandlerInterface;
use Zenigata\Http\Error\ErrorStrategyInterface;
use Zenigata\Utility\Awareness\DebugAwareInterface;
use Zenigata\Utility\Awareness\DebugAwareTrait;

/**
 * Fake implementation of {@see Zenigata\Http\Error\ErrorHandlerInterface}.
 * 
 * A minimal error handler that also implements DebugAwareInterface,
 * so setDebug() propagation from Application can be verified.
 */
final class FakeErrorHandler implements ErrorHandlerInterface, DebugAwareInterface
{
    use DebugAwareTrait;

    private ?ErrorStrategyInterface $defaultStrategy = null;
 
    public function handle(ServerRequestInterface $request, Throwable $error): ResponseInterface
    {
        return new Response(500);
    }
 
    public function addStrategy(ErrorStrategyInterface|string $strategy): void
    {
        // no-op
    }
 
    public function getStrategies(): array
    {
        return [];
    }

    public function setDefaultStrategy(string $name): void
    {
        // no-op
    }

    public function getDefaultStrategy(): ErrorStrategyInterface
    {
        return $this->defaultStrategy ??= new FakeErrorStrategy();
    }

    public function setLogger(LoggerInterface $logger): void
    {
        // no-op
    }
}