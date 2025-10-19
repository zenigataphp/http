<?php

declare(strict_types=1);

namespace Zenigata\Http\Test\Unit\Middleware;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Zenigata\Http\Middleware\Dispatcher;
use Zenigata\Http\Test\LoggableMiddleware;
use Zenigata\Http\Test\LoggableRequestHandler;
use Zenigata\Testing\Http\FakeServerRequest;
use Zenigata\Testing\Infrastructure\FakeContainer;

/**
 * Unit test for {@see Dispatcher}.
 * 
 * Covered cases:
 *
 * - Run middleware in order, then the request handler.  
 * - Resolve middleware from a PSR-11 container when referenced by service ID.  
 * - Wrap and execute callable middleware as PSR-15.
 */
#[CoversClass(Dispatcher::class)]
final class DispatcherTest extends TestCase
{
    public function testExecutionOrder(): void
    {
        $calls = [];

        $dispatcher = new Dispatcher(
            middleware: [
                new LoggableMiddleware($calls, 'middleware1'), 
                new LoggableMiddleware($calls, 'middleware2'),
            ], 
            handler: new LoggableRequestHandler($calls, 'handler')
        );

        $dispatcher->handle(new FakeServerRequest());

        $this->assertSame(['middleware1', 'middleware2', 'handler'], $calls);
    }

    public function testResolveFromContainer(): void
    {
        $calls = [];

        $container = new FakeContainer([
            'fake.middleware' => new LoggableMiddleware($calls, 'containerMiddleware')
        ]);

        $dispatcher = new Dispatcher(
            middleware: [
                'fake.middleware'
            ], 
            handler:   new LoggableRequestHandler($calls, 'handler'),
            container: $container
        );

        $dispatcher->handle(new FakeServerRequest());

        $this->assertSame(['containerMiddleware', 'handler'], $calls);
    }
}
