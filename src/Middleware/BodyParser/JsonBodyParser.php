<?php

declare(strict_types=1);

namespace Zenigata\Http\Middleware\BodyParser;

use JsonException;
use Psr\Http\Message\StreamInterface;

use const JSON_THROW_ON_ERROR;

use function json_decode;
use function trim;

/**
 * JSON body parser.
 * 
 * Parses the request body as JSON and returns the result.
 */
class JsonBodyParser extends AbstractBodyParser
{
    /**
     * @inheritDoc
     */
    protected array $contentTypes = [
        'application/json',
    ];

    /**
     * Creates a new JSON body parser instance.
     *
     * @param bool $associative Determines if decoded objects should be converted into associative arrays.
     * @param int  $depth       Specifies the decoding recursion depth.
     * @param int  $flags       JSON decode options.
     */
    public function __construct(
        private bool $associative = true,
        private int $depth = 512,
        private int $flags = 0,
    ) {}

    /**
     * @inheritDoc
     * 
     * @throws JsonException If JSON decoding fails.
     */
    public function parse(StreamInterface $body): mixed
    {
        $json = trim((string) $body);

        if ($json === '') {
            return $this->associative === true
                ? []
                : null;
        }

        return json_decode(
            $json,
            $this->associative,
            $this->depth,
            $this->flags | JSON_THROW_ON_ERROR
        );
    }
}