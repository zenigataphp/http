<?php

declare(strict_types=1);

namespace Zenigata\Http\Test\Unit\Handling;

use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Zenigata\Http\Handling\HandlerNormalizer;
use Zenigata\Http\Test\FakeController;
use Zenigata\Http\Test\FakeRequestHandler;
use Zenigata\Http\Test\FakeCallableHandler;
use Zenigata\Utility\Testing\FakeContainer;

/**
 * Unit test for {@see Zenigata\Http\Handling\HandlerNormalizer}.
 *
 * Covered cases:
 *
 * - Return a closure as-is.
 * - Return a callable object as-is.
 * - Wrap a RequestHandlerInterface into a callable that delegates to handle().
 * - Resolve a string service ID from the container.
 * - Resolve a FQCN via reflection when no container is provided.
 * - Normalize a [class, method] controller definition into a callable.
 * - Throw RuntimeException when the controller method does not exist.
 * - Throw RuntimeException when the handler type cannot be normalized.
 */
#[CoversClass(HandlerNormalizer::class)]
final class HandlerNormalizerTest extends TestCase
{
    private ServerRequest $request;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->request = new ServerRequest('GET', '/');
    }

    public function testNormalizeClosure(): void
    {
        $closure    = fn() => new Response();
        $normalizer = new HandlerNormalizer();

        $callable = $normalizer->normalize($closure);

        $this->assertSame($closure, $callable);
    }

    public function testNormalizeInvokableObject(): void
    {
        $invokable  = new FakeCallableHandler();
        $normalizer = new HandlerNormalizer();

        $callable = $normalizer->normalize($invokable);

        $this->assertSame($invokable, $callable);
    }

    public function testNormalizeRequestHandler(): void
    {
        $handler    = new FakeRequestHandler();
        $normalizer = new HandlerNormalizer();

        $callable = $normalizer->normalize($handler);
        $result   = $callable($this->request);

        $this->assertIsCallable($callable);
        $this->assertInstanceOf(ResponseInterface::class, $result);
    }

    public function testNormalizeResolvesFromContainer(): void
    {
        $container = new FakeContainer(['my.handler' => new FakeRequestHandler()]);
        $normalizer = new HandlerNormalizer();

        $normalizer->setContainer($container);

        $callable = $normalizer->normalize('my.handler');

        $this->assertIsCallable($callable);
    }

    public function testNormalizeResolvesViaReflection(): void
    {
        $normalizer = new HandlerNormalizer();
        
        // Instantiable without arguments
        $callable = $normalizer->normalize(FakeCallableHandler::class);

        $this->assertIsCallable($callable);
    }

    public function testNormalizeController(): void
    {
        $normalizer = new HandlerNormalizer();

        $callable = $normalizer->normalize([FakeController::class, 'handle']);
        $result   = $callable($this->request);

        $this->assertIsCallable($callable);
        $this->assertInstanceOf(ResponseInterface::class, $result);
    }

    public function testNormalizeControllerViaFromContainer(): void
    {
        $container  = new FakeContainer([FakeController::class => new FakeController()]);
        $normalizer = new HandlerNormalizer();

        $normalizer->setContainer($container);

        $callable = $normalizer->normalize([FakeController::class, 'handle']);

        $this->assertIsCallable($callable);
    }

    public function testThrowWhenControllerMethodDoesNotExist(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('nonExistentMethod');

        $normalizer = new HandlerNormalizer();

        $normalizer->normalize([FakeController::class, 'nonExistentMethod']);
    }

    public function testThrowForUnsupportedHandlerType(): void
    {
        $this->expectException(RuntimeException::class);

        $normalizer = new HandlerNormalizer();

        $normalizer->normalize(new class {});
    }
}