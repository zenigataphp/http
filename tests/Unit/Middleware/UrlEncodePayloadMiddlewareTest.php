<?php

declare(strict_types=1);

namespace Zenigata\Http\Test\Unit\Middleware;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zenigata\Http\Middleware\UrlEncodePayloadMiddleware;
use Zenigata\Testing\Http\FakeRequestHandler;
use Zenigata\Testing\Http\FakeServerRequest;
use Zenigata\Testing\Http\FakeStream;

/**
 * Unit test for {@see UrlEncodePayloadMiddleware}.
 *
 * Covered cases:
 *
 * - Parse valid URL-encoded form data into an array.
 * - Ignore unsupported content types.
 * - Handle malformed input by producing partial results.
 * - Parse empty bodies as empty arrays.
 * - Ensure that the middleware only applies to the configured HTTP methods.
 * - Ensure that custom content types are correctly recognized.
 * - Ensure that the override option affects parsed body replacement behavior.
 */
#[CoversClass(UrlEncodePayloadMiddleware::class)]
final class UrlEncodePayloadMiddlewareTest extends TestCase
{
    public function testParsesValidUrlEncodedBody(): void
    {
        $middleware = new UrlEncodePayloadMiddleware();

        $request = new FakeServerRequest(
            method:  'POST',
            headers: ['Content-Type' => 'application/x-www-form-urlencoded'],
            body:    new FakeStream('foo=bar&baz=1')
        );

        $handler = $this->createRequestHandler();
        $middleware->process($request, $handler);

        $this->assertSame(['foo' => 'bar', 'baz' => '1'], $handler->capturedRequest->getParsedBody());
    }

    public function testSkipsUnsupportedContentType(): void
    {
        $middleware = new UrlEncodePayloadMiddleware();

        $request = new FakeServerRequest(
            method:  'POST',
            headers: ['Content-Type' => 'application/json'],
            body:    new FakeStream('foo=bar')
        );

        $handler = $this->createRequestHandler();
        $middleware->process($request, $handler);

        $this->assertNull($handler->capturedRequest->getParsedBody());
    }

    public function testHandlesMalformedBodyGracefully(): void
    {
        $middleware = new UrlEncodePayloadMiddleware();

        $request = new FakeServerRequest(
            method:  'POST',
            headers: ['Content-Type' => 'application/x-www-form-urlencoded'],
            body:    new FakeStream('foo%invalid=bar')
        );

        $handler = $this->createRequestHandler();
        $middleware->process($request, $handler);

        $parsed = $handler->capturedRequest->getParsedBody();
        $this->assertIsArray($parsed);
        $this->assertArrayHasKey('foo%invalid', $parsed);
    }

    public function testParsesEmptyBodyAsEmptyArray(): void
    {
        $middleware = new UrlEncodePayloadMiddleware();

        $request = new FakeServerRequest(
            method:  'POST',
            headers: ['Content-Type' => 'application/x-www-form-urlencoded'],
            body:    new FakeStream()
        );

        $handler = $this->createRequestHandler();
        $middleware->process($request, $handler);

        $this->assertSame([], $handler->capturedRequest->getParsedBody());
    }

    public function testHonorsConfiguredMethods(): void
    {
        $middleware = new UrlEncodePayloadMiddleware(methods: ['PUT']);

        $request = new FakeServerRequest(
            method:  'POST',
            headers: ['Content-Type' => 'application/x-www-form-urlencoded'],
            body:    new FakeStream('foo=bar')
        );

        $handler = $this->createRequestHandler();
        $middleware->process($request, $handler);

        $this->assertNull($handler->capturedRequest->getParsedBody());
    }

    public function testHonorsConfiguredContentType(): void
    {
        $middleware = new UrlEncodePayloadMiddleware(contentType: ['application/custom-urlencoded']);

        $request = new FakeServerRequest(
            method:  'POST',
            headers: ['Content-Type' => 'application/custom-urlencoded'],
            body:    new FakeStream('foo=bar')
        );

        $handler = $this->createRequestHandler();
        $middleware->process($request, $handler);

        $this->assertSame(['foo' => 'bar'], $handler->capturedRequest->getParsedBody());
    }

    public function testOverrideEnabled(): void
    {
        $middleware = new UrlEncodePayloadMiddleware(override: true);

        $request = new FakeServerRequest(
            method:     'POST',
            headers:    ['Content-Type' => 'application/x-www-form-urlencoded'],
            body:       new FakeStream('foo=bar'),
            parsedBody: ['old' => 'value']
        );

        $handler = $this->createRequestHandler();
        $middleware->process($request, $handler);

        $this->assertSame(['foo' => 'bar'], $handler->capturedRequest->getParsedBody());
    }

    public function testOverrideDisabled(): void
    {
        $middleware = new UrlEncodePayloadMiddleware(override: false);

        $request = new FakeServerRequest(
            method:     'POST',
            headers:    ['Content-Type' => 'application/x-www-form-urlencoded'],
            body:       new FakeStream('foo=bar'),
            parsedBody: ['old' => 'value']
        );

        $handler = $this->createRequestHandler();
        $middleware->process($request, $handler);

        $this->assertSame(['old' => 'value'], $handler->capturedRequest->getParsedBody());
    }

    /**
     * Creates a controllable request handler that captures the incoming request.
     *
     * @return RequestHandlerInterface A testable request handler instance.
     */
    private function createRequestHandler(): RequestHandlerInterface
    {
        return new class extends FakeRequestHandler {
            public ?ServerRequestInterface $capturedRequest = null;

            protected function onHandle(ServerRequestInterface $request): void
            {
                $this->capturedRequest = $request;
            }
        };
    }
}
