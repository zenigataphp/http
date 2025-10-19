<?php

declare(strict_types=1);

namespace Zenigata\Http\Test;

use Throwable;
use Psr\Http\Message\ServerRequestInterface;
use Zenigata\Http\Initializer;
use Zenigata\Testing\Http\FakeHttpFactory;

/**
 * Test-specific implementation of {@see Initializer}.
 * 
 * The contructor accepts custom values representing PHP superglobals,
 * allowing to simulate different HTTP environments during testing.
 */
final class TestableInitializer extends Initializer
{
    /**
     * Creates a new testable initializer instance.
     *
     * @param array                                $server    Simulated $_SERVER parameters.
     * @param array                                $headers   Simulated request headers.
     * @param array                                $cookie    Simulated $_COOKIE values.
     * @param array                                $get       Simulated $_GET values.
     * @param array                                $post      Simulated $_POST values.
     * @param array                                $files     Simulated $_FILES values.
     * @param StreamInterface|resource|string|null $body      Optional request body content.
     * @param Throwable|null                       $exception Optional exception to throw during the creation.
     * @param bool                                 $debug     Enables detailed error responses when true.
     */
    public function __construct(
        private array $server          = [],
        private array $headers         = [],
        private array $cookie          = [],
        private array $get             = [],
        private array $post            = [],
        private array $files           = [],
        private mixed $body            = null,
        private ?Throwable $exception  = null,
        bool $debug                    = false,
    ) {
        $httpFactory = new FakeHttpFactory();

        parent::__construct(
            serverRequestFactory: $httpFactory,
            streamFactory:        $httpFactory,
            uploadedFileFactory:  $httpFactory,
            uriFactory:           $httpFactory,
            responseFactory:      $httpFactory,
            debug:                $debug
        );
    }

    /**
     * {@inheritDoc}
     *
     * Overrides to creating PSR-7 server requests
     * without relying on PHP superglobals.
     * 
     * @throws Throwable If an exception was configured in the constructor.
     */
    public function createServerRequest(): ServerRequestInterface
    {
        if ($this->exception !== null) {
            throw $this->exception;
        }

        return $this->creator->fromArrays(
            server:  $this->server,
            headers: $this->headers,
            cookie:  $this->cookie,
            get:     $this->get,
            post:    $this->post,
            files:   $this->files,
            body:    $this->body
        );
    }
}