<?php

declare(strict_types=1);

namespace Zenigata\Http\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;
use Zenigata\Http\Error\HttpError;
use Zenigata\Http\Middleware\BodyParser\JsonBodyParser;
use Zenigata\Http\Middleware\BodyParser\UrlEncodedBodyParser;
use Zenigata\Http\Middleware\BodyParser\XmlBodyParser;

/**
 * Middleware for parsing the request body.
 *
 * Detects the best parser based on the content type header.
 * 
 * If no parser is configured, the defaults will be used:
 * JSON, XML, URL encoded.
 */
class BodyParserMiddleware implements MiddlewareInterface
{
    /**
     * List of registered body parsers.
     *
     * @var list<BodyParserInterface> $parsers
     */
    private array $parsers = [];

    /**
     * Creates a new middleware instance.
     *
     * @param list<BodyParserInterface> $parsers List of body parsers.
     */
    public function __construct(array $parsers = [])
    {
        if ($parsers === []) {
            $parsers = self::defaultParsers();
        }

        foreach ($parsers as $parser) {
            $this->addParser($parser);
        }
    }

    /**
     * @inheritDoc
     *
     * Parses the request body if it is not empty and does not already have a parsed body.
     * 
     * @throws HttpError If parsing fails, will be throw an HTTP error with status 400.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->canParseBody($request)) {
            return $handler->handle($request);
        }

        $contentType = $request->getHeaderLine('Content-Type');

        if ($contentType !== '') {
            try {
                $request = $this->parseBody($request, $contentType);
            } catch (Throwable $error) {
                throw new HttpError($request, 400, $error->getMessage(), $error);
            }
        }

        return $handler->handle($request);
    }

    /**
     * Adds a body parser.
     *
     * @param BodyParserInterface $parser The body parser.
     */
    public function addParser(BodyParserInterface $parser): void
    {
        $this->parsers[] = $parser;
    }

    /**
     * Returns the registered body parsers.
     *
     * @return list<BodyParserInterface>
     */
    public function getParsers(): array
    {
        return $this->parsers;
    }

    /**
     * Checks if the request already has a parsed body, or an empty body.
     */
    private function canParseBody(ServerRequestInterface $request): bool
    {
        return $request->getParsedBody() === null
            && $request->getBody()->getSize() !== 0;
    }

    /**
     * Parses the request body using a matching parser, if available.
     */
    private function parseBody(ServerRequestInterface $request, string $contentType): ServerRequestInterface
    {
        foreach ($this->parsers as $parser) {
            if ($parser->supports($contentType)) {
                $parsed = $parser->parse($request->getBody());

                return $request->withParsedBody($parsed);
            }
        }

        return $request;
    }

    /**
     * @return list<BodyParserInterface>
     */
    private static function defaultParsers(): array
    {
        return [
            new JsonBodyParser(),
            new XmlBodyParser(),
            new UrlEncodedBodyParser(),
        ];
    }
}