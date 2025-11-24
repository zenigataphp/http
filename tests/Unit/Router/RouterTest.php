<?php

declare(strict_types=1);

namespace Zenigata\Http\Test\Unit\Router;

use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Http\Message\ResponseInterface;
use Zenigata\Http\Error\HttpError;
use Zenigata\Http\Router\Router;
use Zenigata\Http\Router\RouteInterface;
use Zenigata\Http\Router\GroupInterface;
use Zenigata\Http\Router\RouteMatch;
use Zenigata\Utility\Psr\FakeContainer;
use Zenigata\Utility\Psr\FakeRequestHandler;

/**
 * Unit test for {@see Zenigata\Http\Router\Router}.
 * 
 * Covered cases:
 *
 * - Successfully handle a matched route returning {@see Psr\Http\Message\ResponseInterface}.
 * - Handle not-found (404) and method-not-allowed (405).
 * - Properly register and flatten routes and groups.
 * - Resolve a route string definitions.
 * - Enrich the request with matched route metadata and parameters.
 */
#[CoversClass(Router::class)]
final class RouterTest extends TestCase
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

    public function testHandleFoundRouteReturnsResponse(): void
    {
        $router = new Router([$this->route]);

        $response = $router->handle(new ServerRequest('GET', '/hello'));

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testHandleNotFoundThrowsHttpError(): void
    {
        $router = new Router([$this->route]);

        $this->expectException(HttpError::class);
        $this->expectExceptionMessage('Not Found');

        $router->handle(new ServerRequest('GET', '/not-found'));
    }

    public function testHandleMethodNotAllowedThrowsHttpError(): void
    {
        $router = new Router([$this->route]);

        $this->expectException(HttpError::class);
        $this->expectExceptionMessage('Allowed methods: GET.');

        $router->handle(new ServerRequest('POST', '/hello'));
    }

    public function testRegisterAndGetRoutes(): void
    {
        $router = new Router();
        $router->register($this->route);

        $routes = $router->getRoutes();

        $this->assertCount(1, $routes);
        $this->assertSame($this->route, $routes[0]);
    }

    public function testResolveGroupFlattensNestedRoutes(): void
    {
        $group = new class($this->route) implements GroupInterface {
            public function __construct(
                private RouteInterface $route
            ) {}

            public function getRoutes(): array {
                return [
                    $this->route
                ];
            }
        };

        $router = new Router([$group]);
        $routes = $router->getRoutes();

        $this->assertCount(1, $routes);
        $this->assertSame($this->route, $routes[0]);
    }

    public function testResolveDefinitionFromContainer(): void
    {
        $container = new FakeContainer([
            'my.route' => $this->route,
        ]);

        $router = new Router(['my.route'], $container);

        $routes = $router->getRoutes();

        $this->assertCount(1, $routes);
        $this->assertSame($this->route, $routes[0]);
    }

    public function testEnrichRequestAddsRouteAttributes(): void
    {
        /** @var ServerRequestInterface|null */
        $capturedRequest = null;

        $handler = new FakeRequestHandler(
            response: new Response(),
            callable: function ($request) use (&$capturedRequest) {
                $capturedRequest = $request;
            }
        );

        $route = new class($handler) implements RouteInterface {
            public function __construct(
                private $handler
            ) {}

            public function getMethod(): string
            {
                return 'GET';
            }

            public function getPath(): string
            {
                return '/user/{id}';
            }

            public function getHandler(): mixed
            {
                return $this->handler;
            }

            public function getMiddleware(): array
            {
                return [];
            }
        };

        $router = new Router([$route]);
        $router->handle(new ServerRequest('GET', '/user/42'));

        $this->assertInstanceOf(RouteMatch::class, $capturedRequest->getAttribute('route'));
        $this->assertSame('42', $capturedRequest->getAttribute('id'));
    }
}
