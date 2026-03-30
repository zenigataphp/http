<?php

declare(strict_types=1);

namespace Zenigata\Http\Handling\Strategy;

use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

use function json_encode;

/**
 * JSON response strategy.
 *
 * Creates a JSON response from the result, if the content type is supported.
 */
class JsonResponseStrategy extends AbstractResponseStrategy
{
    /**
     * @inheritDoc
     */
    protected string $name = 'json';

    /**
     * @inheritDoc
     */
    protected array $contentTypes = [
        'application/json',
    ];

    /**
     * @inheritDoc
     *
     * @throws JsonException If enconding fails.
     */
    public function respond(ServerRequestInterface $request, mixed $data): ResponseInterface
    {
        $json = json_encode(
            $data,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        $response = $this->getResponseFactory()->createResponse($this->status);

        return $response
            ->withHeader('Content-Type', "application/json; charset={$this->charset}")
            ->withBody($this->getStreamFactory()->createStream($json));
    }
}