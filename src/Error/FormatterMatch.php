<?php

declare(strict_types=1);

namespace Zenigata\Http\Error;

/**
 * Represents the result of a formatter negotiation process.
 */
final class FormatterMatch
{
    /**
     * @param FormatterInterface $formatter   The formatter selected for the current request.
     * @param string             $contentType The content type that triggered the match.
     */
    public function __construct(
        public readonly FormatterInterface $formatter,
        public readonly string $contentType,
    ) {}
}