<?php

declare(strict_types=1);

namespace Zenigata\Http\Handling\Strategy;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Stringable;

use function is_scalar;
use function is_string;
use function print_r;

/**
 * Plain text response strategy.
 *
 * Creates a text response from the result, if the content type is supported.
 */
class TextResponseStrategy extends AbstractResponseStrategy
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
    public function respond(ServerRequestInterface $request, mixed $data): ResponseInterface
    {
        $text = match (true) {
            is_string($data)            => $data,
            $data instanceof Stringable => (string) $data,
            is_scalar($data)            => (string) $data,
            default                     => print_r($data, true),
        };

        $response = $this->getResponseFactory()->createResponse($this->status);

        return $response
            ->withHeader('Content-Type', "text/plain; charset={$this->charset}")
            ->withBody($this->getStreamFactory()->createStream($text));
    }
}