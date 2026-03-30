<?php

declare(strict_types=1);

namespace Zenigata\Http\Test\Unit\Routing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zenigata\Http\Routing\Group;
use Zenigata\Http\Routing\Route;

use function array_map;

/**
 * Unit test for {@see Zenigata\Http\Routing\Route}.
 *
 * Covered cases:
 *
 * - Normalize the HTTP method to uppercase.
 * - Normalize the path with a leading slash and stripped surrounding slashes.
 * - any() returns a Group containing routes for all seven HTTP methods.
 * - map() returns a Group containing routes for the specified methods only.
 * - group() returns a Group with the given prefix and routes.
 * - withGroup() prepends the group prefix to the route path.
 * - withGroup() merges group middleware before route-level middleware.
 */
#[CoversClass(Route::class)]
final class RouteTest extends TestCase
{
    public function testConstructorNormalizesMethod(): void
    {
        $route = new Route('get', '/', fn() => null);

        $this->assertSame('GET', $route->getMethod());
    }

    public function testConstructorNormalizesPath(): void
    {
        $route = new Route('GET', 'users/profile', fn() => null);

        $this->assertSame('/users/profile', $route->getPath());
    }

    public function testConstructorTrimsPathSlashes(): void
    {
        $route = new Route('GET', '/users/', fn() => null);

        $this->assertSame('/users', $route->getPath());
    }

    public function testAnyReturnsAllMethods(): void
    {
        $group  = Route::any('/path', fn() => null);
        $routes = $group->getRoutes();

        $methods = array_map(fn($route) => $route->getMethod(), $routes);

        $this->assertCount(7, $routes);
        $this->assertEqualsCanonicalizing(
            ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD'],
            $methods,
        );
    }

    public function testMapReturnsSelectedMethods(): void
    {
        $group  = Route::map(['GET', 'POST'], '/path', fn() => null);
        $routes = $group->getRoutes();

        $methods = array_map(fn($route) => $route->getMethod(), $routes);

        $this->assertCount(2, $routes);
        $this->assertEqualsCanonicalizing(['GET', 'POST'], $methods);
    }

    public function testGroupReturnsConfiguredGroup(): void
    {
        $group = Route::group('/api', fn() => [Route::get('/users', fn() => null)]);

        $this->assertInstanceOf(Group::class, $group);
        $this->assertSame('/api', $group->getPrefix());
        $this->assertCount(1, $group->getRoutes());
    }

    public function testWithGroupAddsPrefix(): void
    {
        $route = Route::get('/users', fn() => null);
        $group = new Group('/api', fn() => []);

        $prefixed = $route->withGroup($group);

        $this->assertSame('/api/users', $prefixed->getPath());
    }

    public function testWithGroupKeepsOriginalRoute(): void
    {
        $route = Route::get('/users', fn() => null);
        $group = new Group('/api', fn() => []);

        $route->withGroup($group);

        $this->assertSame('/users', $route->getPath());
    }

    public function testWithGroupMergesMiddleware(): void
    {
        $groupMiddleware = ['group.auth'];
        $routeMiddleware = ['route.throttle'];

        $route = Route::get('/users', fn() => null, $routeMiddleware);
        $group = new Group('/api', fn() => [], $groupMiddleware);

        $merged = $route->withGroup($group)->getMiddleware();

        $this->assertSame(['group.auth', 'route.throttle'], $merged);
    }
}