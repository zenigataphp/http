<?php

declare(strict_types=1);

namespace Zenigata\Http\Test;

use Psr\Http\Message\StreamInterface;
use RuntimeException;
use Zenigata\Http\Middleware\BodyParserInterface;

/**
 * Fake implementation of {@see Zenigata\Http\Middleware\BodyParserInterface}.
 * 
 * A configurable body parser that supports a single content type and either
 * returns a preset result or throws on parse().
 */
final class FakeBodyParser implements BodyParserInterface
{
    public function __construct(
        private string $contentType,
        private mixed $result = null,
        private bool $throws = false,
    ) {}

    public function supports(string $contentType): bool
    {
        return $contentType === $this->contentType;
    }

    public function parse(StreamInterface $body): mixed
    {
        if ($this->throws) {
            throw new RuntimeException('Parse error.');
        }

        return $this->result;
    }
}