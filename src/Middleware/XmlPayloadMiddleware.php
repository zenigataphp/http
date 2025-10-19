<?php

declare(strict_types=1);

namespace Zenigata\Http\Middleware;

use Middlewares\XmlPayload;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Middleware for parsing XML request bodies.
 *
 * Parses XML request bodies into an array for supported methods.
 * Delegates the actual parsing to {@see Middlewares\XmlPayload}.
 */
class XmlPayloadMiddleware implements MiddlewareInterface
{
    /**
     * Internal middleware: https://github.com/middlewares/payload?tab=readme-ov-file#xmlpayload
     *
     * @var XmlPayload
     */
    private XmlPayload $middleware;

    /**
     * Creates a new xml payload middleware instance.
     *
     * @param string[] $contentType
     * @param string[] $methods
     * @param bool     $override
     */
    public function __construct(
        array $contentType = ['text/xml', 'application/xml', 'application/x-xml'],
        array $methods     = ['POST', 'PUT', 'PATCH', 'DELETE', 'COPY', 'LOCK', 'UNLOCK'],
        bool  $override    = false,
    ) {
        $this->middleware = new XmlPayload();

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