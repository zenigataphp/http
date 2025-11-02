<?php

declare(strict_types=1);

namespace Zenigata\Http\Handler;

use InvalidArgumentException;
use RuntimeException;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zenigata\Utility\Helper\ReflectionHelper;

use function count;
use function get_debug_type;
use function is_array;
use function is_callable;
use function is_string;
use function method_exists;
use function sprintf;

/**
 * Implementation of {@see Zenigata\Http\Handler\HandlerResolverInterface}.
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
     * @param HandlerInvokerInterface $invoker   Handler invoker used to execute callables and controllers.
     * @param ContainerInterface|null $container Optional PSR-11 container for resolving services.
     */
    public function __construct(
        private HandlerInvokerInterface $invoker = new HandlerInvoker(),
        private ?ContainerInterface $container = null,
    ) {}

    /**
     * {@inheritdoc}
     */
    public function resolve(mixed $handler, array $parameters = []): RequestHandlerInterface
    {
        if (is_string($handler)) {
            $handler = $this->resolveDefinition($handler);
        }

        if ($this->isController($handler)) {
            $handler = $this->resolveController($handler, $parameters);
        }

        if (is_callable($handler)) {
            $handler = $this->resolveCallable($handler, $parameters);
        }

        if (!$handler instanceof RequestHandlerInterface) {
            throw new InvalidArgumentException(sprintf(
                "Handler of type '%s' could not be resolved to a RequestHandlerInterface.",
                is_object($handler) ? $handler::class : get_debug_type($handler)
            ));
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
     * @throws RuntimeException If the class cannot be resolved or instantiated.
     */
    private function resolveDefinition(string $handler): mixed
    {
        if ($this->container !== null && $this->container->has($handler)) {
            return $this->container->get($handler);
        }

        return ReflectionHelper::instantiate($handler);
    }

    /**
     * Resolves a [class, method] controller definition into a request handler.
     * 
     * @param array{0:string,1:string} $handler
     * @param array<string,string>     $parameters
     * 
     * @return RequestHandlerInterface The resolved request handler.
     * @throws RuntimeException If the controller method does not exist.
     */
    private function resolveController(array $handler, array $parameters): RequestHandlerInterface
    {
        [$class, $method] = $handler;

        $instance = $this->resolveDefinition($class);

        if (!method_exists($instance, $method)) {
            throw new RuntimeException("Method '$method' does not exist on class '$class'.");
        }

        return $this->resolveCallable([$instance, $method], $parameters);
    }

    /**
     * Resolves a callable into a request handler.
     * 
     * @param callable             $handler
     * @param array<string,string> $parameters
     */
    private function resolveCallable(callable $handler, array $parameters): RequestHandlerInterface
    {
        return new class($handler, $parameters, $this->invoker) implements RequestHandlerInterface {
            /** @param callable $handler */
            public function __construct(
                private $handler,
                private array $parameters,
                private HandlerInvokerInterface $invoker,
            ) {}

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return $this->invoker->invoke(
                    handler:    $this->handler,
                    request:    $request,
                    parameters: $this->parameters
                );
            }
        };
    }
}