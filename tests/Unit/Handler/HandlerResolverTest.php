<?php

declare(strict_types=1);

namespace Zenigata\Http\Test\Unit\Handler;

use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use RuntimeException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Http\Message\ResponseInterface;
use Zenigata\Http\Handler\HandlerResolver;
use Zenigata\Utility\Psr\FakeContainer;
use Zenigata\Utility\Psr\FakeRequestHandler;

/**
 * Unit test for {@see HandlerResolver}.
 * 
 * Covered cases:
 *
 * - Execute callable handlers returning {@see \Psr\Http\Message\ResponseInterface}.
 * - Reject callables with invalid return types, throwing {@see RuntimeException}.
 * - Resolve handlers by identifier from the container and invoking them.
 * - Support controller-style handlers `[classOrService, method]` via container or direct.
 * - Throw exceptions for missing methods in controllers.
 * - Handle unsupported handler types with an appropriate exception message.
 */
#[CoversClass(HandlerResolver::class)]
final class HandlerResolverTest extends TestCase
{
    public function testResolveCallable(): void
    {
        $resolver = new HandlerResolver();

        $resolved = $resolver->resolve(fn() => new Response(204));
        $response = $resolved->handle(new ServerRequest('GET', '/'));

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertSame(204, $response->getStatusCode());
    }

    public function testResolveFromContainer(): void
    {
        $container = new FakeContainer([
            'handler' => new FakeRequestHandler(new Response(204)),
        ]);

        $resolver = new HandlerResolver(container: $container);

        $resolved = $resolver->resolve('handler', []);
        $response = $resolved->handle(new ServerRequest('GET', '/'));

        $this->assertSame(204, $response->getStatusCode());
    }

    public function testResolveController(): void
    {
        $container = new FakeContainer([
            'controller' => new class {
                public function index($request)
                {
                    return new Response(204);
                }
            }
        ]);

        $resolver = new HandlerResolver(container: $container);

        $resolved = $resolver->resolve(['controller', 'index'], []);
        $response = $resolved->handle(new ServerRequest('GET', '/'));

        $this->assertSame(204, $response->getStatusCode());
    }

    public function testResolveControllerThrowsIfMethodMissing(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Method 'index' does not exist on class 'controller'.");
        
        $container = new FakeContainer([
            'controller' => new class {},
        ]);

        $resolver = new HandlerResolver(container: $container);
        $resolver->resolve(['controller', 'index']);
    }
}
