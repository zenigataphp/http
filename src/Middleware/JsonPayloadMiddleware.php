<?php

declare(strict_types=1);

namespace Zenigata\Http\Middleware;

use Throwable;
use Middlewares\JsonPayload;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zenigata\Http\Error\HttpError;

/**
 * Middleware for parsing JSON request bodies.
 *
 * Parses JSON request bodies into an array or object for supported methods.
 * Delegates the actual parsing to {@see Middlewares\JsonPayload}.
 */
class JsonPayloadMiddleware implements MiddlewareInterface
{
    /**
     * Internal middleware: https://github.com/middlewares/payload?tab=readme-ov-file#jsonpayload
     *
     * @var JsonPayload
     */
    private JsonPayload $middleware;

    /**
     * Creates a new json payload middleware instance.
     *
     * @param string[] $contentType
     * @param string[] $methods
     * @param bool     $override
     * @param bool     $associative
     * @param int      $depth
     * @param int      $options
     */
    public function __construct(
        array $contentType = ['application/json'],
        array $methods     = ['POST', 'PUT', 'PATCH', 'DELETE', 'COPY', 'LOCK', 'UNLOCK'],
        bool  $override    = false,
        bool  $associative = true,
        int   $depth       = 512,
        int   $options     = 0,
    ) { 
        $this->middleware = new JsonPayload();

        $this->middleware->contentType($contentType);
        $this->middleware->methods($methods);
        $this->middleware->override($override);
        $this->middleware->associative($associative);
        $this->middleware->depth($depth);
        $this->middleware->options($options);
    }

    /**
     * {@inheritDoc}
     *
     * Delegates processing to internal middleware.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $this->middleware->process($request, $handler);
        } catch (Throwable $e) { 
            throw new HttpError(
                request:  $request,
                code:     400,
                message:  $e->getMessage(),
                previous: $e->getPrevious()
            );
        }
    }
}