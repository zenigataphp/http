<?php

declare(strict_types=1);

namespace Zenigata\Http\Test\Unit\Handler;

use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zenigata\Http\Handler\HandlerInvoker;

/**
 * Unit test for {@see HandlerInvoker}.
 * Covered cases:
 *  - Invokes callable handlers with named parameters (default mode).
 *  - Invokes callable handlers with positional parameters.
 *  - Returns instances of {@see ResponseInterface}.
 */
#[CoversClass(HandlerInvoker::class)]
final class HandlerInvokerTest extends TestCase
{
    private ServerRequestInterface $request;

    private array $parameters;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->request = new ServerRequest('GET', '/');

        $this->parameters = [
            'foo' => 'abc',
            'bar' => 'xyz',
        ];
    }

    public function testInvokeWithNamedParameters(): void
    {
        $invoker = new HandlerInvoker();

        $response = $invoker->invoke(
            handler: function ($request, $bar, $foo) {
                $this->assertSame('abc', $foo);
                $this->assertSame('xyz', $bar);

                return new Response(204);
            },
            request:    $this->request,
            parameters: $this->parameters
        );

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertSame(204, $response->getStatusCode());
    }

    public function testInvokeWithPositionalParameters(): void
    {
        $invoker = new HandlerInvoker(positional: true);

        $response = $invoker->invoke(
            handler: function ($request, $abc, $bar) {
                $this->assertSame('abc', $abc);
                $this->assertSame('xyz', $bar);

                return new Response(204);
            },
            request:    $this->request,
            parameters: $this->parameters
        );

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertSame(204, $response->getStatusCode());
    }
}
