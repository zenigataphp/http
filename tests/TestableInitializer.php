<?php

declare(strict_types=1);

namespace Zenigata\Http\Test;

use Zenigata\Http\Request\Initializer;

/**
 * Test double for {@see Zenigata\Http\Request\Initializer}.
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