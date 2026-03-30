<?php

declare(strict_types=1);

namespace Zenigata\Http\Test\Unit\Routing;

use InvalidArgumentException;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zenigata\Http\Error\HttpError;
use Zenigata\Http\Routing\Route;
use Zenigata\Http\Routing\RouteMatch;
use Zenigata\Http\Routing\Router;
use Zenigata\Http\Test\FakeRoute;
use Zenigata\Utility\Testing\FakeContainer;

/**
 * Unit test for {@see Zenigata\Http\Routing\Router}.
 *
 * Covered cases:
 *
 * - Return a RouteMatch when the request matches a registered route.
 * - Throw HttpError 404 when no route matches the request path.
 * - Throw HttpError 405 when the path matches but the method is not allowed.
 * - getRoutes() flattens a Group and applies its prefix and middleware to each route.
 * - getRoutes() resolves a string route FQCN via container or reflection.
 * - getRoutes() throws InvalidArgumentException when a string resolves to the wrong type.
 * - isCacheEnabled() reflects the value passed at construction.
 */
#[CoversClass(Router::class)]
final class RouterTest extends TestCase
{
    private Router $router;

    private ServerRequest $request;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->router  = new Router();
        $this->request = new ServerRequest('GET', '/users');
    }

    public function testMatchReturnsRoute(): void
    {
        $this->router->addRoute(Route::get('/users', fn() => null));

        $match = $this->router->match($this->request);

        $this->assertInstanceOf(RouteMatch::class, $match);
        $this->assertSame('GET', $match->method);
        $this->assertSame('/users', $match->path);
    }

    public function testMatchThrowsNotFound(): void
    {
        $this->router->addRoute(Route::get('/posts', fn() => null));

        $this->expectException(HttpError::class);
        $this->expectExceptionCode(404);

        $this->router->match($this->request);
    }

    public function testMatchThrowsMethodNotAllowed(): void
    {
        $this->router->addRoute(Route::post('/users', fn() => null));

        $this->expectException(HttpError::class);
        $this->expectExceptionCode(405);

        $this->router->match($this->request);
    }

    public function testMatchAddsRouteParameters(): void
    {
        $this->router->addRoute(Route::get('/users/{id}', fn() => null));
        
        $request = new ServerRequest('GET', '/users/42');
        $match   = $this->router->match($request);

        $this->assertSame(['id' => '42'], $match->parameters);
    }

    public function testAddRouteInvalidatesDispatcher(): void
    {
        $this->router->addRoute(Route::get('/users', fn() => null));

        // Warm up the dispatcher.
        $this->router->match($this->request);

        // Add a new route after the dispatcher was built.
        $this->router->addRoute(Route::get('/posts', fn() => null));

        // The new route must be reachable — this would fail if the dispatcher were not rebuilt.
        $match = $this->router->match(new ServerRequest('GET', '/posts'));

        $this->assertSame('/posts', $match->path);
    }

    public function testGetRoutesAppliesGroupPrefix(): void
    {
        $group = Route::group(
            prefix: '/api',
            routes: fn() => [Route::get('/users', fn() => null)]
        );

        $this->router->addRoute($group);

        $routes = $this->router->getRoutes();

        $this->assertCount(1, $routes);
        $this->assertSame('/api/users', $routes[0]->getPath());
    }

    public function testGetRoutesAppliesGroupMiddleware(): void
    {
        $group = Route::group(
            prefix: '/api',
            routes: fn() => [Route::get('/users', fn() => null)],
            middleware: ['auth']
        );

        $this->router->addRoute($group);

        $routes = $this->router->getRoutes();

        $this->assertContains('auth', $routes[0]->getMiddleware());
    }

    public function testGetRoutesResolvesFromContainer(): void
    {
        $route     = Route::get('/users', fn() => null);
        $container = new FakeContainer(['get-users' => $route]);

        $this->router->addRoute('get-users');
        $this->router->setContainer($container);

        $this->assertContains($route, $this->router->getRoutes());
    }

    public function testGetRoutesResolvesViaReflection(): void
    {
        $this->router->addRoute(FakeRoute::class);

        $routes = $this->router->getRoutes();

        $this->assertSame('/hello', $routes[0]->getPath());
    }

    public function testGetRoutesThrowsIfInvalidResolvedType(): void
    {
        $container = new FakeContainer(['invalid.route' => new class {}]);

        $this->router->addRoute('invalid.route');
        $this->router->setContainer($container);

        $this->expectException(InvalidArgumentException::class);

        $this->router->getRoutes();
    }

    public function testIsCacheDisabledByDefault(): void
    {
        $this->assertFalse($this->router->isCacheEnabled());
    }

    public function testIsCacheEnabledWhenConfigured(): void
    {
        $router = new Router(enableCache: true);

        $this->assertTrue($router->isCacheEnabled());
    }
}