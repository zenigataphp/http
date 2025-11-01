<?php

declare(strict_types=1);

namespace Zenigata\Http\Test\Unit\Response;

use InvalidArgumentException;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\Stream;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zenigata\Http\Response\Emitter;
use Zenigata\Http\Test\TestableEmitter;

use function str_repeat;
use function strlen;

/**
 * Unit test for {@see Emitter}.
 *
 * Covered cases:
 * 
 * - Emits headers and body correctly.
 * - Skips headers if they were already sent.
 * - Does not emit a body for status codes without content (204, 205, 304).
 * - Streams large responses in chunks without buffering the whole body.
 * - Stops emission if the client connection closes.
 * - Throws for invalid buffer length values.
 * - Handles responses without Content-Length header.
 * - Rewinds seekable streams before emission.
 */
#[CoversClass(Emitter::class)]
class EmitterTest extends TestCase
{
    public function testEmitsHeadersAndBody(): void
    {
        $response = new Response(200, ['Content-Type' => 'text/plain'], 'hello');
        $emitter = new TestableEmitter();

        $this->expectOutputString('hello');

        $emitter->emit($response);

        $headers = $emitter->getSentHeaders();

        $this->assertCount(2, $headers); // Status + Content-Type
        $this->assertSame('HTTP/1.1 200 OK', $headers[0]['header']);
        $this->assertSame('Content-Type: text/plain', $headers[1]['header']);
    }

    public function testSkipsHeadersIfAlreadySent(): void
    {
        $response = new Response(200, ['X-Test' => 'foo']);
        $emitter = new TestableEmitter(headersSent: true);

        $this->expectOutputString('');

        $emitter->emit($response);

        $this->assertEmpty($emitter->getSentHeaders());
    }

    public function testDoesNotEmitBodyForEmptyResponses(): void
    {
        foreach (Emitter::STATUS_CODES_WITHOUT_BODY as $code) {
            $response = new Response($code);
            $emitter = new TestableEmitter();
    
            $this->expectOutputString('');
    
            $emitter->emit($response);
        }
    }

    public function testStopsEmittingIfConnectionCloses(): void
    {
        $response = new Response(200, body: str_repeat('A', 10000));
        $emitter = new TestableEmitter(1024, connectionNormal: false);

        $this->expectOutputRegex('/^A+/');

        $emitter->emit($response);

        $this->assertLessThan(10000, strlen($this->getActualOutputForAssertion()));
    }

    public function testThrowsForInvalidBufferLength(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new TestableEmitter(0);
    }

    public function testHandlesBodyWithoutContentLengthHeader(): void
    {
        $response = new Response(200, body: 'foobar');
        $emitter = new TestableEmitter();

        $this->expectOutputString('foobar');

        $emitter->emit($response);
    }

    public function testRewindsSeekableStream(): void
    {
        $stream = Stream::create('abc');
        $stream->read(1);

        $response = new Response(200, body: $stream);
        $emitter = new TestableEmitter();

        $this->expectOutputString('abc');

        $emitter->emit($response);
    }
}
