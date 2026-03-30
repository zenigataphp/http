<?php

declare(strict_types=1);

namespace Zenigata\Http\Handling;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Zenigata\Utility\Awareness\ContainerAwareInterface;
use Zenigata\Utility\Awareness\ContainerAwareTrait;
use Zenigata\Utility\Helper\ReflectionResolver;

use function count;
use function get_debug_type;
use function is_array;
use function is_callable;
use function is_object;
use function is_string;
use function method_exists;
use function sprintf;

/**
 * Implementation of {@see Zenigata\Http\Handling\HandlerNormalizerInterface}.
 * 
 * Supports {@see Psr\Http\Server\RequestHandlerInterface}, callables,
 * and controllers defined as [className, methodName].
 */
class HandlerNormalizer implements HandlerNormalizerInterface, ContainerAwareInterface
{
    use ContainerAwareTrait;

    /**
     * @inheritDoc
     *
     * @throws RuntimeException If the handler cannot be normalized or instantiated.
     */
    public function normalize(mixed $handler): callable
    {
        if (is_string($handler)) {
            $handler = $this->resolveHandler($handler);
        }

        return match (true) {
            is_callable($handler)                       => $handler,
            $handler instanceof RequestHandlerInterface => $this->createCallableHandler($handler),
            $this->isController($handler)               => $this->normalizeController($handler),
            default                                     => throw new RuntimeException(sprintf(
                "Handler of type '%s' cannot be normalized into a callable.",
                is_object($handler) ? $handler::class : get_debug_type($handler)
            ))
        };
    }

    /**
     * Resolves a string definition into a request handler or a controller instance.
     * 
     * @return mixed The resolved handler or controller instance.
     * @throws RuntimeException If the handler cannot be resolved or instantiated.
     */
    private function resolveHandler(string $handler): mixed
    {
        return $this->container?->has($handler)
            ? $this->container->get($handler)
            : ReflectionResolver::resolve($handler);
    }

    /**
     * Wraps a {@see Psr\Http\Server\RequestHandlerInterface} into a callable.
     *
     * @return callable The callable handler.
     */
    private function createCallableHandler(RequestHandlerInterface $handler): callable
    {
        return fn(ServerRequestInterface $request) => $handler->handle($request);
    }

    /**
     * Normalizes a [class, method] controller definition into a callable.
     * 
     * @param array{0:string,1:string} $handler
     * 
     * @return callable The callable handler.
     * @throws RuntimeException If the controller method does not exist.
     */
    private function normalizeController(array $handler): callable
    {
        [$class, $method] = $handler;

        $instance = $this->resolveHandler($class);

        if (!method_exists($instance, $method)) {
            throw new RuntimeException("Method '$method' does not exist on class '$class'.");
        }

        return [$instance, $method];
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
}