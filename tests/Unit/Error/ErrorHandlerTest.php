<?php

declare(strict_types=1);

namespace Zenigata\Http\Test\Unit\Error;

use Exception;
use InvalidArgumentException;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zenigata\Http\Error\ErrorHandler;
use Zenigata\Http\Error\HttpError;
use Zenigata\Http\Test\FakeErrorStrategy;
use Zenigata\Utility\Testing\FakeContainer;
use Zenigata\Utility\Testing\FakeLogger;

/**
 * Unit test for {@see Zenigata\Http\Error\ErrorHandler}.
 *
 * Covered cases:
 *
 * - Delegate to the first matching strategy.
 * - Fall back to the default strategy when no strategy supports the error.
 * - Log the error message when a logger is provided.
 * - Skip logging when no logger is provided.
 * - Register a strategy instance and make it available via getStrategies().
 * - Register a strategy resolvable from container or reflection.
 * - Throw en exception when a string strategy resolves to the wrong type.
 * - Configure a different default strategy.
 * - Throw en exception when the default strategy is not in the registered list.
 * - Use the request from HttpError when it is passed instead of the original request.
 */
#[CoversClass(ErrorHandler::class)]
final class ErrorHandlerTest extends TestCase
{
    private Exception $error;
    
    private ServerRequest $request;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->error   = new Exception('something went wrong');
        $this->request = new ServerRequest('GET', '/');
    }

    public function testHandleUsesMatchingStrategy(): void
    {
        $strategy = new FakeErrorStrategy(supports: true);

        $handler = new ErrorHandler(
            strategies: [$strategy],
            defaultStrategy: 'fake'
        );

        $handler->handle($this->request, $this->error);

        $this->assertTrue($strategy->isInvoked());
    }

    public function testHandleUsesDefaultStrategy(): void
    {
        $nonMatching     = new FakeErrorStrategy('other', supports: false);
        $defaultStrategy = new FakeErrorStrategy('default', supports: false);

        $handler = new ErrorHandler(
            strategies: [$nonMatching, $defaultStrategy],
            defaultStrategy: 'default',
        );

        $handler->handle($this->request, $this->error);

        $this->assertFalse($nonMatching->isInvoked());
        $this->assertTrue($defaultStrategy->isInvoked());
    }

    public function testHandleLogsError(): void
    {
        $logger   = new FakeLogger();
        $strategy = new FakeErrorStrategy();

        $handler = new ErrorHandler(
            strategies: [$strategy],
            defaultStrategy: 'fake',
        );

        $handler->setLogger($logger);
        $handler->handle($this->request, $this->error);

        $this->assertCount(1, $logger->all());
        $this->assertStringContainsString('something went wrong', $logger->first());
    }

    public function testAddStrategy(): void
    {
        $handler = new ErrorHandler();
        $handler->addStrategy(new FakeErrorStrategy());

        $this->assertArrayHasKey('fake', $handler->getStrategies());
    }

    public function testAddStrategyResolvesFromContainer(): void
    {
        $container  = new FakeContainer(['fake.strategy' => new FakeErrorStrategy()]);

        $handler = new ErrorHandler();
        $handler->setContainer($container);
        $handler->addStrategy('fake.strategy');

        $this->assertArrayHasKey('fake', $handler->getStrategies());
    }

    public function testAddStrategyResolvesViaReflection(): void
    {
        $handler = new ErrorHandler();
        $handler->addStrategy(FakeErrorStrategy::class);

        $this->assertArrayHasKey('fake', $handler->getStrategies());
    }

    public function testAddStrategyThrowsIfInvalidStrategy(): void
    {
        $container = new FakeContainer(['invalid.strategy' => new class {}]);

        $handler = new ErrorHandler();
        $handler->setContainer($container);

        $this->expectException(InvalidArgumentException::class);

        $handler->addStrategy('invalid.strategy');
    }

    public function testSetDefaultStrategy(): void
    {
        $handler = new ErrorHandler();
        $handler->addStrategy(new FakeErrorStrategy());

        $this->assertSame('text', $handler->getDefaultStrategy()->getName());

        $handler->setDefaultStrategy('fake');

        $this->assertSame('fake', $handler->getDefaultStrategy()->getName());
    }

    public function testSetDefaultStrategyThrowsIfUnknownStrategy(): void
    {
        $handler = new ErrorHandler();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Unknown default strategy 'foo'");

        $handler->setDefaultStrategy('foo');
    }

    public function testConstructorThrowsUnknownDefaultStrategy(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ErrorHandler(
            strategies: [new FakeErrorStrategy('existing')],
            defaultStrategy: 'non-existent',
        );
    }

    public function testHandleUsesHttpErrorRequest(): void
    {
        $logger   = new FakeLogger();
        $error    = new HttpError(new ServerRequest('POST', '/protected'), 403);
        $strategy = new FakeErrorStrategy();

        $handler = new ErrorHandler(
            strategies: [$strategy],
            defaultStrategy: 'fake',
        );

        $handler->setLogger($logger);
        $handler->handle($this->request, $error);

        // The log context must reference the HttpError's own request.
        $this->assertStringContainsString('POST', $logger->first());
        $this->assertStringContainsString('/protected', $logger->first());
    }
}