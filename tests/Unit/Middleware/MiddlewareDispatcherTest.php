<?php

declare(strict_types=1);

namespace Zenigata\Http\Test\Unit\Middleware;

use InvalidArgumentException;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Zenigata\Http\Middleware\MiddlewareDispatcher;
use Zenigata\Http\Test\FakeMiddleware;
use Zenigata\Http\Test\FakeRequestHandler;
use Zenigata\Utility\Testing\FakeContainer;

/**
 * Unit test for {@see Zenigata\Http\Middleware\MiddlewareDispatcher}.
 * 
 * Covered cases:
 *
 * - Run middleware in order, then the request handler.  
 * - Resolve handlers from a PSR-11 container when referenced by service ID.  
 * - Resolve handlers using reflection when the container does not own the service ID.
 * - Throw exceptions when a resolved class is not implementing MiddlewareInterface.
 */
#[CoversClass(MiddlewareDispatcher::class)]
final class MiddlewareDispatcherTest extends TestCase
{
    private FakeRequestHandler $handler;

    private ServerRequest $request;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->handler = new FakeRequestHandler();
        $this->request = new ServerRequest('GET', '/'); 
    }

    public function testDispatchRunsInOrder(): void
    {
        $calls = [];

        $callback = function(string $name) use (&$calls): void {
            $calls[] = $name;
        };

        $dispatcher = new MiddlewareDispatcher([
            new FakeMiddleware(fn() => $callback('middleware1')), 
            new FakeMiddleware(fn() => $callback('middleware2')),
        ]);

        $dispatcher->addMiddleware(new FakeMiddleware(fn() => $callback('middleware3')));

        $response = $dispatcher->dispatch($this->request, $this->handler);

        $this->assertSame(['middleware1', 'middleware2', 'middleware3'], $calls);
        $this->assertCount(3, $dispatcher->getMiddleware());
        $this->assertSame(204, $response->getStatusCode());
    }

    public function testDispatchResolvesFromContainer(): void
    {
        $container  = new FakeContainer(['fake.middleware' => new FakeMiddleware()]);

        $dispatcher = new MiddlewareDispatcher(['fake.middleware']);
        $dispatcher->setContainer($container);

        $response = $dispatcher->dispatch($this->request, $this->handler);

        $this->assertSame(204, $response->getStatusCode());
    }

    public function testDispatchResolvesWithReflection(): void
    {
        $container = new FakeContainer(); // Empty container

        $dispatcher = new MiddlewareDispatcher([FakeMiddleware::class]);
        $dispatcher->setContainer($container);
 
        $response = $dispatcher->dispatch($this->request, $this->handler);
 
        $this->assertSame(204, $response->getStatusCode());
    }

    public function testDispatchThrowsIfInvalidMiddleware(): void
    {
        $container = new FakeContainer(['invalid.middleware' => new class {}]);
 
        $dispatcher = new MiddlewareDispatcher(['invalid.middleware']);
        $dispatcher->setContainer($container);
 
        $this->expectException(InvalidArgumentException::class);
 
        $dispatcher->dispatch($this->request, $this->handler);
    }
}
