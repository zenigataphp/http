<?php

declare(strict_types=1);

namespace Zenigata\Http\Handling\Strategy;

/**
 * Represents an HTTP redirect.
 */
final class HttpRedirect
{
    /**
     * Creates a new HTTP redirect instance.
     *
     * @param string                        $location Target URL.
     * @param int                           $status   Redirect status code (301, 302, 303, 307, 308).
     * @param array<string,string|string[]> $headers  Additional headers.
     */
    public function __construct(
        public readonly string $location,
        public readonly int $status = 302,
        public readonly array $headers = []
    ) {}
}