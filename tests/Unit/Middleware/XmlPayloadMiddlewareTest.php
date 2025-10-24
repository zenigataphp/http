<?php

declare(strict_types=1);

namespace Zenigata\Http\Test\Unit\Middleware;

use SimpleXMLElement;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zenigata\Http\Error\HttpError;
use Zenigata\Http\Middleware\XmlPayloadMiddleware;

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

    public function testParsesValidXmlBody(): void
    {
        $middleware = new XmlPayloadMiddleware();

        $request = new ServerRequest(
            method:  'POST',
            uri:     '/',
            headers: ['Content-Type' => 'application/xml'],
            body:    '<root><foo>bar</foo></root>'
        );

        $middleware->process($request, $this->handler);

        $parsed = $this->handler->capturedRequest->getParsedBody();

        $this->assertInstanceOf(\SimpleXMLElement::class, $parsed);
        $this->assertSame('bar', (string) $parsed->foo);
    }

    public function testSkipsUnsupportedContentType(): void
    {
        $middleware = new XmlPayloadMiddleware();

        $request = new ServerRequest(
            method:  'POST',
            uri:     '/',
            headers: ['Content-Type' => 'text/plain'],
            body:    '<foo>bar</foo>'
        );

        $middleware->process($request, $this->handler);

        $parsed = $this->handler->capturedRequest->getParsedBody();

        $this->assertNull($parsed);
    }

    public function testThrowsOnInvalidXml(): void
    {
        $this->expectException(HttpError::class);
        $this->expectExceptionMessage('Bad Request');

        $middleware = new XmlPayloadMiddleware();

        $request = new ServerRequest(
            method:  'POST',
            uri:     '/',
            headers: ['Content-Type' => 'application/xml'],
            body:    '<foo><bar></foo>'
        );


        @$middleware->process($request, $this->handler); // TODO can we make better than this?
    }

    public function testParsesEmptyBodyAsNull(): void
    {
        $middleware = new XmlPayloadMiddleware();

        $request = new ServerRequest(
            method:  'POST',
            uri:     '/',
            headers: ['Content-Type' => 'application/xml'],
            body:    ''
        );

        $middleware->process($request, $this->handler);

        $parsed = $this->handler->capturedRequest->getParsedBody();

        $this->assertNull($parsed);
    }

    public function testHonorsConfiguredMethods(): void
    {
        $middleware = new XmlPayloadMiddleware(methods: ['PUT']);

        $request = new ServerRequest(
            method:  'POST',
            uri:     '/',
            headers: ['Content-Type' => 'application/xml'],
            body:    '<root><foo>bar</foo></root>'
        );

        $middleware->process($request, $this->handler);

        $parsed = $this->handler->capturedRequest->getParsedBody();

        $this->assertNull($parsed);
    }

    public function testHonorsConfiguredContentType(): void
    {
        $middleware = new XmlPayloadMiddleware(contentType: ['application/custom+xml']);

        $request = new ServerRequest(
            method:  'POST',
            uri:     '/',
            headers: ['Content-Type' => 'application/custom+xml'],
            body:    '<root><foo>bar</foo></root>'
        );

        $middleware->process($request, $this->handler);

        $parsed = $this->handler->capturedRequest->getParsedBody();

        $this->assertInstanceOf(SimpleXMLElement::class, $parsed);
        $this->assertSame('bar', (string) $parsed->foo);
    }

    public function testOverrideEnabled(): void
    {
        $middleware = new XmlPayloadMiddleware(override: true);

        $request = new ServerRequest(
            method:  'POST',
            uri:     '/',
            headers: ['Content-Type' => 'application/xml'],
            body:    '<root><foo>bar</foo></root>'
        );

        $request = $request->withParsedBody(['original' => 'value']);

        $middleware->process($request, $this->handler);

        $parsed = $this->handler->capturedRequest->getParsedBody();

        $this->assertInstanceOf(SimpleXMLElement::class, $parsed);
        $this->assertSame('bar', (string) $parsed->foo);
    }

    public function testOverrideDisabled(): void
    {
        $middleware = new XmlPayloadMiddleware(override: false);

        $request = new ServerRequest(
            method:  'POST',
            uri:     '/',
            headers: ['Content-Type' => 'application/xml'],
            body:    '<root><foo>bar</foo></root>'
        );

        $request = $request->withParsedBody(['original' => 'value']);

        $middleware->process($request, $this->handler);

        $parsed = $this->handler->capturedRequest->getParsedBody();

        $this->assertSame(['original' => 'value'], $parsed);
    }
}
