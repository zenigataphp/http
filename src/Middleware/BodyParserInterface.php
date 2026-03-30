<?php

declare(strict_types=1);

namespace Zenigata\Http\Middleware;

use Psr\Http\Message\StreamInterface;

/**
 * Defines a contract for a request body parser.
 * 
 * Parses the request body, if the content type is supported.
 */
interface BodyParserInterface
{
    /**
     * Indicates if the parser support the specified content type.
     *
     * @param string $contentType The content type.
     * 
     * @return bool True if the parser supports the content type.
     */
    public function supports(string $contentType): bool;

    /**
     * Returns the parsed body.
     *
     * @param StreamInterface $body The request body to parse.
     * 
     * @return mixed The parsed body.
     */
    public function parse(StreamInterface $body): mixed;
}