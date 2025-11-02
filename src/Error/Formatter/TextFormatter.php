<?php

declare(strict_types=1);

namespace Zenigata\Http\Error\Formatter;

use Throwable;

use function sprintf;

/**
 * Formats errors into a plain text representation.
 *
 * Provides a concise text output suitable for CLI or plain HTTP responses.
 */
final class TextFormatter extends AbstractFormatter
{
    /**
     * @inheritDoc
     */
    protected array $contentTypes = [
        'text/plain',
    ];

    /**
     * @inheritDoc
     */
    public function format(Throwable $error, bool $debug): string
    {
        if ($debug === false) {
            return sprintf("Message: %s\n", $error->getMessage());
        }

        return sprintf(
            "Message: %s\nType: %s\nFile: %s\nLine: %d\n\nTrace:\n%s\n",
            $error->getMessage(),
            $error::class,
            $error->getFile(),
            $error->getLine(),
            $error->getTraceAsString()
        );
    }
}
