<?php

declare(strict_types=1);

namespace Zenigata\Http\Middleware;

use InvalidArgumentException;
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
 * PSR-15 middleware dispatcher.
 * 
 * A PSR-15 compatible middleware dispatcher that processes middleware sequentially, 
 * allowing a final handler as a fallback if no middleware handles the request.
 */
class Dispatcher implements RequestHandlerInterface
{
    /**
     * Creates a new middleware dispatcher instance.
     * 
     * @param iterable<MiddlewareInterface|string> $middleware Initial middleware stack.
     * @param RequestHandlerInterface|string|null  $handler    Final handler executed after all middleware.
     * @param ContainerInterface|null              $container  Optional PSR-11 container for resolving service IDs.
     */
    public function __construct(
        private iterable $middleware = [],
        private RequestHandlerInterface|string|null $handler = null,
        private ?ContainerInterface $container = null,
    ) {}

    /**
     * @inheritDoc
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $handler = $this->handler ?? $this->notFoundHandler();

        if (is_string($handler)) {
            $handler = $this->resolveHandler($handler);
        }

        $middleware = array_reverse($this->middleware);

        foreach ($middleware as $middleware) {
            if (is_string($middleware)) {
                $middleware = $this->resolveMiddleware($middleware);
            }

            $handler = $this->wrapMiddleware($middleware, $handler);
        }
    
        return $handler->handle($request);
    }

    /**
     * Registers a middleware into the stack.
     *
     * @param MiddlewareInterface|string $middleware Middleware instance, or container-resolvable identifier.
     */
    public function register(MiddlewareInterface|string $middleware): void
    {
        $this->middleware[] = $middleware;
    }

    /**
     * Resolves a string definition from container or via reflection.
     */
    private function resolveDefinition(string $definition): object
    {
        if ($this->container !== null && $this->container->has($definition)) {
            return $this->container->get($definition);
        }

        return ReflectionHelper::instantiate($definition);
    }

    /**
     * Resolves a middleware definition into a middleware instance.
     * 
     * @throws InvalidArgumentException If the middleware is missing or has the wrong type.
     */
    private function resolveMiddleware(string $middleware): MiddlewareInterface
    {
        $resolved = $this->resolveDefinition($middleware);

        if (!$resolved instanceof MiddlewareInterface) {
            throw new InvalidArgumentException(sprintf(
                "Invalid type for '%s'. Expected [%s], got '%s'.",
                $middleware,
                MiddlewareInterface::class,
                $resolved::class
            ));
        }

        return $resolved;
    }

    /**
     * Resolves a handler definition into an handler instance.
     * 
     * @throws InvalidArgumentException If the handler is missing or has the wrong type.
     */
    private function resolveHandler(string $handler): RequestHandlerInterface
    {
        $resolved = $this->resolveDefinition($handler);

        if (!$resolved instanceof RequestHandlerInterface) {
            throw new InvalidArgumentException(sprintf(
                "Invalid type for '%s'. Expected [%s], got '%s'.",
                $handler,
                RequestHandlerInterface::class,
                $resolved::class
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

