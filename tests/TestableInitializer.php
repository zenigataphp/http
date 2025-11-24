<?php

declare(strict_types=1);

namespace Zenigata\Http\Test;

use Zenigata\Http\Initializer\Initializer;

/**
 * Test double for {@see Zenigata\Http\Initializer\Initializer}.
 * 
 * Simulates file upload behavior for predictable request handling.
 */
final class TestableInitializer extends Initializer
{
    /**
     * @inheritDoc
     * 
     * Overridden for controlled testing behavior.
     */
    protected function isUploadedFile(string $filename): bool
    {
        return true;
    }
}