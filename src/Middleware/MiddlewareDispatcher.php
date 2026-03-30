<?php

declare(strict_types=1);

namespace Zenigata\Http\Middleware;

use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zenigata\Utility\Helper\ReflectionResolver;
use Zenigata\Utility\Awareness\ContainerAwareInterface;
use Zenigata\Utility\Awareness\ContainerAwareTrait;

use function array_reverse;
use function is_string;
use function sprintf;

/**
 * Implementation of {@see Zenigata\Http\Middleware\MiddlewareDispatcherInterface}.
 */
class MiddlewareDispatcher implements MiddlewareDispatcherInterface, ContainerAwareInterface
{
    use ContainerAwareTrait;
    
    /**
     * Creates a new dispatcher instance.
     * 
     * @param list<MiddlewareInterface|string> $middleware The middleware stack.
     */
    public function __construct(
        private array $middleware = [],
    ) {}
    
    /**
     * @inheritDoc
     */
    public function dispatch(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $pipeline = array_reverse($this->middleware); // FIFO

        foreach ($pipeline as $middleware) {
            if (is_string($middleware)) {
                $middleware = $this->resolveMiddleware($middleware);
            }

            $handler = $this->createHandler($middleware, $handler);
        }
    
        return $handler->handle($request);
    }

    /**
     * @inheritDoc
     */
    public function addMiddleware(MiddlewareInterface|string $middleware): void
    {
        $this->middleware[] = $middleware;
    }

    /**
     * @inheritDoc
     */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    /**
     * Resolves a middleware definition into a middleware instance.
     * 
     * @throws InvalidArgumentException If the middleware cannot be resolved or has the wrong type.
     */
    private function resolveMiddleware(string $middleware): MiddlewareInterface
    {
        $instance = $this->container?->has($middleware)
            ? $this->container->get($middleware)
            : ReflectionResolver::resolve($middleware);

        if (!$instance instanceof MiddlewareInterface) {
            throw new InvalidArgumentException(sprintf(
                "Invalid type for '%s'. Expected [%s], got '%s'.",
                $middleware,
                MiddlewareInterface::class,
                $instance::class
            ));
        }

        return $instance;
    }

    /**
     * Creates a request handler that wraps a middleware and the next handler in the chain.
     */
    private function createHandler(MiddlewareInterface $middleware, RequestHandlerInterface $handler): RequestHandlerInterface
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