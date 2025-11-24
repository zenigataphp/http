<?php

declare(strict_types=1);

namespace Zenigata\Http\Handler;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Utility trait for building responses in various formats.
 *
 * Provides convenience methods for generating PSR-7 responses in common formats.
 * Uses {@see Zenigata\Http\Handler\ResponseBuilder} internally.
 */
trait ResponseBuilderTrait
{
    /**
     * Internal response builder instance.
     *
     * @var ResponseBuilder|null
     */
    private ?ResponseBuilder $responseBuilder = null;
    
    /**
     * Returns a lazy-initialized builder instance.
     */
    protected function responseBuilder(): ResponseBuilder
    {
        return $this->responseBuilder ??= new ResponseBuilder();
    }

    /**
     * @see Zenigata\Http\Handler\ResponseBuilder::createResponse()
     */
    protected function createResponse(int $status = 200, mixed $body = null, array $headers = []): ResponseInterface
    {
        return $this->responseBuilder()->createResponse($status, $body, $headers);
    }

    /**
     * @see Zenigata\Http\Handler\ResponseBuilder::createStream()
     */
    protected function createStream(mixed $body): StreamInterface
    {
        return $this->responseBuilder()->createStream($body);
    }

    /**
     * @see Zenigata\Http\Handler\ResponseBuilder::jsonResponse()
     */
    protected function jsonResponse(
        mixed $data,
        int $status    = 200,
        array $headers = [],
        int $flags     = 0,
        int $depth     = 512
    ): ResponseInterface {
        return $this->responseBuilder()->jsonResponse($data, $status, $headers, $flags, $depth);
    }

    /**
     * @see Zenigata\Http\Handler\ResponseBuilder::htmlResponse()
     */
    protected function htmlResponse(string $html, int $status = 200, array $headers = []): ResponseInterface
    {
        return $this->responseBuilder()->htmlResponse($html, $status, $headers);
    }

    /** 
     * @see Zenigata\Http\Handler\ResponseBuilder::textResponse()
     */
    protected function textResponse(string $text, int $status = 200, array $headers = []): ResponseInterface
    {
        return $this->responseBuilder()->textResponse($text, $status, $headers);
    }

    /** 
     * @see Zenigata\Http\Handler\ResponseBuilder::xmlResponse()
     */
    protected function xmlResponse(string $xml, int $status = 200, array $headers = []): ResponseInterface
    {
        return $this->responseBuilder()->xmlResponse($xml, $status, $headers);
    }

    /**
     * @see Zenigata\Http\Handler\ResponseBuilder::redirectResponse()
     */
    protected function redirectResponse(string $location, int $status = 302, array $headers = []): ResponseInterface
    {
        return $this->responseBuilder()->redirectResponse($location, $status, $headers);
    }

    /**
     * @see Zenigata\Http\Handler\ResponseBuilder::emptyResponse()
     */
    protected function emptyResponse(int $status = 204, array $headers = []): ResponseInterface
    {
        return $this->responseBuilder()->emptyResponse($status, $headers);
    }

    /**
     * @see Zenigata\Http\Handler\ResponseBuilder::fileResponse()
     */
    protected function fileResponse(
        string $path,
        ?string $filename   = null,
        string $disposition = 'attachment',
        int $status         = 200,
        array $headers      = []
    ): ResponseInterface {
        return $this->responseBuilder()->fileResponse($path, $filename, $disposition, $status, $headers);
    }
}
