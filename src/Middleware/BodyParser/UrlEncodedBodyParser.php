<?php

declare(strict_types=1);

namespace Zenigata\Http\Middleware\BodyParser;

use Psr\Http\Message\StreamInterface;
use RuntimeException;

use function parse_str;
use function trim;

/**
 * URL encoded body parser.
 * 
 * Parses the request body as URL encoded data and returns the result.
 */
class UrlEncodedBodyParser extends AbstractBodyParser
{
    /**
     * @inheritDoc
     */
    protected array $contentTypes = [
        'application/x-www-form-urlencoded',
    ];

    /**
     * @inheritDoc
     * 
     * @return array The parsed body.
     * @throws RuntimeException If the url encoded string is invalid.
     */
    public function parse(StreamInterface $body): mixed
    {
        $urlencoded = trim((string) $body);

        parse_str($urlencoded, $data);

        if ($urlencoded !== '' && $data === []) {
            throw new RuntimeException('Invalid URL encoded string.');
        }

        return $data;
    }
}