<?php

declare(strict_types=1);

namespace Zenigata\Http\Emitter;

use Laminas\HttpHandlerRunner\Emitter\EmitterInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Combined HTTP response emitter.
 *
 * Delegates response emission to specialized emitters: when applicable,
 * streaming emitters are used, otherwise default emitters.
 */
class CombinedEmitter implements EmitterInterface
{
    /**
     * Creates a new response emitter instance.
     * 
     * @param EmitterInterface $defaultEmitter Used as fallback when streaming is unavailable.
     * @param EmitterInterface $streamEmitter  Used for emitting responses with streamable bodies.
     */
    public function __construct(
        private EmitterInterface $defaultEmitter = new DefaultEmitter(),
        private EmitterInterface $streamEmitter  = new StreamEmitter(), 
    ) {}

    /**
     * {@inheritDoc}
     *
     * Attempts to emit using the stream emitter first,
     * then falls back to the default emitter.
     */
    public function emit(ResponseInterface $response): bool
    {
        if ($this->streamEmitter->emit($response)) {
            return true;
        }

        return $this->defaultEmitter->emit($response);
    }
}