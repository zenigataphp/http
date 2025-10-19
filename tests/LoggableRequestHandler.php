<?php

declare(strict_types=1);

namespace Zenigata\Http\Test;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zenigata\Testing\Http\FakeResponse;

/**
 * Fake implementation of {@see RequestHandlerInterface} (PSR-15).
 *
 * On invocation, it adds its name to a shared stack for asserting call order or count,
 * and returns a new response instance without performing any work.
 */
final class LoggableRequestHandler implements RequestHandlerInterface
{
    /**
     * Creates a new loggable request handler instance.
     *
     * @param array  $stack Reference to an array that will collect the names of executed handlers.
     * @param string $name  Label to append to the stack when this handler is executed.
     */
    public function __construct(
        private array &$stack,
        private string $name,
    ) {}

    /**
     * {@inheritDoc}
     * 
     * Appends the handler name in the provided stack.
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->stack[] = $this->name;

        return new FakeResponse();
    }
}