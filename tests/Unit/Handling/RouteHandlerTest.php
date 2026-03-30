<?php

declare(strict_types=1);

namespace Zenigata\Http\Test\Unit\Handling;

use InvalidArgumentException;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zenigata\Http\Handling\RouteHandler;
use Zenigata\Http\Routing\RouteMatch;
use Zenigata\Http\Test\FakeHandlerNormalizer;
use Zenigata\Http\Test\FakeResponseStrategy;
use Zenigata\Utility\Testing\FakeContainer;

/**
 * Unit test for {@see Zenigata\Http\Handling\RouteHandler}.
 *
 * Covered cases:
 *
 * - Return a response produced by the handler directly, bypassing strategies.
 * - Delegate to the first matching strategy when the handler result is not a response.
 * - Fall back to the default strategy when no strategy supports the result.
 * - Register a strategy instance and make it available via getStrategies().
 * - Register a strategy resolvable from container or reflection.
 * - Throw InvalidArgumentException when a string strategy resolves to the wrong type.
 * - Configure a different default strategy.
 * - Throw InvalidArgumentException when the default strategy is not in the registered list.
 * - Propagate the container to the normalizer when it implements ContainerAwareInterface.
 */
#[CoversClass(RouteHandler::class)]
final class RouteHandlerTest extends TestCase
{
    private RouteMatch $route;

    private ServerRequest $request;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->route   = new RouteMatch('GET', '/', fn() => 'handler result');
        $this->request = new ServerRequest('GET', '/');
    }

    public function testHandleReturnsResponseDirectly(): void
    {
        $response = new Response(200);
        $route    = new RouteMatch('GET', '/', fn() => $response);

        $strategy = new FakeResponseStrategy(supports: false);

        $handler = new RouteHandler(
            strategies: [$strategy],
            defaultStrategy: 'fake',
            normalizer: new FakeHandlerNormalizer(),
        );

        $result = $handler->handle($this->request, $route);

        $this->assertSame($response, $result);
        $this->assertFalse($strategy->isInvoked());
    }

    public function testHandleUsesMatchingStrategy(): void
    {
        $strategy = new FakeResponseStrategy(supports: true);

        $handler = new RouteHandler(
            strategies: [$strategy],
            defaultStrategy: 'fake',
            normalizer: new FakeHandlerNormalizer(),
        );

        $handler->handle($this->request, $this->route);

        $this->assertTrue($strategy->isInvoked());
    }

    public function testHandleUsesDefaultStrategy(): void
    {
        $nonMatching     = new FakeResponseStrategy('other', supports: false);
        $defaultStrategy = new FakeResponseStrategy('default', supports: false);

        $handler = new RouteHandler(
            strategies: [$nonMatching, $defaultStrategy],
            defaultStrategy: 'default',
            normalizer: new FakeHandlerNormalizer(),
        );

        $handler->handle($this->request, $this->route);

        $this->assertFalse($nonMatching->isInvoked());
        $this->assertTrue($defaultStrategy->isInvoked());
    }

    public function testAddStrategy(): void
    {
        $handler = new RouteHandler();
        $handler->addStrategy(new FakeResponseStrategy());

        $this->assertArrayHasKey('fake', $handler->getStrategies());
    }

    public function testAddStrategyResolvesFromContainer(): void
    {
        $container  = new FakeContainer(['fake.strategy' => new FakeResponseStrategy()]);

        $handler = new RouteHandler();
        $handler->setContainer($container);
        $handler->addStrategy('fake.strategy');

        $this->assertArrayHasKey('fake', $handler->getStrategies());
    }

    public function testAddStrategyResolvesViaReflection(): void
    {
        $handler = new RouteHandler();
        $handler->addStrategy(FakeResponseStrategy::class);

        $this->assertArrayHasKey('fake', $handler->getStrategies());
    }

    public function testAddStrategyThrowsIfInvalidStrategy(): void
    {
        $container = new FakeContainer(['invalid.strategy' => new class {}]);

        $handler = new RouteHandler();
        $handler->setContainer($container);

        $this->expectException(InvalidArgumentException::class);

        $handler->addStrategy('invalid.strategy');
    }

    public function testSetDefaultStrategy(): void
    {
        $handler = new RouteHandler();
        $handler->addStrategy(new FakeResponseStrategy());

        $this->assertSame('text', $handler->getDefaultStrategy()->getName());

        $handler->setDefaultStrategy('fake');
        
        $this->assertSame('fake', $handler->getDefaultStrategy()->getName());
    }

    public function testSetDefaultStrategyThrowsIfUnknownStrategy(): void
    {
        $handler = new RouteHandler();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Unknown default strategy 'foo'");

        $handler->setDefaultStrategy('foo');
    }

    public function testConstructorThrowsUnknownDefaultStrategy(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new RouteHandler(
            strategies: [new FakeResponseStrategy('existing')],
            defaultStrategy: 'non-existent',
        );
    }

    public function testSetContainerPropagatesToNormalizer(): void
    {
        $container  = new FakeContainer();
        $normalizer = new FakeHandlerNormalizer();

        $handler = new RouteHandler(normalizer: $normalizer);
        $handler->setContainer($container);

        $this->assertSame($container, $normalizer->getContainer());
    }
}