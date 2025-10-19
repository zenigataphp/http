<?php

declare(strict_types=1);

namespace Zenigata\Http\Test\Unit\Emitter;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Zenigata\Http\Emitter\StreamEmitter;
use Zenigata\Testing\Http\FakeResponse;
use Zenigata\Testing\Http\FakeStream;

/**
 * Unit test for {@see StreamEmitter}.
 *
 * Verifies the conditions under which a response is streamed.
 * 
 * Covered cases:
 * 
 * - Standard responses without relevant headers are not streamed.
 * - Responses with `Content-Disposition` are streamed.
 * - Responses with `Content-Range` are streamed.
 * - Responses exceeding the buffer limit are streamed.
 * - Responses smaller than the buffer limit are not streamed.
 */
#[CoversClass(StreamEmitter::class)]
final class StreamEmitterTest extends TestCase
{
    public function testNoStreamWithoutHeaders(): void
    {
        $emitter = new StreamEmitter(1024);

        $response = new FakeResponse(
            headers: ['Content-Type' => 'text/plain'],
            body:    new FakeStream('small body')
        );

        $this->assertFalse($emitter->shouldStream($response));
    }

    public function testStreamWithContentDisposition(): void
    {
        $emitter = new StreamEmitter(1024);

        $response = new FakeResponse(
            headers: ['Content-Disposition' => 'attachment; filename="file.txt"'],
            body:    new FakeStream('file content')
        );

        $this->assertTrue($emitter->shouldStream($response));
    }

    public function testStreamWithContentRange(): void
    {
        $emitter = new StreamEmitter(1024);

        $response = new FakeResponse(
            headers: ['Content-Range' => 'bytes 0-99/100'],
            body:    new FakeStream('partial content')
        );

        $this->assertTrue($emitter->shouldStream($response));
    }

    public function testStreamWhenLengthExceedsBuffer(): void
    {
        $emitter = new StreamEmitter(5);

        $response = new FakeResponse(
            headers: ['Content-Length' => '10'],
            body:    new FakeStream('0123456789')
        );

        $this->assertTrue($emitter->shouldStream($response));
    }

    public function testNoStreamWhenLengthBelowBuffer(): void
    {
        $emitter = new StreamEmitter(50);

        $response = new FakeResponse(
            headers: ['Content-Length' => '10'],
            body:    new FakeStream('0123456789')
        );

        $this->assertFalse($emitter->shouldStream($response));
    }
}
