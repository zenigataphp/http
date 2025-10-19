<?php

declare(strict_types=1);

namespace Zenigata\Http\Test\Unit\Emitter;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Zenigata\Http\Emitter\CombinedEmitter;
use Zenigata\Http\Emitter\NullEmitter;
use Zenigata\Testing\Http\FakeResponse;
use Zenigata\Testing\Http\FakeStream;

/**
 * Unit test for {@see CombinedEmitter}.
 *
 * Ensures correct delegation between stream and default emitters.
 * 
 * Covered cases:
 * 
 * - When the stream emitter succeeds, the default emitter is not called.
 * - When the stream emitter fails, the default emitter is used as fallback.
 */
#[CoversClass(CombinedEmitter::class)]
final class CombinedEmitterTest extends TestCase
{
    public function testUsesStreamEmitterWhenStreamingPossible(): void
    {
        $response = new FakeResponse(
            headers: ['Content-Disposition' => 'attachment; filename="x.txt"'],
            body:    new FakeStream('abc')
        );

        $defaultEmitter = new NullEmitter(emit: true);
        $streamEmitter = new NullEmitter(emit: true);;

        $emitter = new CombinedEmitter($defaultEmitter, $streamEmitter);

        $this->assertTrue($emitter->emit($response));
        $this->assertFalse($defaultEmitter->isInvoked());
        $this->assertTrue($streamEmitter->isInvoked());
    }

    public function testFallsBackToDefaultEmitterWhenStreamFails(): void
    {
        $response = new FakeResponse(
            headers: ['Content-Type' => 'text/plain'],
            body:    new FakeStream('body')
        );

        $defaultEmitter = new NullEmitter(emit: true);
        $streamEmitter = new NullEmitter(emit: false);

        $emitter = new CombinedEmitter($defaultEmitter, $streamEmitter);

        $this->assertTrue($emitter->emit($response));
        $this->assertTrue($defaultEmitter->isInvoked());
        $this->assertTrue($streamEmitter->isInvoked());
    }
}
