<?php

declare(strict_types=1);

namespace Zenigata\Http\Test\Unit;

use RuntimeException;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Zenigata\Http\HttpRunner;
use Zenigata\Utility\Psr\FakeRequestHandler;
use Zenigata\Http\Test\TestableEmitter;

/**
 * Unit test for {@see Zenigata\Http\HttpRunner}.
 * 
 * Covered cases:
 *
 * - Run with an explicit request using a successful handler.
 * - Verify error handling behavior, with or without debug enabled.
 */
#[CoversClass(HttpRunner::class)]
final class HttpRunnerTest extends TestCase
{
    private TestableEmitter $emitter;

    private ServerRequest $request;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->emitter = new TestableEmitter();
        $this->request = new ServerRequest('GET', '/hello', ['Accept' => 'text/plain']);
    }

    public function testRunWithExplicitRequestEmitsResponse(): void
    {
        $response = new Response(200, ['Content-Type' => 'text/plain'], 'OK');

        $runner = new HttpRunner(
            handler: new FakeRequestHandler($response),
            emitter: $this->emitter
        );

        $runner->run($this->request);

        $this->assertNotEmpty($this->emitter->getSentHeaders());
        $this->expectOutputString('OK');
    }

    public function testRunHandlesExceptionThroughErrorHandler(): void
    {
        $runner = new HttpRunner(
            handler: new FakeRequestHandler(
                response: new Response(),
                callable: fn() => throw new RuntimeException('Custom error message')
            ),
            emitter: $this->emitter
        );

        $runner->run($this->request);

        $this->assertNotEmpty($this->emitter->getSentHeaders());
        $this->expectOutputRegex('/Message: Internal Server Error/');
    }

    public function testRunHandlesExceptionInDebugMode(): void
    {
        $runner = new HttpRunner(
            handler: new FakeRequestHandler(
                response: new Response(),
                callable: fn() => throw new RuntimeException('Custom error message')
            ),
            debug: true,
            emitter: $this->emitter
        );

        $runner->run($this->request);

        $this->assertNotEmpty($this->emitter->getSentHeaders());
        $this->expectOutputRegex('/Message: Custom error message/');
    }
}
