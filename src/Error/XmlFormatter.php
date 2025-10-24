<?php

declare(strict_types=1);

namespace Zenigata\Http\Error;

use Throwable;

use const ENT_QUOTES;
use const ENT_XML1;

use function htmlspecialchars;
use function implode;

/**
 * Formats errors into a simple XML representation.
 *
 * Produces an XML document describing the error message and code.
 */
final class XmlFormatter implements FormatterInterface
{
    /**
     * {@inheritDoc}
     */
    public function contentTypes(): array
    {
        return ['text/xml', 'application/xml', 'application/x-xml'];  
    }

    /**
     * {@inheritDoc}
     */
    public function format(Throwable $error, bool $debug): string
    {
        $message = $this->escape($error->getMessage());
        $details = $debug === true ? $this->createDetails($error) : '';

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<error>
    <message>{$message}</message>
    {$details}
</error>
XML;
    }

    /**
     * Generates the XML markup for error details.
     */
    private function createDetails(Throwable $error): string
    {
        $details = [
            '<type>%s</type>',
            '<file>%s</file>',
            '<line>%d</line>',
            '<trace>%s</trace>',
        ];

        return sprintf(
            implode("\n", $details),
            $this->escape($error::class),
            $this->escape($error->getFile()),
            $error->getLine(),
            $this->escape($error->getTraceAsString())
        );
    }

    /**
     * Encodes text for safe inclusion in XML nodes.
     */
    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1);
    }
}
