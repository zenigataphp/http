<?php

declare(strict_types=1);

namespace Zenigata\Http\Test\Unit\Middleware;

use SimpleXMLElement;
use Middlewares\Utils\HttpErrorException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zenigata\Http\Middleware\XmlPayloadMiddleware;
use Zenigata\Testing\Http\FakeRequestHandler;
use Zenigata\Testing\Http\FakeServerRequest;
use Zenigata\Testing\Http\FakeStream;

/**
 * Unit test for {@see XmlPayloadMiddleware}.
 *
 * Covered cases:
 *
 * - Parse valid XML bodies into objects.
 * - Ignore unsupported content types.
 * - Throw exception for invalid XML.
 * - Parse empty bodies as null.
 * - Ensure that the middleware only applies to the configured HTTP methods.
 * - Ensure that custom content types are correctly recognized.
 * - Ensure that the override option affects parsed body replacement behavior.
 */
#[CoversClass(XmlPayloadMiddleware::class)]
final class XmlPayloadMiddlewareTest extends TestCase
{
    public function testParsesValidXmlBody(): void
    {
        $middleware = new XmlPayloadMiddleware();

        $request = new FakeServerRequest(
            method:  'POST',
            headers: ['Content-Type' => 'application/xml'],
            body:    new FakeStream('<root><foo>bar</foo></root>')
        );

        $handler = $this->createRequestHandler();
        $middleware->process($request, $handler);

        $parsed = $handler->capturedRequest->getParsedBody();

        $this->assertInstanceOf(\SimpleXMLElement::class, $parsed);
        $this->assertSame('bar', (string) $parsed->foo);
    }

    public function testSkipsUnsupportedContentType(): void
    {
        $middleware = new XmlPayloadMiddleware();

        $request = new FakeServerRequest(
            method:  'POST',
            headers: ['Content-Type' => 'text/plain'],
            body:    new FakeStream('<foo>bar</foo>')
        );

        $handler = $this->createRequestHandler();
        $middleware->process($request, $handler);

        $this->assertNull($handler->capturedRequest->getParsedBody());
    }

    public function testThrowsOnInvalidXml(): void
    {
        $this->expectException(HttpErrorException::class);
        $this->expectExceptionMessage('Bad Request');

        $middleware = new XmlPayloadMiddleware();

        $request = new FakeServerRequest(
            method:  'POST',
            headers: ['Content-Type' => 'application/xml'],
            body:    new FakeStream('<foo><bar></foo>')
        );

        $handler = $this->createRequestHandler();

        @$middleware->process($request, $handler); // TODO capire se migliorare o eliminare il test case
    }

    public function testParsesEmptyBodyAsNull(): void
    {
        $middleware = new XmlPayloadMiddleware();

        $request = new FakeServerRequest(
            method:  'POST',
            headers: ['Content-Type' => 'application/xml'],
            body:    new FakeStream()
        );

        $handler = $this->createRequestHandler();
        $middleware->process($request, $handler);

        $this->assertNull($handler->capturedRequest->getParsedBody());
    }

    public function testHonorsConfiguredMethods(): void
    {
        $middleware = new XmlPayloadMiddleware(methods: ['PUT']);

        $request = new FakeServerRequest(
            method:  'POST',
            headers: ['Content-Type' => 'application/xml'],
            body:    new FakeStream('<root><foo>bar</foo></root>')
        );

        $handler = $this->createRequestHandler();
        $middleware->process($request, $handler);

        $this->assertNull($handler->capturedRequest->getParsedBody());
    }

    public function testHonorsConfiguredContentType(): void
    {
        $middleware = new XmlPayloadMiddleware(contentType: ['application/custom+xml']);

        $request = new FakeServerRequest(
            method:  'POST',
            headers: ['Content-Type' => 'application/custom+xml'],
            body:    new FakeStream('<root><foo>bar</foo></root>')
        );

        $handler = $this->createRequestHandler();
        $middleware->process($request, $handler);

        $parsed = $handler->capturedRequest->getParsedBody();

        $this->assertInstanceOf(SimpleXMLElement::class, $parsed);
        $this->assertSame('bar', (string) $parsed->foo);
    }

    public function testOverrideEnabled(): void
    {
        $middleware = new XmlPayloadMiddleware(override: true);

        $request = new FakeServerRequest(
            method:     'POST',
            headers:    ['Content-Type' => 'application/xml'],
            body:       new FakeStream('<root><foo>bar</foo></root>'),
            parsedBody: ['old' => 'value']
        );

        $handler = $this->createRequestHandler();
        $middleware->process($request, $handler);

        $parsed = $handler->capturedRequest->getParsedBody();

        $this->assertInstanceOf(SimpleXMLElement::class, $parsed);
        $this->assertSame('bar', (string) $parsed->foo);
    }

    public function testOverrideDisabled(): void
    {
        $middleware = new XmlPayloadMiddleware(override: false);

        $request = new FakeServerRequest(
            method:     'POST',
            headers:    ['Content-Type' => 'application/xml'],
            body:       new FakeStream('<root><foo>bar</foo></root>'),
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
