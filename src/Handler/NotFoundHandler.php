<?php

declare(strict_types=1);

namespace Zenigata\Http\Handler;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

use const JSON_PRETTY_PRINT;

use function str_contains;
use function strtolower;

/**
 * Default PSR-15 request handler used as a fallback when 
 * no middleware is processing the incoming request.
 */
class NotFoundHandler implements RequestHandlerInterface
{
    use ResponseBuilderTrait;

    /**
     * The message used in responses.
     * 
     * @var string
     */
    private const NOT_FOUND_MESSAGE = 'Not Found';

    /**
     * {@inheritDoc}
     *
     * Negotiates the response format from the `Accept` header.
     * Supports JSON, plain text and HTML.
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $contentType = $this->detectContentType($request);
        
        $response = match ($contentType) {
            'application/json' => $this->buildJsonResponse(),
            'text/plain'       => $this->buildTextResponse(),
            default            => $this->buildHtmlResponse()
        };

        return $response->withStatus(404);
    }

    /**
     * Determines the content type to return based on the `Accept` header.
     *
     * Defaults to `text/html` if no supported type is requested.
     *
     * @param ServerRequestInterface $request
     * 
     * @return string One of `application/json`, `text/plain`, or `text/html`.
     */
    private function detectContentType(ServerRequestInterface $request): string
    {
        $acceptHeader = strtolower($request->getHeaderLine('Accept'));

        return match (true) {
            str_contains($acceptHeader, 'application/json') => 'application/json',
            str_contains($acceptHeader, 'text/plain')       => 'text/plain',
            default                                         => 'text/html'
        };
    }

    /**
     * Builds a JSON response containing the message.
     *
     * @return ResponseInterface JSON response.
     */
    private function buildJsonResponse(): ResponseInterface
    {
        return $this->jsonResponse(
            data:  ['message' => self::NOT_FOUND_MESSAGE],
            flags: JSON_PRETTY_PRINT
        );
    }

    /**
     * Builds a plain text response containing the message.
     *
     * @return ResponseInterface Text response.
     */
    private function buildTextResponse(): ResponseInterface
    {
        return $this->textResponse(self::NOT_FOUND_MESSAGE);
    }

    /**
     * Builds an HTML response containing the message.
     *
     * @return ResponseInterface HTML response.
     */
    private function buildHtmlResponse(): ResponseInterface
    {
        $message = self::NOT_FOUND_MESSAGE;

        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>$message</title>
    <style>html{font-family: sans-serif;}</style>
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
    <h1>$message</h1>
</body>
</html>
HTML;

        return $this->htmlResponse($html);
    }
}