<?php

declare(strict_types=1);

namespace Zenigata\Http\Test\Unit\Routing;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Zenigata\Http\Routing\Route;
use Zenigata\Testing\Http\FakeRequestHandler;

/**
 * Unit test for {@see Route}.
 *
 * Covered cases:
 *
 * - Create routes via static factories for standard HTTP methods, checking method, path, handler, middleware.
 * - Normalize paths by removing trailing slashes while keeping the leading slash.
 * - Create multiple routes at once with {@see Route::map()} and {@see Route::any()}.
 * - Group routes under a prefix and/or middleware via {@see Route::group()}, updating all routes accordingly.
 * - Accept {@see \Psr\Http\Server\RequestHandlerInterface} as a handler directly.
 * - Ensure immutability for prefix and middleware modifications.
 * - Handle edge cases: root path, empty middleware, empty prefix, avoiding mutation in grouped contexts.
 */
#[CoversClass(Route::class)]
final class RouteTest extends TestCase
{
    public function testStaticMethods(): void
    {
        $handler = 'handler';

        $methods = [
            'GET'     => Route::get('/foo', $handler),
            'POST'    => Route::post('/foo', $handler),
            'PUT'     => Route::put('/foo', $handler),
            'PATCH'   => Route::patch('/foo', $handler),
            'DELETE'  => Route::delete('/foo', $handler),
            'HEAD'    => Route::head('/foo', $handler),
            'OPTIONS' => Route::options('/foo', $handler),
        ];

        foreach ($methods as $expectedMethod => $route) {
            $this->assertSame($expectedMethod, $route->getMethod());
            $this->assertSame('/foo', $route->getPath());
            $this->assertSame($handler, $route->getHandler());
            $this->assertEmpty($route->getMiddleware());
        }
    }

    public function testPathIsNormalized(): void
    {
        $route = Route::get('/foo/bar/', 'handler');

        $this->assertSame('/foo/bar', $route->getPath());
    }

    public function testMapCreatesRoutesForEachMethod(): void
    {
        $group = Route::map(['GET', 'POST'], '/api', 'handler');

        $routes = $group->getRoutes();

        $this->assertCount(2, $routes);
        $this->assertSame('GET', $routes[0]->getMethod());
        $this->assertSame('POST', $routes[1]->getMethod());
    }

    public function testAnyCreatesRoutesForAllCommonMethods(): void
    {
        $group = Route::any('/any', 'handler');

        $routes = $group->getRoutes();

        $this->assertCount(7, $routes);
        $this->assertSame('GET', $routes[0]->getMethod());
        $this->assertSame('HEAD', $routes[6]->getMethod());
    }

    public function testGroupAppliesPrefixAndMiddleware(): void
    {
        $group = Route::group(
            prefix:     '/admin',
            routes:     [
                Route::get('/dashboard', 'DashboardHandler'),
                Route::post('/save', 'SaveHandler'),
            ],
            middleware: ['auth']
        );

        $routes = $group->getRoutes();

        $this->assertCount(2, $routes);

        $this->assertSame('/admin/dashboard', $routes[0]->getPath());
        $this->assertSame('/admin/save', $routes[1]->getPath());

        $this->assertSame(['auth'], $routes[0]->getMiddleware());
        $this->assertSame(['auth'], $routes[1]->getMiddleware());
    }

    public function testRouteAcceptsRequestHandlerInstance(): void
    {
        $handler = new FakeRequestHandler();
        $route = Route::get('/foo', $handler);

        $this->assertSame($handler, $route->getHandler());
    }

    public function testConstructorStripsTrailingSlashOnly(): void
    {
        $route = Route::get('/foo/bar///', 'handler');
        $this->assertSame('/foo/bar', $route->getPath());

        $route2 = Route::get('/', 'handler');
        $this->assertSame('', $route2->getPath());
    }

    public function testGroupWithEmptyPrefix(): void
    {
        $group = Route::group(
            prefix:     '', 
            routes:     [Route::get('/one', 'OneHandler')],
            middleware: ['group']
        );

        $routes = $group->getRoutes();

        $this->assertCount(1, $routes);
        $this->assertSame('/one', $routes[0]->getPath());
        $this->assertSame(['group'], $routes[0]->getMiddleware());
    }

    public function testGroupDoesNotMutateOriginalRoutes(): void
    {
        $original = Route::get('/route', 'handler', ['original']);

        $group = Route::group(
            prefix:     '/prefix',
            routes:     [$original],
            middleware: ['group']
        );

        $routes = $group->getRoutes();

        $this->assertSame('/route', $original->getPath());
        $this->assertSame(['original'], $original->getMiddleware());

        $this->assertSame('/prefix/route', $routes[0]->getPath());
        $this->assertSame(['group', 'original'], $routes[0]->getMiddleware());
    }
}
