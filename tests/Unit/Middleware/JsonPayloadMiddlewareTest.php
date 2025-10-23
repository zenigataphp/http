<?php

declare(strict_types=1);

namespace Zenigata\Http\Test\Unit\Middleware;

use const JSON_INVALID_UTF8_SUBSTITUTE;

use Middlewares\Utils\HttpErrorException;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zenigata\Http\Middleware\JsonPayloadMiddleware;

/**
 * Unit test for {@see JsonPayloadMiddleware}.
 *
 * Covered cases:
 *
 * - Parse valid JSON bodies into an associative array.
 * - Ignore requests with unsupported content types.
 * - Throw exception for malformed JSON input.
 * - Parse empty JSON bodies as empty arrays.
 * - Ensure that non-associative decoding returns an object.
 * - Ensure that the depth option is respected for deeply nested JSON.
 * - Ensure that JSON decode options alter decoding behavior. 
 * - Ensure that the middleware only applies to the configured HTTP methods.
 * - Ensure that custom content types are correctly recognized.
 * - Ensure that the override option affects parsed body replacement behavior.
 */
#[CoversClass(JsonPayloadMiddleware::class)]
final class JsonPayloadMiddlewareTest extends TestCase
{
    private RequestHandlerInterface $handler;

    /**
     * {@inheritDoc}
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

    public function testParsesValidJsonBody(): void
    {
        $middleware = new JsonPayloadMiddleware();

        $request = new ServerRequest(
            method:  'POST',
            uri:     '/',
            headers: ['Content-Type' => 'application/json'],
            body:    '{"foo":"bar"}'
        );

        $middleware->process($request, $this->handler);

        $parsed = $this->handler->capturedRequest->getParsedBody();

        $this->assertIsArray($parsed);
        $this->assertSame(['foo' => 'bar'], $parsed);
    }

    public function testSkipsUnsupportedContentType(): void
    {
        $middleware = new JsonPayloadMiddleware();

        $request = new ServerRequest(
            method:  'POST',
            uri:     '/',
            headers: ['Content-Type' => 'text/plain'],
            body:    '{"foo":"bar"}'
        );

        $middleware->process($request, $this->handler);

        $parsed = $this->handler->capturedRequest->getParsedBody();

        $this->assertNull($parsed);
    }

    public function testThrowsOnInvalidJson(): void
    {
        $this->expectException(HttpErrorException::class);
        $this->expectExceptionMessage('Bad Request');

        $middleware = new JsonPayloadMiddleware();

        $request = new ServerRequest(
            method:  'POST',
            uri:     '/',
            headers: ['Content-Type' => 'application/json'],
            body:    '{invalid json}'
        );

        $middleware->process($request, $this->handler);
    }

    public function testParsesEmptyBodyAsEmptyArray(): void
    {
        $middleware = new JsonPayloadMiddleware();

        $request = new ServerRequest(
            method:  'POST',
            uri:     '/',
            headers: ['Content-Type' => 'application/json'],
            body:    ''
        );

        $middleware->process($request, $this->handler);

        $parsed = $this->handler->capturedRequest->getParsedBody();

        $this->assertSame([], $parsed);
    }

    public function testAssociativeOptionAffectsDecodedStructure(): void
    {
        $middleware = new JsonPayloadMiddleware(associative: false);

        $request = new ServerRequest(
            method:  'POST',
            uri:     '/',
            headers: ['Content-Type' => 'application/json'],
            body:    '{"foo":"bar"}'
        );

        $middleware->process($request, $this->handler);

        $parsed = $this->handler->capturedRequest->getParsedBody();

        $this->assertIsObject($parsed);
        $this->assertSame('bar', $parsed->foo);
    }

    public function testDepthOptionIsRespected(): void
    {
        $this->expectException(HttpErrorException::class);
        $this->expectExceptionMessage('Bad Request');

        $middleware = new JsonPayloadMiddleware(depth: 2);

        $request = new ServerRequest(
            method:  'POST',
            uri:     '/',
            headers: ['Content-Type' => 'application/json'],
            body:    '{"level1":{"level2":{"level3":"x"}}}'
        );

        $middleware->process($request, $this->handler);
    }

    public function testJsonDecodeOptionsAffectInvalidUtf8Handling(): void
    {
        // Invalid UTF-8 sequence (\xC3 without continuation)
        $invalidUtf8 = "{\"foo\":\"bar\xC3\"}";

        // Without JSON_INVALID_UTF8_SUBSTITUTE, decoding throws or returns null.
        $middleware = new JsonPayloadMiddleware(options: JSON_INVALID_UTF8_SUBSTITUTE);

        $request = new ServerRequest(
            method:  'POST',
            uri:     '/',
            headers: ['Content-Type' => ['application/json']],
            body:    $invalidUtf8
        );

        $middleware->process($request, $this->handler);

        $parsed = $this->handler->capturedRequest->getParsedBody();

        // The invalid byte should be replaced with the Unicode replacement char (�)
        $this->assertSame(['foo' => "bar�"], $parsed);
    }

    public function testHonorsConfiguredMethods(): void
    {
        $middleware = new JsonPayloadMiddleware(methods: ['PUT']);

        $request = new ServerRequest(
            method:  'POST',
            uri:     '/',
            headers: ['Content-Type' => 'application/json'],
            body:    '{"foo":"bar"}'
        );

        $middleware->process($request, $this->handler);

        $parsed = $this->handler->capturedRequest->getParsedBody();

        $this->assertNull($parsed);
    }

    public function testHonorsConfiguredContentType(): void
    {
        $middleware = new JsonPayloadMiddleware(contentType: ['application/custom+json']);

        $request = new ServerRequest(
            method:  'POST',
            uri:     '/',
            headers: ['Content-Type' => 'application/custom+json'],
            body:    '{"foo":"bar"}'
        );

        $middleware->process($request, $this->handler);

        $parsed = $this->handler->capturedRequest->getParsedBody();

        $this->assertSame(['foo' => 'bar'], $parsed);
    }

    public function testOverrideEnaled(): void
    {
        $middleware = new JsonPayloadMiddleware(override: true);

        $request = new ServerRequest(
            method:  'POST',
            uri:     '/',
            headers: ['Content-Type' => 'application/json'],
            body:    '{"foo":"bar"}'
        );

        $request = $request->withParsedBody(['original' => 'value']);

        $middleware->process($request, $this->handler);

        $parsed = $this->handler->capturedRequest->getParsedBody();

        $this->assertSame(['foo' => 'bar'], $parsed);
    }

    public function testOverrideDisabled(): void
    {
        $middleware = new JsonPayloadMiddleware(override: false);

        $request = new ServerRequest(
            method:  'POST',
            uri:     '/',
            headers: ['Content-Type' => 'application/json'],
            body:    '{"foo":"bar"}'
        );

        $request = $request->withParsedBody(['original' => 'value']);

        $middleware->process($request, $this->handler);

        $parsed = $this->handler->capturedRequest->getParsedBody();

        $this->assertSame(['original' => 'value'], $parsed);
    }
}
