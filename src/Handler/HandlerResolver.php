<?php

declare(strict_types=1);

namespace Zenigata\Http\Handler;

use InvalidArgumentException;
use LogicException;
use RuntimeException;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zenigata\Helpers\ReflectionHelper;

use function count;
use function is_array;
use function is_callable;
use function is_string;
use function method_exists;

/**
 * Implementation of {@see HandlerResolverInterface}.
 * 
 * Resolves route handler definitions into a PSR-15 request handler.
 *
 * It supports container identifiers, callables, controller as [class, method] pairs,
 * or instances of {@see RequestHandlerInterface}.
 */
class HandlerResolver implements HandlerResolverInterface
{
    /**
     * Creates a new handler resolver instance.
     *
     * @param ContainerInterface|null $container Optional PSR-11 container for resolving services.
     */
    public function __construct(
        private ?ContainerInterface $container = null
    ) {}

    /**
     * {@inheritdoc}
     */
    public function resolve(mixed $handler): RequestHandlerInterface
    {
        if (is_string($handler)) {
            $handler = $this->resolveDefinition($handler);
        }

        if (is_callable($handler)) {
            $handler = $this->resolveCallable($handler);
        }

        if ($this->isController($handler)) {
            $handler = $this->resolveController($handler);
        }
        
        return $handler;
    }

    /**
     * Checks whether the given definition represents a controller.
     *
     * A controller is defined as an array with exactly two string elements:
     * [className, methodName].
     */
    private function isController(mixed $handler): bool
    {
        return is_array($handler)
            && count($handler) === 2
            && is_string($handler[0])
            && is_string($handler[1]);
    }

    /**
     * Resolves a string definition into arequest handler or a controller instance.
     * 
     * @return mixed The resolved request handler or controller instance.
     * @throws InvalidArgumentException // TODO
     * @throws LogicException // TODO 
     */
    private function resolveDefinition(string $handler): mixed
    {
        if ($this->container !== null && $this->container->has($handler)) {
            return $this->container->get($handler);
        }

        return ReflectionHelper::instantiate($handler);
    }

    /**
     * Resolves a callable into a request handler.
     */
    private function resolveCallable(callable $handler): RequestHandlerInterface
    {
        return new class($handler) implements RequestHandlerInterface {
            /** @param callable $handler */
            public function __construct(
                private $handler
            ) {}

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return ($this->handler)($request);
            }
        };
    }

    /**
     * Resolves a [class, method] controller definition into a request handler.
     * 
     * @param array{0:string,1:string} $handler
     * 
     * @return RequestHandlerInterface The resolved request handler.
     * @throws InvalidArgumentException If the controller service is missing or has the wrong type.
     * @throws RuntimeException If the controller method does not exist.
     */
    private function resolveController(array $handler): RequestHandlerInterface
    {
        [$class, $method] = $handler;

        $instance = $this->resolveDefinition($class);

        if (!method_exists($instance, $method)) {
            throw new RuntimeException("Method '$method' does not exist on class '$class'.");
        }

        return new class($instance, $method) implements RequestHandlerInterface {
            public function __construct(
                private object $instance,
                private string $method,
            ) {}

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return $this->instance->{$this->method}($request);
            }
        };
    }
}