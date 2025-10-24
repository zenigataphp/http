<?php

declare(strict_types=1);

namespace Zenigata\Http\Test\Unit\Emitter;

use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Zenigata\Http\Emitter\EmitterInterface;
use Zenigata\Http\Emitter\NullEmitter;

/**
 * Unit test for {@see NullEmitter}.
 *
 * Ensures the null emitter correctly simulates response emission and tracks invocation state.
 * 
 * Covered cases:
 * 
 * - Default state.
 * - Emission returns `true` when configured as successful.
 * - Emission returns `false` when configured as failed.
 * - Invocation state is updated after each emission.
 */
#[CoversClass(NullEmitter::class)]
final class NullEmitterTest extends TestCase
{
    public function testDefaults(): void
    {
        $emitter = new NullEmitter();

        $this->assertInstanceOf(EmitterInterface::class, $emitter);
        $this->assertFalse($emitter->isInvoked());
    }

    public function testEmitFlagIsTrue(): void
    {
        $emitter = new NullEmitter(emit: true);

        $emitted = $emitter->emit(new Response());

        $this->assertTrue($emitted);
        $this->assertTrue($emitter->isInvoked());
    }

    public function testEmitFlagIsFalse(): void
    {
        $emitter = new NullEmitter(emit: false);

        $emitted = $emitter->emit(new Response());

        $this->assertFalse($emitted);
        $this->assertTrue($emitter->isInvoked());
    }
}