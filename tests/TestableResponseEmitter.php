<?php

declare(strict_types=1);

namespace Zenigata\Http\Test;

use Zenigata\Http\Runtime\ResponseEmitter;

/**
 * Testable implementation of {@see Zenigata\Http\Runtime\ResponseEmitter}.
 * 
 * Records emitted headers and simulates environment state.
 */
final class TestableResponseEmitter extends ResponseEmitter
{
    /**
     * Collection of headers emitted during test execution.
     *
     * @var array<int,array{header:string,replace:bool,code:int}>
     */
    private array $sentHeaders = [];

    /**
     * Creates a new testable response emitter instance.
     *
     * @param int  $bufferLength     The maximum number of bytes to read and emit per iteration.
     * @param bool $headersSent      Whether to simulate the condition of headers already being sent.
     * @param bool $connectionNormal Whether to simulate an active client connection.
     */
    public function __construct(
        int $bufferLength = 8192,
        private bool $headersSent = false,        
        private bool $connectionNormal = true,
    ) {
        parent::__construct($bufferLength);
    }

    /**
     * Returns the headers captured during emission.
     *
     * @return array<int,array{header:string,replace:bool,code:int}>
     */
    public function getSentHeaders(): array
    {
        return $this->sentHeaders;
    }

    /**
     * Overridden for controlled testing behavior.
     */
    protected function headersSent(): bool
    {
        return $this->headersSent;
    }

    /**
     * Overridden for controlled testing behavior.
     */
    protected function sendHeader(string $header, bool $replace, int $code = 0): void
    {
        $this->sentHeaders[] = [
            'header'  => $header,
            'replace' => $replace,
            'code'    => $code,
        ];
    }

    /**
     * Overridden for controlled testing behavior.
     */
    protected function isConnectionNormal(): bool
    {
        return $this->connectionNormal;
    }
}