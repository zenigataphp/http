<?php

declare(strict_types=1);

namespace Zenigata\Http\Error\Strategy;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

use function sprintf;

/**
 * Plain ext error strategy.
 *
 * Creates a text response from the error, if the content type is supported.
 */
class TextErrorStrategy extends AbstractErrorStrategy
{
    /**
     * @inheritDoc
     */
    protected string $name = 'text';

    /**
     * @inheritDoc
     */
    protected array $contentTypes = [
        'text/plain',
    ];

    /**
     * @inheritDoc
     */
    public function respond(ServerRequestInterface $request, Throwable $error): ResponseInterface
    {
        $text = $this->serialize($error);
        
        $response = $this->getResponseFactory()->createResponse($this->resolveStatus($error));

        return $response
            ->withHeader('Content-Type', "text/plain; charset={$this->charset}")
            ->withBody($this->getStreamFactory()->createStream($text));
    }

    /**
     * Converts the error to string.
     */
    protected function serialize(Throwable $error): string
    {
        $message = $this->resolveMessage($error);

        if ($this->debug === false) {
            return sprintf("Message: %s", $message);
        }

        return sprintf(
            "Message: %s\nType: %s\nCode: %s\nFile: %s\nLine: %d\nTrace: %s",
            $message,
            $error::class,
            $error->getCode(),
            $error->getFile(),
            $error->getLine(),
            $error->getTraceAsString()
        );
    }
}