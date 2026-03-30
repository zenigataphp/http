<?php

declare(strict_types=1);

namespace Zenigata\Http\Test\Unit\Middleware;

use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\Stream;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Zenigata\Http\Error\HttpError;
use Zenigata\Http\Middleware\BodyParserMiddleware;
use Zenigata\Http\Test\FakeBodyParser;
use Zenigata\Http\Test\FakeRequestHandler;

/**
 * Unit test for {@see Zenigata\Http\Middleware\BodyParserMiddleware}.
 *
 * Covered cases:
 *
 * - Skip parsing when the request body is empty.
 * - Skip parsing when the request already has a parsed body.
 * - Skip parsing when no Content-Type header is present.
 * - Parse the body using the matching parser and attach the result to the request.
 * - Skip parsing when no parser supports the content type.
 * - Throw HttpError 400 when parsing fails.
 * - addParser() appends a parser to the stack.
 */
#[CoversClass(BodyParserMiddleware::class)]
final class BodyParserMiddlewareTest extends TestCase
{
    private FakeRequestHandler $handler;

    private ServerRequestInterface $request;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->handler = new FakeRequestHandler();
        $this->request = new ServerRequest('POST', '/', ['Content-Type' => 'application/json']);
    }

    public function testProcessSkipsEmptyBody(): void
    {
        $parser     = new FakeBodyParser('application/json', result: ['data']);
        $middleware = new BodyParserMiddleware([$parser]);

        $middleware->process($this->request, $this->handler);

        $this->assertNull($this->handler->getRequest()->getParsedBody());
    }

    public function testProcessKeepsParsedBody(): void
    {
        $parser     = new FakeBodyParser('application/json', result: ['overwritten']);
        $middleware = new BodyParserMiddleware([$parser]);

        $request = $this->request
            ->withBody(Stream::create('{"key":"value"}'))
            ->withParsedBody(['already' => 'parsed']);

        $middleware->process($request, $this->handler);

        $this->assertSame(['already' => 'parsed'], $this->handler->getRequest()->getParsedBody());
    }

    public function testProcessSkipsMissingContentType(): void
    {
        $parser     = new FakeBodyParser('application/json', result: ['data']);
        $middleware = new BodyParserMiddleware([$parser]);

        $request = $this->request
            ->withoutHeader('Content-Type')
            ->withBody(Stream::create('{"key":"value"}'));

        $middleware->process($request, $this->handler);

        $this->assertNull($this->handler->getRequest()->getParsedBody());
    }

    public function testProcessParsesBody(): void
    {
        $parser     = new FakeBodyParser('application/json', result: ['name' => 'Alice']);
        $middleware = new BodyParserMiddleware([$parser]);

        $request = $this->request->withBody(Stream::create('{"name":"Alice"}'));

        $middleware->process($request, $this->handler);

        $this->assertSame(['name' => 'Alice'], $this->handler->getRequest()->getParsedBody());
    }

    public function testProcessSkipsUnsupportedContentType(): void
    {
        $parser     = new FakeBodyParser('application/json', result: ['data']);
        $middleware = new BodyParserMiddleware([$parser]);

        $request = $this->request
            ->withHeader('Content-Type', 'text/plain')
            ->withBody(Stream::create('hello'));

        $middleware->process($request, $this->handler);

        $this->assertNull($this->handler->getRequest()->getParsedBody());
    }

    public function testProcessThrowsOnInvalidBody(): void
    {
        $parser     = new FakeBodyParser('application/json', throws: true);
        $middleware = new BodyParserMiddleware([$parser]);

        $request = $this->request->withBody(Stream::create('{invalid}'));

        $this->expectException(HttpError::class);
        $this->expectExceptionCode(400);
        $this->expectExceptionMessage('Parse error.');

        $middleware->process($request, $this->handler);
    }

    public function testAddParserAddsToStack(): void
    {
        $middleware = new BodyParserMiddleware([]);
        $parser     = new FakeBodyParser('text/csv');

        $middleware->addParser($parser);

        $this->assertContains($parser, $middleware->getParsers());
    }
}