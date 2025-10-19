<?php

declare(strict_types=1);

namespace Zenigata\Http\Emitter;

use Laminas\HttpHandlerRunner\Emitter\EmitterInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * No-op HTTP response emitter.
 *
 * This emitter simulates response emission without producing any real output.
 * Useful for testing or where sending an actual response is not desired.
 */
class NullEmitter implements EmitterInterface
{
    /**
     * Indicates whether the emitter has been invoked.
     *
     * @var bool
     */
    private bool $invoked = false;

    /**
     * Indicates whether the emitter succeeds or fails.
     *
     * @var boolean
     */
    private bool $emit;

    /**
     * Creates a new null emitter instance.
     * 
     * @param bool $emit Defines if the emission succeeds or fails.
     */
    public function __construct(bool $emit = true)
    {
        $this->emit = $emit;
    }

    /**
     * {@inheritDoc}
     *
     * Simulates emitting a response returing the configured emit flag.
     */
    public function emit(ResponseInterface $response): bool
    {
        $this->invoked = true;

        return $this->emit;
    }

    /**
     * Checks whether the emitter has been invoked.
     *
     * @return bool True if the emitter was called at least once, false otherwise.
     */
    public function isInvoked(): bool
    {
        return $this->invoked;
    }
}