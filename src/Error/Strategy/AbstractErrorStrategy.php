<?php

declare(strict_types=1);

namespace Zenigata\Http\Error\Strategy;

use LogicException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;
use Zenigata\Http\Error\ErrorStrategyInterface;
use Zenigata\Http\Error\HttpError;
use Zenigata\Utility\Awareness\DebugAwareInterface;
use Zenigata\Utility\Awareness\DebugAwareTrait;
use Zenigata\Utility\Awareness\ResponseFactoryAwareInterface;
use Zenigata\Utility\Awareness\ResponseFactoryAwareTrait;
use Zenigata\Utility\Awareness\StreamFactoryAwareInterface;
use Zenigata\Utility\Awareness\StreamFactoryAwareTrait;

use function str_contains;
use function sprintf;

/**
 * Base implementation of {@see Zenigata\Http\Error\ErrorStrategyInterface}.
 * 
 * Provides common functionality for response creation.
 * Supports detection based on the "Accept" header.
 */
abstract class AbstractErrorStrategy implements ErrorStrategyInterface, DebugAwareInterface, ResponseFactoryAwareInterface, StreamFactoryAwareInterface
{
    use DebugAwareTrait;
    use ResponseFactoryAwareTrait;
    use StreamFactoryAwareTrait;

    /**
     * The strategy name.
     */
    protected string $name = '';

    /**
     * List of content types supported by the strategy.
     *
     * @var list<string>
     */
    protected array $contentTypes = [];

    /**
     * Character set used in Content-Type header.
     */
    protected string $charset = 'utf-8';

    /**
     * Default error message.
     */
    protected string $message = 'An internal error occurred.';

    /**
     * Default HTTP status code.
     */
    protected int $status = 500;

    /**
     * @inheritDoc
     * 
     * @throws LogicException If the strategy name is not set.
     */
    public function getName(): string
    {
        if ($this->name === '') {
            throw new LogicException(sprintf(
                'Error strategy "%s" must define a name.',
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
    public function supports(ServerRequestInterface $request, Throwable $error): bool
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
    abstract public function respond(ServerRequestInterface $request, Throwable $error): ResponseInterface;

    /**
     * Returns the exception message if debug mode is enabled,
     * otherwise returns the default message.
     */
    public function resolveMessage(Throwable $error): string
    {
        return $this->debug === true
            ? $error->getMessage()
            : $this->message;
    }

    /**
     * Returns the exception code if it is a valid HTTP error code,
     * otherwise returns the default status code.
     */
    public function resolveStatus(Throwable $error): int
    {
        $code = $error->getCode();

        if ($error instanceof HttpError) {
            return $code;
        }

        if (isset(HttpError::HTTP_ERROR_CODES[$code])) {
            return $code;
        }

        return $this->status;
    }
}