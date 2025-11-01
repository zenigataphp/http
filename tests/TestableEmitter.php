<?php

declare(strict_types=1);

namespace Zenigata\Http\Test;

use Zenigata\Http\Response\Emitter;

/**
 * // TODO
 */
final class TestableEmitter extends Emitter
{
    /**
     * // TODO documentare property e type
     *
     * @var array
     */
    private array $sentHeaders = [];

    /**
     * Creates a new testable emitter instance.
     *
     * @param int  $bufferLength     The maximum number of bytes to read and emit per iteration.
     * @param bool $headersSent      // TODO documentare brevemente
     * @param bool $connectionNormal // TODO documentare brevemente
     */
    public function __construct(
        int $bufferLength = 8192,
        private bool $headersSent = false,        
        private bool $connectionNormal = true,
    ) {
        parent::__construct($bufferLength);
    }

    /**
     * Returns ... // TODO documentare method, return type
     *
     * @return array
     */
    public function getSentHeaders(): array
    {
        return $this->sentHeaders;
    }

    /**
     * {@inheritDoc}
     * 
     * Override for testing and control. // TODO corretto?
     */
    protected function headersSent(): bool
    {
        return $this->headersSent;
    }

    /**
     * {@inheritDoc}
     * 
     * Override for testing and control. // TODO corretto?
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
     * {@inheritDoc}
     * 
     * Override for testing and control. // TODO corretto?
     */
    protected function isConnectionNormal(): bool
    {
        return $this->connectionNormal;
    }
}