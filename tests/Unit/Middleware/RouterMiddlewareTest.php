<?php

declare(strict_types=1);

namespace Zenigata\Http\Test\Unit\Middleware;

use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Http\Message\ResponseInterface;
use Zenigata\Http\Middleware\RouterMiddleware;
use Zenigata\Http\Router\RouteInterface;
use Zenigata\Utility\Psr\FakeRequestHandler;

/**
 * Unit test for {@see Zenigata\Http\Middleware\RouterMiddleware}.
 * 
 * Covered cases:
 *
 * - Process a matching request and return the expected {@see Psr\Http\Message\ResponseInterface}.
 * - Delegate routing operations to the internal {@see Zenigata\Http\Router\Router}.
 * - Properly register and expose routes.
 * - Preserve router configuration (cache flag and route retrieval).
 */
#[CoversClass(RouterMiddleware::class)]
final class RouterMiddlewareTest extends TestCase
{
    private RouteInterface $route;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->route = new class implements RouteInterface {
            public function getMethod(): string
            {
                return 'GET';
            }

            public function getPath(): string
            {
                return '/hello';
            }

            public function getHandler(): mixed
            {
                return fn() => new Response();
            }

            public function getMiddleware(): array
            {
                return [];
            }
        };
    }

    public function testProcessDelegatesToInternalRouter(): void
    {
        $middleware = new RouterMiddleware([$this->route]);

        $response = $middleware->process(
            request: new ServerRequest('GET', '/hello'),
            handler: new FakeRequestHandler(new Response())
        );

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testRegisterAddsRoutes(): void
    {
        $middleware = new RouterMiddleware();
        $middleware->register($this->route);

        $routes = $middleware->getRoutes();

        $this->assertCount(1, $routes);
        $this->assertSame($this->route, $routes[0]);
    }

    public function testIsCacheEnabledReflectsRouterSetting(): void
    {
        $middleware = new RouterMiddleware([], enableCache: true);

        $this->assertTrue($middleware->isCacheEnabled());
    }
}
