<?php

declare(strict_types=1);

namespace Zenigata\Http\Test;

use Throwable;
use Zenigata\Http\Error\FormatterInterface;

/**
 * Fake implementation of {@see Zenigata\Http\ErrorInterface}.
 *
 * Allows injecting custom content types and a formatter callback.
 */
final class FakeFormatter implements FormatterInterface
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
    public function __construct(
        private array $types,
        callable $format
    ) {
        $this->format = $format;
    }

    /**
     * @inheritDoc
     */
    public function contentTypes(): array
    {
        return $this->types;
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