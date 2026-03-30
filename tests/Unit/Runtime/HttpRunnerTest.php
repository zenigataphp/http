<?php

declare(strict_types=1);

namespace Zenigata\Http\Test\Unit\Runtime;

use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Server\RequestHandlerInterface;
use Zenigata\Http\Runtime\HttpRunner;
use Zenigata\Http\Runtime\RequestInitializerInterface;
use Zenigata\Http\Test\FakeRequestHandler;
use Zenigata\Http\Test\FakeResponseEmitter;

/**
 * Unit test for {@see Zenigata\Http\Runtime\HttpRunner}.
 *
 * Covered cases:
 *
 * - Pass the provided request directly to the handler without invoking the initializer.
 * - Initialize the request from globals when no request is provided.
 * - Emit the response returned by the handler.
 * - emit() delegates directly to the emitter.
 */
#[CoversClass(HttpRunner::class)]
final class HttpRunnerTest extends TestCase
{
    private FakeRequestHandler $handler;

    private ServerRequest $request;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->handler = new FakeRequestHandler();
        $this->request = new ServerRequest('GET', '/');
    }

    public function testRunUsesProvidedRequest(): void
    {
        $initializer = $this->createMock(RequestInitializerInterface::class);
        $initializer->expects($this->never())->method('initialize');

        $runner = new HttpRunner(
            initializer: $initializer,
            emitter: new FakeResponseEmitter(),
        );

        $runner->run($this->handler, $this->request);
    }

    public function testRunInitializesMissingRequest(): void
    {
        $initializer = $this->createMock(RequestInitializerInterface::class);
        $initializer->expects($this->once())->method('initialize')->willReturn($this->request);

        $runner = new HttpRunner(
            initializer: $initializer,
            emitter: new FakeResponseEmitter(),
        );

        $runner->run($this->handler);
    }

    public function testRunEmitsHandlerResponse(): void
    {
        $response = new Response(201);
        $emitter  = new FakeResponseEmitter();

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($response);

        $runner = new HttpRunner(
            initializer: $this->createMock(RequestInitializerInterface::class),
            emitter: $emitter,
        );

        $runner->run($handler, $this->request);

        $this->assertSame($response, $emitter->getResponse());
    }

    public function testEmitUsesEmitter(): void
    {
        $response = new Response(204);
        $emitter  = new FakeResponseEmitter();

        $runner = new HttpRunner(
            initializer: $this->createMock(RequestInitializerInterface::class),
            emitter: $emitter,
        );

        $runner->emit($response);

        $this->assertSame($response, $emitter->getResponse());
    }
}