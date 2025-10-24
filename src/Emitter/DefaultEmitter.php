<?php

declare(strict_types=1);

namespace Zenigata\Http\Emitter;

use Laminas\HttpHandlerRunner\Emitter\SapiEmitter;
use Psr\Http\Message\ResponseInterface;

/**
 * Default HTTP response emitter.
 *
 * Wraps the {@see SapiEmitter} from Laminas to emit standard PSR-7 responses
 * through PHP’s SAPI (Server API) output.
 */
class DefaultEmitter implements EmitterInterface
{
    /**
     * The internal emitter used for standard SAPI output.
     *
     * @var SapiEmitter
     */
    private SapiEmitter $emitter;

    /**
     * Creates a new default emitter instance.
     */
    public function __construct()
    {
        $this->emitter = new SapiEmitter();
    }

    /**
     * {@inheritDoc}
     *
     * Delegates emission to the internal emitter.
     */
    public function emit(ResponseInterface $response): bool
    {
        return $this->emitter->emit($response);
    }
}