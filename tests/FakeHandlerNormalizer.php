<?php

declare(strict_types=1);

namespace Zenigata\Http\Test;

use Nyholm\Psr7\Response;
use Zenigata\Http\Handling\HandlerNormalizerInterface;
use Zenigata\Utility\Awareness\ContainerAwareInterface;
use Zenigata\Utility\Awareness\ContainerAwareTrait;

use function is_callable;

/**
 * Fake implementation of {@see Zenigata\Http\Handling\HandlerNormalizerInterface}.
 */
class FakeHandlerNormalizer implements HandlerNormalizerInterface, ContainerAwareInterface
{
    use ContainerAwareTrait;

    public function normalize(mixed $handler): callable
    {
        if (is_callable($handler)) {
            return $handler;
        }

        return fn() => new Response(204);
    }
}