<?php

declare(strict_types=1);

namespace Zenigata\Http\Handling;

/**
 * Defines a contract for an handler normalizer.
 *
 * Transforms the handler definition into a valid callable,
 * so it can be executed by the invoker.
 */
interface HandlerNormalizerInterface
{
    /**
     * Transforms the handler definition into a valid callable.
     *
     * @param mixed $handler The handler definition.
     * 
     * @return callable The callable handler.
     */
    public function normalize(mixed $handler): callable;
}