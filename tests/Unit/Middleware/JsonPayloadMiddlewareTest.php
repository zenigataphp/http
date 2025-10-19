<?php

declare(strict_types=1);

namespace Zenigata\Http\Test\Unit\Middleware;

use const JSON_INVALID_UTF8_SUBSTITUTE;

use Middlewares\Utils\HttpErrorException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zenigata\Http\Middleware\JsonPayloadMiddleware;
use Zenigata\Testing\Http\FakeRequestHandler;
use Zenigata\Testing\Http\FakeServerRequest;
use Zenigata\Testing\Http\FakeStream;

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
    public function testParsesValidJsonBody(): void
    {
        $middleware = new JsonPayloadMiddleware();

        $request = new FakeServerRequest(
            method:  'POST',
            headers: ['Content-Type' => 'application/json'],
            body:    new FakeStream('{"foo":"bar"}')
        );

        $handler = $this->createRequestHandler();
        $middleware->process($request, $handler);

        $parsed = $handler->capturedRequest->getParsedBody();

        $this->assertIsArray($parsed);
        $this->assertSame(['foo' => 'bar'], $parsed);
    }

    public function testSkipsUnsupportedContentType(): void
    {
        $middleware = new JsonPayloadMiddleware();

        $request = new FakeServerRequest(
            method:  'POST',
            headers: ['Content-Type' => 'text/plain'],
            body:    new FakeStream('{"foo":"bar"}')
        );

        $handler = $this->createRequestHandler();
        $middleware->process($request, $handler);

        $this->assertNull($handler->capturedRequest->getParsedBody());
    }

    public function testThrowsOnInvalidJson(): void
    {
        $this->expectException(HttpErrorException::class);
        $this->expectExceptionMessage('Bad Request');

        $middleware = new JsonPayloadMiddleware();

        $request = new FakeServerRequest(
            method:  'POST',
            headers: ['Content-Type' => 'application/json'],
            body:    new FakeStream('{invalid json}')
        );

        $handler = $this->createRequestHandler();
        $middleware->process($request, $handler);
    }

    public function testParsesEmptyBodyAsEmptyArray(): void
    {
        $middleware = new JsonPayloadMiddleware();

        $request = new FakeServerRequest(
            method:  'POST',
            headers: ['Content-Type' => 'application/json'],
            body:    new FakeStream()
        );

        $handler = $this->createRequestHandler();
        $middleware->process($request, $handler);

        $this->assertSame([], $handler->capturedRequest->getParsedBody());
    }

    public function testAssociativeOptionAffectsDecodedStructure(): void
    {
        $middleware = new JsonPayloadMiddleware(associative: false);

        $request = new FakeServerRequest(
            method:  'POST',
            headers: ['Content-Type' => 'application/json'],
            body:    new FakeStream('{"foo":"bar"}')
        );

        $handler = $this->createRequestHandler();
        $middleware->process($request, $handler);

        $parsed = $handler->capturedRequest->getParsedBody();

        $this->assertIsObject($parsed);
        $this->assertSame('bar', $parsed->foo);
    }

    public function testDepthOptionIsRespected(): void
    {
        $this->expectException(HttpErrorException::class);
        $this->expectExceptionMessage('Bad Request');

        $middleware = new JsonPayloadMiddleware(depth: 2);

        $request = new FakeServerRequest(
            method:  'POST',
            headers: ['Content-Type' => 'application/json'],
            body:    new FakeStream('{"level1":{"level2":{"level3":"x"}}}')
        );

        $handler = $this->createRequestHandler();
        $middleware->process($request, $handler);
    }

    public function testJsonDecodeOptionsAffectInvalidUtf8Handling(): void
    {
        // Invalid UTF-8 sequence (\xC3 without continuation)
        $invalidUtf8 = "{\"foo\":\"bar\xC3\"}";

        // Without JSON_INVALID_UTF8_SUBSTITUTE, decoding throws or returns null.
        $middleware = new JsonPayloadMiddleware(options: JSON_INVALID_UTF8_SUBSTITUTE);

        $request = new FakeServerRequest(
            method:  'POST',
            headers: ['Content-Type' => ['application/json']],
            body:    new FakeStream($invalidUtf8)
        );

        $handler = $this->createRequestHandler();
        $middleware->process($request, $handler);

        $parsed = $handler->capturedRequest->getParsedBody();

        // The invalid byte should be replaced with the Unicode replacement char (�)
        $this->assertSame(['foo' => "bar�"], $parsed);
    }

    public function testHonorsConfiguredMethods(): void
    {
        $middleware = new JsonPayloadMiddleware(methods: ['PUT']);

        $request = new FakeServerRequest(
            method:  'POST',
            headers: ['Content-Type' => 'application/json'],
            body:    new FakeStream('{"foo":"bar"}')
        );

        $handler = $this->createRequestHandler();
        $middleware->process($request, $handler);

        $this->assertNull($handler->capturedRequest->getParsedBody());
    }

    public function testHonorsConfiguredContentType(): void
    {
        $middleware = new JsonPayloadMiddleware(contentType: ['application/custom+json']);

        $request = new FakeServerRequest(
            method:  'POST',
            headers: ['Content-Type' => 'application/custom+json'],
            body:    new FakeStream('{"foo":"bar"}')
        );

        $handler = $this->createRequestHandler();
        $middleware->process($request, $handler);

        $this->assertSame(['foo' => 'bar'], $handler->capturedRequest->getParsedBody());
    }

    public function testOverrideEnaled(): void
    {
        $middleware = new JsonPayloadMiddleware(override: true);

        $request = new FakeServerRequest(
            method:     'POST',
            headers:    ['Content-Type' => 'application/json'],
            body:       new FakeStream('{"foo":"bar"}'),
            parsedBody: ['original' => 'value']
        );

        $handler = $this->createRequestHandler();
        $middleware->process($request, $handler);

        $this->assertSame(['foo' => 'bar'], $handler->capturedRequest->getParsedBody());
    }

    public function testOverrideDisabled(): void
    {
        $middleware = new JsonPayloadMiddleware(override: false);

        $request = new FakeServerRequest(
            method:     'POST',
            headers:    ['Content-Type' => 'application/json'],
            body:       new FakeStream('{"foo":"bar"}'),
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
