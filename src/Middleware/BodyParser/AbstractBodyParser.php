<?php

declare(strict_types=1);

namespace Zenigata\Http\Middleware\BodyParser;

use Psr\Http\Message\StreamInterface;
use Zenigata\Http\Middleware\BodyParserInterface;

use function in_array;

/**
 * Base implementation of {@see Zenigata\Http\Middleware\BodyParserInterface}.
 */
abstract class AbstractBodyParser implements BodyParserInterface
{
    /**
     * List of supported content types.
     *
     * @var list<string>
     */
    protected array $contentTypes = [];

    /**
     * @inheritDoc
     * 
     * Checks if the content type is in the supported list.
     */
    public function supports(string $contentType): bool
    {
        return in_array($contentType, $this->contentTypes, true);
    }
    
    /**
     * @inheritDoc
     */
    abstract public function parse(StreamInterface $body): mixed;
}