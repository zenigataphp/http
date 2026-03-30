<?php

declare(strict_types=1);

namespace Zenigata\Http\Test\Unit\Runtime;

use InvalidArgumentException;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\Stream;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zenigata\Http\Runtime\ResponseEmitter;
use Zenigata\Http\Test\TestableResponseEmitter;

use function array_column;
use function array_filter;
use function array_values;
use function ob_get_clean;
use function ob_start;
use function str_starts_with;
use function str_repeat;
use function strlen;

/**
 * Unit test for {@see Zenigata\Http\Runtime\ResponseEmitter}.
 *
 * Covered cases:
 *
 * - Throw InvalidArgumentException when bufferLength is less than 1.
 * - Emit the status line with the correct protocol version, code and reason phrase.
 * - Emit response headers.
 * - Emit multiple Set-Cookie headers without replacing one another.
 * - Skip headers when they have already been sent.
 * - Skip the body for status codes that never carry one (204, 205, 304).
 * - Skip the body when the response body is empty.
 * - Rewind a seekable stream before emitting the body.
 * - Emit the full body when no Content-Length header is present.
 * - Stop emitting when the client connection drops mid-stream.
 */
#[CoversClass(ResponseEmitter::class)]
final class ResponseEmitterTest extends TestCase
{
    public function testRejectsInvalidBufferLength(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new TestableResponseEmitter(0);
    }

    public function testEmitsStatusLine(): void
    {
        $emitter = new TestableResponseEmitter();

        $emitter->emit(new Response(404));

        $this->assertSame('HTTP/1.1 404 Not Found', $emitter->getSentHeaders()[0]['header']);
    }

    public function testEmitsResponseHeaders(): void
    {
        $emitter  = new TestableResponseEmitter();
        $response = new Response(200, ['Content-Type' => 'text/plain']);

        $emitter->emit($response);

        $headers = array_column($emitter->getSentHeaders(), 'header');

        $this->assertContains('Content-Type: text/plain', $headers);
    }

    public function testEmitsMultipleCookiesWithoutReplacing(): void
    {
        $emitter  = new TestableResponseEmitter();
        $response = (new Response())
            ->withAddedHeader('Set-Cookie', 'a=1')
            ->withAddedHeader('Set-Cookie', 'b=2');

        $emitter->emit($response);

        $setCookies = array_filter(
            $emitter->getSentHeaders(),
            fn($h) => str_starts_with($h['header'], 'Set-Cookie'),
        );

        $this->assertCount(2, $setCookies);
        $this->assertFalse(array_values($setCookies)[0]['replace']);
        $this->assertFalse(array_values($setCookies)[1]['replace']);
    }

    public function testSkipsSentHeaders(): void
    {
        $emitter  = new TestableResponseEmitter(headersSent: true);
        $response = new Response(200, ['X-Test' => 'foo']);

        $emitter->emit($response);

        $this->assertEmpty($emitter->getSentHeaders());
    }

    public function testSkipsBodyForEmptyStatus(): void
    {
        foreach (ResponseEmitter::CODES_WITHOUT_BODY as $code) {
            $emitter = new TestableResponseEmitter();

            $emitter->emit((new Response($code))->withBody(Stream::create('should not appear')));

            $this->expectOutputString('');
            $emitter->emit(new Response($code));
        }
    }

    public function testSkipsBodyWhenBodyIsEmpty(): void
    {
        $emitter = new TestableResponseEmitter();

        $this->expectOutputString('');

        $emitter->emit((new Response())->withBody(Stream::create('')));
    }

    public function testRewindsStreamBeforeEmit(): void
    {
        $stream = Stream::create('abc');
        $stream->read(1); // advance past 'a'

        $emitter = new TestableResponseEmitter();

        $this->expectOutputString('abc');

        $emitter->emit(new Response(200, body: $stream));
    }

    public function testEmitsBodyWithoutLength(): void
    {
        $emitter = new TestableResponseEmitter();

        $this->expectOutputString('foobar');

        $emitter->emit(new Response(200, body: 'foobar'));
    }

    public function testStopsOnClosedConnection(): void
    {
        // Buffer 1024 bytes, body 10000 bytes, connection drops immediately after first chunk.
        $emitter  = new TestableResponseEmitter(1024, connectionNormal: false);
        $response = new Response(200, body: str_repeat('A', 10000));

        ob_start();
        $emitter->emit($response);
        $output = ob_get_clean();

        $this->assertLessThan(10000, strlen($output));
        $this->assertGreaterThan(0, strlen($output));
    }
}