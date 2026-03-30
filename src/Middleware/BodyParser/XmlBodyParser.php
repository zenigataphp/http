<?php

declare(strict_types=1);

namespace Zenigata\Http\Middleware\BodyParser;

use LibXMLError;
use Psr\Http\Message\StreamInterface;
use RuntimeException;
use SimpleXMLElement;
use Throwable;

use function array_map;
use function implode;
use function libxml_clear_errors;
use function libxml_get_errors;
use function libxml_use_internal_errors;
use function trim;

/**
 * XML body parser.
 * 
 * Parses the request body as XML and returns the result.
 */
class XmlBodyParser extends AbstractBodyParser
{
    /**
     * @inheritDoc
     */
    protected array $contentTypes = [
        'application/xml',
        'text/xml',
    ];

    /**
     * @inheritDoc
     * 
     * @return SimpleXMLElement|null The parsed body.
     * @throws RuntimeException If the XML string is malformed or cannot be parsed.
     */
    public function parse(StreamInterface $body): mixed
    {
        $xml = trim((string) $body);

        if ($xml === '') {
            return null;
        }

        $previous = libxml_use_internal_errors(true);

        try {
            return new SimpleXMLElement($xml);
        } catch (Throwable $e) {
            $errors  = libxml_get_errors();

            $message = $errors !== []
                ? $this->formatXmlErrors($errors)
                : $e->getMessage();

            throw new RuntimeException("Malformed XML: {$message}", 0, $e);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    /**
     * Formats a list of XML errors into a single human-readable string.
     *
     * @param list<LibXMLError> $errors
     */
    private function formatXmlErrors(array $errors): string
    {
        $messages = array_map(
            fn($e) => trim($e->message),
            $errors
        );

        return implode('; ', $messages);
    }
}