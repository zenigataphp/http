<?php

declare(strict_types=1);

namespace Zenigata\Http\Error\Formatter;

use Throwable;

/**
 * Defines a contract for converting an exception or error into a serialized
 * representation suitable for an HTTP response body.
 *
 * Implementations may define different output formats (e.g., JSON, XML, HTML),
 * and declare which `Content-Type` values they support.
 */
interface FormatterInterface
{
    /**
     * Converts the given {@see Throwable} into a formatted string representation.
     *
     * @param Throwable $error The error or exception to be formatted.
     * @param bool      $debug Indicates whether to include error details in the response.
     * 
     * @return string The formatted output, ready to be written to the response body.
     */
    public function format(Throwable $error, bool $debug): string;

    /**
     * Returns the list of the content types supported by this formatter.
     *
     * @return string[] Array of MIME types (e.g. `application/json`, `text/html`).
     */
    public function getContentTypes(): array;
}