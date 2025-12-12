<?php

declare(strict_types=1);

namespace Zenigata\Http\Error;

use Throwable;

use function sprintf;

/**
 * Formats errors into a plain text representation.
 */
final class TextFormatter implements FormatterInterface
{
    /**
     * @inheritDoc
     */
    public function contentTypes(): array
    {
        return [
            'text/plain',
        ];
    }

    /**
     * @inheritDoc
     */
    public function format(Throwable $error, bool $debug): string
    {
        if ($debug === false) {
            return sprintf("Message: %s\n", $error->getMessage());
        }

        return sprintf(
            "Message: %s\nType: %s\nCode: %s\nFile: %s\nLine: %d\n\nTrace:\n%s\n",
            $error->getMessage(),
            $error::class,
            $error->getCode(),
            $error->getFile(),
            $error->getLine(),
            $error->getTraceAsString()
        );
    }
}
