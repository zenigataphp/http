<?php

declare(strict_types=1);

namespace Zenigata\Http\Test;

use Throwable;
use Zenigata\Http\Error\AbstractFormatter;

/**
 * // TODO
 */
final class FakeFormatter extends AbstractFormatter
{
    /**
     * // TODO
     *
     * @var callable(Throwable, bool):string
     */
    private $format;

    /**
     * Creates a new fake formatter instance.
     *
     * @param string[]                         $types  // TODO documentare brevemente
     * @param callable(Throwable, bool):string $format // TODO documentare brevemente
     */
    public function __construct(array $types, callable $format)
    {
        $this->contentTypes = $types;
        $this->format = $format;
    }

    /**
     * {@inheritDoc}
     * 
     * Internally executes the provided format callback. // TODO corretto?
     */
    public function format(Throwable $error, bool $debug): string
    {
        return ($this->format)($error, $debug);
    }
}