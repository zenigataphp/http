<?php

declare(strict_types=1);

namespace Zenigata\Http\Error;

use Throwable;

/**
 * Base class for {@see FormatterInterface} implementations.
 *
 * It provides a standard framework for formatters
 * to define the supported content types.
 */
abstract class AbstractFormatter implements FormatterInterface
{
    /**
     * Content types supported by this formatter.
     *
     * @var string[]
     */
    protected array $contentTypes = [];

    /**
     * {@inheritDoc}
     */
    abstract public function format(Throwable $error, bool $debug): string;

    /**
     * {@inheritDoc}
     */
    public function getContentTypes(): array
    {
        return $this->contentTypes;
    }
}