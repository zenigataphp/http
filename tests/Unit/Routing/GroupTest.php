<?php

declare(strict_types=1);

namespace Zenigata\Http\Test\Unit\Routing;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Http\Server\MiddlewareInterface;
use Zenigata\Http\Routing\Route;
use Zenigata\Http\Routing\Group;
use Zenigata\Utility\Psr\FakeMiddleware;

/**
 * Unit test for {@see Group}.
 * 
 * Covered cases:
 *
 * - Add a single route with prefix and middleware, path is updated and middleware merged.
 * - Add multiple routes, each receives prefix and middleware without affecting others.
 * - Preserve immutability of original routes when adding them to a group.
 * - Handle edge cases: empty prefix (paths unchanged) and stripping trailing slash in prefix.
 */
#[CoversClass(Group::class)]
final class GroupTest extends TestCase
{
    /**
     * Fake middleware stack under test.
     *
     * @var MiddlewareInterface[]
     */
    private array $middleware;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->middleware = [
            new FakeMiddleware(),
        ];
    }

    public function testAddSingleRoute(): void
    {
        $route = Route::get('/hello', 'handler', $this->middleware);
        $group = new Group('/api', [$route], $this->middleware);

        $routes = $group->getRoutes();

        $this->assertCount(1, $routes);
        $this->assertSame('/api/hello', $routes[0]->getPath());
        $this->assertCount(2, $routes[0]->getMiddleware());
    }

    public function testMultipleRoutesPrefixedAndEnriched(): void
    {
        $routes = [
            Route::get('/a', 'handlerA'),
            Route::post('/b', 'handlerB')
        ];

        $group = new Group('/api', $routes, $this->middleware);

        $routes = $group->getRoutes();

        $this->assertSame('/api/a', $routes[0]->getPath());
        $this->assertSame('/api/b', $routes[1]->getPath());

        $this->assertCount(1, $routes[0]->getMiddleware());
        $this->assertCount(1, $routes[1]->getMiddleware());
    }

    public function tetsOriginalRouteUnchangedAfterAdd(): void
    {
        $route = Route::get('/hello', 'handler', $this->middleware);

        $originalPath = $route->getPath();
        $originalMiddlewares = $route->getMiddleware();

        new Group('/api', [$route], $this->middleware);

        $this->assertSame($originalPath, $route->getPath());
        $this->assertSame($originalMiddlewares, $route->getMiddleware());
    }

    public function testEmptyPrefixGroup(): void
    {
        $route = Route::get('/hello', 'handler', $this->middleware);
        $group = new Group('', [$route]);

        $routes = $group->getRoutes();

        $this->assertSame('/hello', $routes[0]->getPath());
        $this->assertCount(1, $routes[0]->getMiddleware());
    }

    public function testHandleTrailingSlashInPrefix(): void
    {
        $route = Route::get('/hello', 'handler');
        $group = new Group('/api/', [$route]);

        $routes = $group->getRoutes();

        $this->assertSame('/api/hello', $routes[0]->getPath());
    }
}
