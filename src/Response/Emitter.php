<?php

declare(strict_types=1);

namespace Zenigata\Http\Response;

use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;

use const CONNECTION_NORMAL;

use function connection_status;
use function header;
use function headers_sent;
use function min;
use function in_array;
use function sprintf;
use function strlen;
use function strtolower;

/**
 * Implementation of {@see Zenigata\Http\Response\EmitterInterface}.
 *
 * Emits a PSR-7 response to the current SAPI environment using streaming,
 * minimizing memory usage and handling large payloads gracefully.
 */
class Emitter implements EmitterInterface
{
    /**
     * Status codes that never include a body.
     *
     * @var int[]
     */
    public const STATUS_CODES_WITHOUT_BODY = [204, 205, 304];

    /**
     * Maximum bytes emitted per iteration to balance memory and responsiveness.
     * 
     * @var int
     */
    private int $bufferLength;

    /**
     * Creates a new emitter instance.
     *
     * @param int $bufferLength The maximum number of bytes to read and emit per iteration.
     */
    public function __construct(int $bufferLength = 8192) // 8 KB
    {
        if ($bufferLength < 1) {
            throw new InvalidArgumentException(sprintf(
                'Buffer length must be greater than zero; received %d.',
                $bufferLength
            ));
        }

        $this->bufferLength = $bufferLength;
    }

    /**
     * @inheritDoc
     */
    public function emit(ResponseInterface $response): void
    {
        if (!$this->headersSent()) {
            $this->emitStatusLine($response);
            $this->emitHeaders($response);
        }

        if (!$this->isResponseEmpty($response)) {
            $this->emitBody($response);
        }
    }

    /**
     * Checks if headers have already been sent.
     */
    protected function headersSent(): bool
    {
        return headers_sent();
    }

    /**
     * Sends a single HTTP header line.
     */
    protected function sendHeader(string $header, bool $replace, int $code = 0): void
    {
        header($header, $replace, $code);
    }

    /**
     * Checks whether the client connection is still open.
     */
    protected function isConnectionNormal(): bool
    {
        return connection_status() === CONNECTION_NORMAL;
    }

    /**
     * Emits all headers from the response.
     */
    private function emitHeaders(ResponseInterface $response): void
    {
        foreach ($response->getHeaders() as $name => $values) {
            $replace = strtolower((string) $name) !== 'set-cookie';

            foreach ($values as $value) {
                $this->sendHeader(
                    header:  sprintf('%s: %s', $name, $value),
                    replace: $replace
                );
                
                $replace = false;
            }
        }
    }

    /**
     * Emits the HTTP status line from the response.
     */
    private function emitStatusLine(ResponseInterface $response): void
    {
        $code = $response->getStatusCode();

        $this->sendHeader(
            header: sprintf(
                'HTTP/%s %d %s',
                $response->getProtocolVersion(),
                $code,
                $response->getReasonPhrase()
            ),
            replace: true,
            code: $code
        );
    }

    /**
     * Emits the response body to the client in streaming chunks.
     */
    private function emitBody(ResponseInterface $response): void
    {
        $body = $response->getBody();

        if ($body->isSeekable()) {
            $body->rewind();
        }

        $length = (int) $response->getHeaderLine('Content-Length');

        if ($length === 0) {
            $length = $body->getSize() ?? 0;
        }

        while (!$body->eof()) {
            $chunk = $this->calculateChunkSize($length);
            $data  = $body->read($chunk);

            echo $data;

            $length -= strlen($data);

            if ($length <= 0 || !$this->isConnectionNormal()) {
                break;
            }
        }
    }

    /**
     * Calculates the optimal chunk size for streaming a response.
     */
    private function calculateChunkSize(int $remaining): int
    {
        return $remaining > 0
            ? min($this->bufferLength, $remaining)
            : $this->bufferLength;
    }

    /**
     * Determines if the response body should be considered empty.
     */
    private function isResponseEmpty(ResponseInterface $response): bool
    {
        $statusCode = $response->getStatusCode();

        if (in_array($statusCode, self::STATUS_CODES_WITHOUT_BODY, true)) {
            return true;
        }

        $body = $response->getBody();

        if ($body->isSeekable()) {
            $body->rewind();
        }

        return $body->eof() || $body->read(1) === '';
    }
}
