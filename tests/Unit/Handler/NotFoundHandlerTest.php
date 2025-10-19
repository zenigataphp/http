<?php

declare(strict_types=1);

namespace Zenigata\Http\Test\Unit\Handler;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Zenigata\Http\Handler\NotFoundHandler;
use Zenigata\Testing\Http\FakeServerRequest;

/**
 * Unit test for {@see NotFoundHandler}.
 *
 * Covered cases:
 * 
 * - Default message and text response.
 * - JSON response with message when `Accept: application/json`.
 * - Text response with message when `Accept: text/plain`.
 * - HTML response with message when `Accept: text/html`.
 */
#[CoversClass(NotFoundHandler::class)]
final class NotFoundHandlerTest extends TestCase
{
    /**
     * Not found handler under test
     *
     * @var NotFoundHandler
     */
    private NotFoundHandler $handler;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->handler = new NotFoundHandler();
    }

    public function testDefaults(): void
    {
        $response = $this->handler->handle(new FakeServerRequest());

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame(['text/html'], $response->getHeader('Content-Type'));
        $this->assertStringContainsString('<h1>Not Found</h1>', (string) $response->getBody());
    }

    public function testJsonContentType(): void
    {
        $response = $this->handler->handle(new FakeServerRequest(
            headers: ['Accept' => 'application/json']
        ));

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame(['application/json'], $response->getHeader('Content-Type'));
        $this->assertStringContainsString('"message": "Not Found"', (string) $response->getBody());
    }

    public function testPlainTextContentType(): void
    {
        $response = $this->handler->handle(new FakeServerRequest(
            headers: ['Accept' => 'text/plain']
        ));

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame(['text/plain'], $response->getHeader('Content-Type'));
        $this->assertStringContainsString('Not Found', (string) $response->getBody());
    }

    public function testHtmlContentType(): void
    {
        $response = $this->handler->handle(new FakeServerRequest(
            headers: ['Accept' => 'text/html']
        ));

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame(['text/html'], $response->getHeader('Content-Type'));
        $this->assertStringContainsString('<h1>Not Found</h1>', (string) $response->getBody());
    }
}