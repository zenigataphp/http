<?php

declare(strict_types=1);

namespace Zenigata\Http\Error\Strategy;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

use function explode;
use function json_encode;

/**
 * JSON error strategy.
 *
 * Creates a JSON response from the error, if the content type is supported.
 */
class JsonErrorStrategy extends AbstractErrorStrategy
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
     */
    public function respond(ServerRequestInterface $request, Throwable $error): ResponseInterface
    {
        $data = [
            'error' => $this->serialize($error)
        ];
        
        $json = json_encode(
            $data,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        $response = $this->getResponseFactory()->createResponse($this->resolveStatus($error));

        return $response
            ->withHeader('Content-Type', "application/json; charset={$this->charset}")
            ->withBody($this->getStreamFactory()->createStream($json));
    }

    /**
     * Converts the error to array.
     */
    protected function serialize(Throwable $error): array
    {
        $message = $this->resolveMessage($error);

        if ($this->debug === false) {
            return ['message' => $message];
        }

        return [
            'message' => $message,
            'type'    => $error::class,
            'code'    => $error->getCode(),
            'file'    => $error->getFile(),
            'line'    => $error->getLine(),
            'trace'   => explode("\n", $error->getTraceAsString()),
        ];
    }
}