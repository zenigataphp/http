<?php

declare(strict_types=1);

namespace Zenigata\Http\Middleware;

use InvalidArgumentException;
use LogicException;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zenigata\Http\Error\HttpError;
use Zenigata\Utility\Helper\ReflectionHelper;

use function array_reverse;
use function is_string;
use function sprintf;

/**
 * Implementation of {@see DispatcherInterface}.
 * 
 * A PSR-15 compatible middleware dispatcher that processes middleware sequentially, 
 * allowing a final handler as a fallback if no middleware handles the request.
 */
class Dispatcher implements DispatcherInterface
{
    /**
     * Creates a new middleware dispatcher instance.
     * 
     * @param MiddlewareInterface[]|string[] $middleware Initial middleware stack.
     * @param RequestHandlerInterface|null   $handler    Final handler executed after all middleware.
     * @param ContainerInterface|null        $container  Optional PSR-11 container for resolving service IDs.
     */
    public function __construct(
        private array $middleware = [],
        private ?RequestHandlerInterface $handler = null,
        private ?ContainerInterface $container = null,
    ) {}

    /**
     * @inheritDoc
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $handler = $this->handler ?? $this->notFoundHandler();
        $middleware = array_reverse($this->middleware);

        foreach ($middleware as $middleware) {
            if (is_string($middleware)) {
                $middleware = $this->resolveDefinition($middleware);
            }

            $handler = $this->wrapMiddleware($middleware, $handler);
        }
    
        return $handler->handle($request);
    }

    /**
     * @inheritDoc
     */
    public function register(MiddlewareInterface|string $middleware): void
    {
        $this->middleware[] = $middleware;
    }

    /**
     * Resolves a string definition into a middleware instance.
     * 
     * @return MiddlewareInterface The resolved middleware instance.
     * @throws LogicException If no container has been set.
     * @throws InvalidArgumentException If the middleware is missing or has the wrong type.
     */
    private function resolveDefinition(string $middleware): MiddlewareInterface
    {
        $resolved = null;

        if ($this->container !== null && $this->container->has($middleware)) {
            $resolved = $this->container->get($middleware);
        }

        if ($resolved === null) {
            $resolved = ReflectionHelper::instantiate($middleware);
        }

        if (!$resolved instanceof MiddlewareInterface) {
            throw new InvalidArgumentException(sprintf(
                "Invalid type for '%s'. Expected [%s], got '%s'.",
                $middleware,
                MiddlewareInterface::class,
                $middleware::class
            ));
        }

        return $resolved;
    }

    /**
     * Creates a fallback request handler that throws an HTTP 404 error.
     */
    private function notFoundHandler(): RequestHandlerInterface
    {
        return new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                throw new HttpError(
                    request: $request,
                    message: 'No handler available to process the request.',
                    code:    404
                );
            }
        };
    }

    /**
     * Creates a request handler that wraps a middleware 
     * and the next handler in the chain.
     */
    private function wrapMiddleware(MiddlewareInterface $middleware, RequestHandlerInterface $handler): RequestHandlerInterface
    {
        return new class($middleware, $handler) implements RequestHandlerInterface {
            public function __construct(
                private MiddlewareInterface $middleware,
                private RequestHandlerInterface $next
            ) {}

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return $this->middleware->process($request, $this->next);
            }
        };
    }
}

