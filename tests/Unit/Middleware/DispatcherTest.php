<?php

declare(strict_types=1);

namespace Zenigata\Http\Test\Unit\Middleware;

use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Http\Message\ResponseInterface;
use Zenigata\Http\Middleware\Dispatcher;
use Zenigata\Utility\Psr\FakeContainer;
use Zenigata\Utility\Psr\FakeMiddleware;
use Zenigata\Utility\Psr\FakeRequestHandler;

use function array_shift;

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
    private ResponseInterface $response;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->response = new Response();
    }

    public function testExecutionOrder(): void
    {
        $stack = [];
        $names = ['middleware1', 'middleware2', 'handler'];

        $callback = function () use (&$stack, &$names) {
            $name = array_shift($names);
            $stack[] = $name;
        };

        $dispatcher = new Dispatcher(
            middleware: [
                new FakeMiddleware(callable: $callback), 
                new FakeMiddleware(callable: $callback),
            ], 
            handler: new FakeRequestHandler($this->response, $callback)
        );

        $dispatcher->handle(new ServerRequest('GET', '/'));

        $this->assertSame(['middleware1', 'middleware2', 'handler'], $stack);
    }

    public function testResolveFromContainer(): void
    {
        $stack = [];
        $names = ['containerMiddleware', 'handler'];

        $callback = function () use (&$stack, &$names) {
            $name = array_shift($names);
            $stack[] = $name;
        };

        $container = new FakeContainer([
            'fake.middleware' => new FakeMiddleware(callable: $callback)
        ]);

        $dispatcher = new Dispatcher(
            middleware: [
                'fake.middleware'
            ], 
            handler:   new FakeRequestHandler($this->response, $callback),
            container: $container
        );

        $dispatcher->handle(new ServerRequest('GET', '/'));

        $this->assertSame(['containerMiddleware', 'handler'], $stack);
    }
}
