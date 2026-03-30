<?php

declare(strict_types=1);

namespace Zenigata\Http\Test\Unit\Routing;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zenigata\Http\Routing\Group;
use Zenigata\Http\Routing\Route;

/**
 * Unit test for {@see Zenigata\Http\Routing\Group}.
 *
 * Covered cases:
 *
 * - Normalize the prefix with a leading slash and stripped surrounding slashes.
 * - getRoutes() invokes the callable and returns its result.
 * - getRoutes() throws InvalidArgumentException when the callable does not return an array.
 * - getMiddleware() returns the middleware stack.
 */
#[CoversClass(Group::class)]
final class GroupTest extends TestCase
{
    public function testConstructorNormalizesPrefix(): void
    {
        $group = new Group('api', fn() => []);

        $this->assertSame('/api', $group->getPrefix());
    }

    public function testConstructorTrimsPrefixSlashes(): void
    {
        $group = new Group('/api/', fn() => []);

        $this->assertSame('/api', $group->getPrefix());
    }

    public function testGetRoutesReturnsCallableResult(): void
    {
        $route = Route::get('/users', fn() => null);
        $group = new Group('/api', fn() => [$route]);

        $this->assertSame([$route], $group->getRoutes());
    }

    public function testGetRoutesThrowsIfInvalidResult(): void
    {
        $group = new Group('/api', fn() => 'not an array');

        $this->expectException(InvalidArgumentException::class);

        $group->getRoutes();
    }

    public function testGetMiddlewareReturnsStack(): void
    {
        $middleware = ['auth', 'throttle'];
        $group      = new Group('/api', fn() => [], $middleware);

        $this->assertSame($middleware, $group->getMiddleware());
    }
}