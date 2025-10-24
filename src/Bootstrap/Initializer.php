<?php

declare(strict_types=1);

namespace Zenigata\Http\Bootstrap;

use Middlewares\Utils\Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use Nyholm\Psr7Server\ServerRequestCreatorInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;

/**
 * Implementation of {@see InitializerInterface}.
 *
 * Prepares the initial server request required to begin request handling
 * and provides a fallback response if initialization fails.
 * 
 * Internally relies on `nyholm/psr7-server` to build the server request.
 * See https://github.com/Nyholm/psr7-server
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
     * Creates a new initializer instance.
     *
     * @param ServerRequestFactoryInterface|null $serverRequestFactory Optional server request factory.
     * @param StreamFactoryInterface|null        $streamFactory        Optional stream factory.
     * @param UploadedFileFactoryInterface|null  $uploadedFileFactory  Optional uploaded file factory.
     * @param UriFactoryInterface|null           $uriFactory           Optional URI factory.
     */
    public function __construct(
        ?ServerRequestFactoryInterface $serverRequestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
        ?UploadedFileFactoryInterface $uploadedFileFactory = null,
        ?UriFactoryInterface $uriFactory = null
    ) {
        $this->creator = new ServerRequestCreator(
            $serverRequestFactory ?? Factory::getServerRequestFactory(),
            $uriFactory ?? Factory::getUriFactory(),
            $uploadedFileFactory ?? Factory::getUploadedFileFactory(),
            $streamFactory ?? Factory::getStreamFactory(),
        );
    }

    /**
     * {@inheritDoc}
     */
    public function fromGlobals(): ServerRequestInterface
    {
        return $this->creator->fromGlobals();
    }

    /**
     * {@inheritDoc}
     */
    public function fromArrays(
        array $server  = [],
        array $get     = [],
        array $post    = [],
        array $cookies = [],
        array $files   = [],
    ): ServerRequestInterface
    {
        return $this->creator->fromArrays($server, $get, $post, $cookies, $files);
    }
}