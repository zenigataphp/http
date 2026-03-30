<?php

declare(strict_types=1);

namespace Zenigata\Http\Test;

use Zenigata\Http\Runtime\RequestInitializer;

/**
 * Testable implementation of {@see Zenigata\Http\Runtime\RequestInitializer}.
 * 
 * Simulates file upload behavior for predictable request handling.
 */
final class TestableRequestInitializer extends RequestInitializer
{
    /**
     * Overridden for controlled testing behavior.
     */
    protected function isUploadedFile(string $filename): bool
    {
        return true;
    }
}