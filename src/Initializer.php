<?php

declare(strict_types=1);

namespace Zenigata\Http;

use Throwable;
use Middlewares\Utils\Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use Nyholm\Psr7Server\ServerRequestCreatorInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;

use function sprintf;

/**
 * Implementation of {@see InitializerInterface}.
 *
 * Prepares the initial server request required to begin request handling
 * and provides a fallback response if initialization fails.
 */
class Initializer implements InitializerInterface
{
    /**
     * Responsible for creating server requests from PHP globals.
     *
     * @var ServerRequestCreatorInterface
     */
    protected ServerRequestCreatorInterface $creator;

    /**
     * Factory used to generate PSR-7 responses.
     *
     * @var ResponseFactoryInterface
     */
    private ResponseFactoryInterface $responseFactory;

    /**
     * Enables detailed output in error responses.
     *
     * @var bool
     */
    private bool $debug;

    /**
     * Creates a new initializer instance.
     *
     * @param ServerRequestFactoryInterface|null $serverRequestFactory Optional server request factory.
     * @param StreamFactoryInterface|null        $streamFactory        Optional stream factory.
     * @param UploadedFileFactoryInterface|null  $uploadedFileFactory  Optional uploaded file factory.
     * @param UriFactoryInterface|null           $uriFactory           Optional URI factory.
     * @param ResponseFactoryInterface|null      $responseFactory      Optional response factory.
     * @param bool                               $debug                Enables detailed error output when true.
     */
    public function __construct(
        ?ServerRequestFactoryInterface $serverRequestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
        ?UploadedFileFactoryInterface $uploadedFileFactory = null,
        ?UriFactoryInterface $uriFactory = null,
        ?ResponseFactoryInterface $responseFactory = null,
        bool $debug = false,
    ) {
        $this->creator = new ServerRequestCreator(
            $serverRequestFactory ?? Factory::getServerRequestFactory(),
            $uriFactory ?? Factory::getUriFactory(),
            $uploadedFileFactory ?? Factory::getUploadedFileFactory(),
            $streamFactory ?? Factory::getStreamFactory(),
        );
        
        $this->responseFactory = $responseFactory ?? Factory::getResponseFactory();
        $this->debug = $debug;
    }

    /**
     * {@inheritDoc}
     * 
     * Internally relies on `nyholm/psr7-server` to build the server request.
     * See https://github.com/Nyholm/psr7-server
     */
    public function createServerRequest(): ServerRequestInterface
    {
        return $this->creator->fromGlobals();
    }

    /**
     * {@inheritDoc}
     * 
     * Produces a `text/plain` response when initialization fails.
     * Includes exception details when debug mode is enabled.
     */
    public function createErrorResponse(Throwable $error): ResponseInterface
    {
        $response = $this->responseFactory->createResponse(500);
        $response = $response->withHeader('Content-Type', 'text/plain; charset=utf-8');

        $body = $this->debug === true
            ? sprintf(
                "Exception: %s\nMessage: %s\nFile: %s:%d\n\nStack trace:\n%s",
                $error::class,
                $error->getMessage(),
                $error->getFile(),
                $error->getLine(),
                $error->getTraceAsString()
            )
            : 'Internal Server Error';

        $response->getBody()->write($body);

        return $response;
    }
}