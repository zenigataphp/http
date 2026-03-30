<?php

declare(strict_types=1);

namespace Zenigata\Http\Handling;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Default implementation of {@see Zenigata\Http\Handling\HandlerInvokerInterface}.
 * 
 * Invokes the callable handler passing the parameters as optional named arguments.
 */
class DefaultHandlerInvoker implements HandlerInvokerInterface
{
    /**
     * @inheritDoc
     */
    public function invoke(ServerRequestInterface $request, callable $handler, array $parameters = []): mixed
    {
        return $handler($request, ...$parameters);
    }
}