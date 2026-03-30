<?php

declare(strict_types=1);

namespace Zenigata\Http\Handling;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Defines a contract for an handler invoker.
 * 
 * Invokes the callable handler, defining how parameters should be passed to it.
 */
interface HandlerInvokerInterface
{
    /**
     * Invokes the callable handler.
     *
     * @param ServerRequestInterface $request    The incoming request.
     * @param callable               $handler    The callable handler.
     * @param array                  $parameters The route parameters.
     * 
     * @return mixed The handler result.
     */
    public function invoke(ServerRequestInterface $request, callable $handler, array $parameters = []): mixed;
}