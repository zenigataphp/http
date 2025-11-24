<?php

declare(strict_types=1);

namespace Zenigata\Http\Test\Unit\Middleware;

use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zenigata\Http\Middleware\UrlEncodePayloadMiddleware;

/**
 * Unit test for {@see Zenigata\Http\Middleware\UrlEncodePayloadMiddleware}.
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
    private RequestHandlerInterface $handler;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->handler = new class implements RequestHandlerInterface {
            public ?ServerRequestInterface $capturedRequest = null;

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->capturedRequest = $request;

                return new Response();
            }
        };
    }

    public function testParsesValidUrlEncodedBody(): void
    {
        $middleware = new UrlEncodePayloadMiddleware();

        $request = new ServerRequest(
            method:  'POST',
            uri:     '/',
            headers: ['Content-Type' => 'application/x-www-form-urlencoded'],
            body:    'foo=bar&baz=1'
        );

        $middleware->process($request, $this->handler);

        $this->assertSame(['foo' => 'bar', 'baz' => '1'], $this->handler->capturedRequest->getParsedBody());
    }

    public function testSkipsUnsupportedContentType(): void
    {
        $middleware = new UrlEncodePayloadMiddleware();

        $request = new ServerRequest(
            method:  'POST',
            uri:     '/',
            headers: ['Content-Type' => 'application/json'],
            body:    'foo=bar'
        );

        $middleware->process($request, $this->handler);

        $this->assertNull($this->handler->capturedRequest->getParsedBody());
    }

    public function testHandlesMalformedBodyGracefully(): void
    {
        $middleware = new UrlEncodePayloadMiddleware();

        $request = new ServerRequest(
            method:  'POST',
            uri:     '/',
            headers: ['Content-Type' => 'application/x-www-form-urlencoded'],
            body:    'foo%invalid=bar'
        );

        $middleware->process($request, $this->handler);

        $parsed = $this->handler->capturedRequest->getParsedBody();
        $this->assertIsArray($parsed);
        $this->assertArrayHasKey('foo%invalid', $parsed);
    }

    public function testParsesEmptyBodyAsEmptyArray(): void
    {
        $middleware = new UrlEncodePayloadMiddleware();

        $request = new ServerRequest(
            method:  'POST',
            uri:     '/',
            headers: ['Content-Type' => 'application/x-www-form-urlencoded'],
            body:    ''
        );

        $middleware->process($request, $this->handler);

        $parsed = $this->handler->capturedRequest->getParsedBody();

        $this->assertSame([], $parsed);
    }

    public function testHonorsConfiguredMethods(): void
    {
        $middleware = new UrlEncodePayloadMiddleware(methods: ['PUT']);

        $request = new ServerRequest(
            method:  'POST',
            uri:     '/',
            headers: ['Content-Type' => 'application/x-www-form-urlencoded'],
            body:    'foo=bar'
        );

        $middleware->process($request, $this->handler);

        $parsed = $this->handler->capturedRequest->getParsedBody();

        $this->assertNull($parsed);
    }

    public function testHonorsConfiguredContentType(): void
    {
        $middleware = new UrlEncodePayloadMiddleware(contentType: ['application/custom-urlencoded']);

        $request = new ServerRequest(
            method:  'POST',
            uri:     '/',
            headers: ['Content-Type' => 'application/custom-urlencoded'],
            body:    'foo=bar'
        );

        $middleware->process($request, $this->handler);

        $parsed = $this->handler->capturedRequest->getParsedBody();

        $this->assertSame(['foo' => 'bar'], $parsed);
    }

    public function testOverrideEnabled(): void
    {
        $middleware = new UrlEncodePayloadMiddleware(override: true);

        $request = new ServerRequest(
            method:  'POST',
            uri:     '/',
            headers: ['Content-Type' => 'application/x-www-form-urlencoded'],
            body:    'foo=bar'
        );

        $request = $request->withParsedBody(['original' => 'value']);

        $middleware->process($request, $this->handler);

        $parsed = $this->handler->capturedRequest->getParsedBody();

        $this->assertSame(['foo' => 'bar'], $parsed);
    }

    public function testOverrideDisabled(): void
    {
        $middleware = new UrlEncodePayloadMiddleware(override: false);

        $request = new ServerRequest(
            method:  'POST',
            uri:     '/',
            headers: ['Content-Type' => 'application/x-www-form-urlencoded'],
            body:    'foo=bar'
        );

        $request = $request->withParsedBody(['original' => 'value']);

        $middleware->process($request, $this->handler);

        $parsed = $this->handler->capturedRequest->getParsedBody();

        $this->assertSame(['original' => 'value'], $parsed);
    }
}
