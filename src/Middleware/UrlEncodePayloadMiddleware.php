<?php

declare(strict_types=1);

namespace Zenigata\Http\Middleware;

use Middlewares\UrlEncodePayload;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Middleware for parsing URL-encoded request bodies.
 *
 * Parses URL-encoded request bodies into an array for supported methods.
 * Delegates the actual parsing to {@see Middlewares\UrlEncodePayload}.
 */
class UrlEncodePayloadMiddleware implements MiddlewareInterface
{
    /**
     * Internal middleware: https://github.com/middlewares/payload?tab=readme-ov-file#urlencodepayload
     *
     * @var UrlEncodePayload
     */
    private UrlEncodePayload $middleware;

    /**
     * Creates a new url-encoded payload middleware instance.
     *
     * @param string[] $contentType
     * @param string[] $methods
     * @param bool     $override
     */
    public function __construct(
        array $contentType = ['application/x-www-form-urlencoded'],
        array $methods     = ['POST', 'PUT', 'PATCH', 'DELETE', 'COPY', 'LOCK', 'UNLOCK'],
        bool  $override    = false,
    ) { 
        $this->middleware = new UrlEncodePayload();

        $this->middleware->contentType($contentType);
        $this->middleware->methods($methods);
        $this->middleware->override($override);
    }

    /**
     * {@inheritDoc}
     *
     * Delegates processing to internal middleware.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $this->middleware->process($request, $handler);
    }
}