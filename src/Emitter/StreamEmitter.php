<?php

declare(strict_types=1);

namespace Zenigata\Http\Emitter;

use Laminas\HttpHandlerRunner\Emitter\SapiStreamEmitter;
use Psr\Http\Message\ResponseInterface;

/**
 * Stream-based HTTP response emitter.
 *
 * Uses the {@see SapiStreamEmitter} from Laminas to stream large or ranged
 * responses efficiently, such as file downloads or partial content responses.
 */
class StreamEmitter implements EmitterInterface
{
    /**
     * The internal emitter handling stream-based output.
     *
     * @var SapiStreamEmitter
     */
    private SapiStreamEmitter $emitter;

    /**
     * Buffer size in bytes for streaming the response body.
     *
     * @var int
     */
    private int $maxBufferLength;

    /**
     * Creates a new stream emitter instance.
     *
     * @param int $maxBufferLength The buffer size in bytes (default: 8 KB).
     */
    public function __construct(int $maxBufferLength = 8192)
    {
        $this->emitter = new SapiStreamEmitter($maxBufferLength);
        $this->maxBufferLength = $maxBufferLength;
    }

    /**
     * {@inheritDoc}
     *
     * Emits the response using stream mode when appropriate.
     * Delegates emission to the internal emitter.
     */
    public function emit(ResponseInterface $response): bool
    {
        return $this->shouldStream($response)
            ? $this->emitter->emit($response)
            : false;
    }

    /**
     * {@inheritDoc}
     *
     * Streaming is used for responses with file attachments, ranged content,
     * or when the body size exceeds the configured buffer limit.
     */
    public function shouldStream(ResponseInterface $response): bool
    {
        return $response->hasHeader('Content-Disposition')
            || $response->hasHeader('Content-Range')
            || $this->exceedsBufferLimit($response);
    }

    /**
     * Checks if the response body size exceeds the configured buffer limit.
     */
    private function exceedsBufferLimit(ResponseInterface $response): bool
    {
        $contentLength = $response->hasHeader('Content-Length')
            ? (int) $response->getHeaderLine('Content-Length')
            : null;

        return $contentLength !== null
            && $contentLength > $this->maxBufferLength;
    }
}