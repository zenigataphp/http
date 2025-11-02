<?php

declare(strict_types=1);

namespace Zenigata\Http\Test;

use Throwable;
use Zenigata\Http\Error\Formatter\AbstractFormatter;

/**
 * Fake implementation of {@see Zenigata\Http\Error\Formatter\FormatterInterface}.
 *
 * Allows injecting custom content types and a formatter callback.
 */
final class FakeFormatter extends AbstractFormatter
{
    /**
     * User-provided callback used to generate formatted output.
     *
     * @var callable(Throwable, bool):string
     */
    private $format;

    /**
     * Creates a new fake formatter instance.
     *
     * @param string[]                         $types  List of supported MIME types (e.g. `['application/json']`).
     * @param callable(Throwable, bool):string $format Callback used to produce the serialized error output.
     */
    public function __construct(array $types, callable $format)
    {
        $this->contentTypes = $types;
        $this->format = $format;
    }

    /**
     * @inheritDoc
     * 
     * Executes the injected format callback to generate the error body.
     */
    public function format(Throwable $error, bool $debug): string
    {
        return ($this->format)($error, $debug);
    }
}