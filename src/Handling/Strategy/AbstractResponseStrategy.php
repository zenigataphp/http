<?php

declare(strict_types=1);

namespace Zenigata\Http\Handling\Strategy;

use LogicException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zenigata\Http\Handling\ResponseStrategyInterface;
use Zenigata\Utility\Awareness\ResponseFactoryAwareInterface;
use Zenigata\Utility\Awareness\ResponseFactoryAwareTrait;
use Zenigata\Utility\Awareness\StreamFactoryAwareInterface;
use Zenigata\Utility\Awareness\StreamFactoryAwareTrait;

use function sprintf;
use function str_contains;

/**
 * Base implementation of {@see Zenigata\Http\Handling\ResponseStrategyInterface}.
 * 
 * Provides common functionality for response creation.
 * Supports detection based on the "Accept" header.
 */
abstract class AbstractResponseStrategy implements ResponseStrategyInterface, ResponseFactoryAwareInterface, StreamFactoryAwareInterface
{
    use ResponseFactoryAwareTrait;
    use StreamFactoryAwareTrait;

    /**
     * The strategy name.
     */
    protected string $name = '';

    /**
     * List of content types supported by this strategy.
     *
     * @var list<string>
     */
    protected array $contentTypes = [];

    /**
     * Character set used in Content-Type header.
     */
    protected string $charset = 'utf-8';

    /**
     * Default HTTP status code.
     */
    protected int $status = 200;

    /**
     * @inheritDoc
     * 
     * @throws LogicException If the strategy name is not set.
     */
    public function getName(): string
    {
        if ($this->name === '') {
            throw new LogicException(sprintf(
                "Response strategy '%s' must define a name.",
                static::class
            ));
        }

        return $this->name;
    }

    /**
     * @inheritDoc
     * 
     * Checks if this strategy supports the given request based on the Accept header.
     */
    public function supports(ServerRequestInterface $request, mixed $data): bool
    {
        $accept = $request->getHeaderLine('Accept');

        if ($accept === '') {
            return false;
        }

        foreach ($this->contentTypes as $contentType) {
            if (str_contains($accept, $contentType)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    abstract public function respond(ServerRequestInterface $request, mixed $data): ResponseInterface;
}