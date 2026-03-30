<?php

declare(strict_types=1);

namespace Zenigata\Http\Test\Unit\Error\Strategy;

use Exception;
use LogicException;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;
use Zenigata\Http\Error\HttpError;
use Zenigata\Http\Error\Strategy\AbstractErrorStrategy;

/**
 * Unit test for {@see Zenigata\Http\Error\Strategy\AbstractErrorStrategy}.
 *
 * Covered cases:
 *
 * - Throw LogicException from getName() when the strategy name is not defined.
 * - Return false from supports() when the Accept header is absent.
 * - Return true from supports() when the Accept header matches a content type.
 * - Return false from supports() when the Accept header does not match any content type.
 * - resolveMessage() returns the exception message in debug mode.
 * - resolveMessage() returns the default message when debug is disabled.
 * - resolveStatus() returns the code from an HttpError.
 * - resolveStatus() returns the code from a generic exception when it is a valid HTTP error code.
 * - resolveStatus() returns the default 500 when the code is not a valid HTTP error code.
 */
#[CoversClass(AbstractErrorStrategy::class)]
final class AbstractErrorStrategyTest extends TestCase
{
    private Exception $error;
    
    private AbstractErrorStrategy $strategy;

    private ServerRequest $request;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->error = new Exception('custom message');

        $this->strategy = new class extends AbstractErrorStrategy {
            protected string $name = 'test';

            protected array $contentTypes  = ['application/json'];

            public function respond(ServerRequestInterface $request, Throwable $error): ResponseInterface
            {
                throw new LogicException('Not implemented.');
            }
        };

        $this->request = new ServerRequest('GET', '/');
    }

    public function testSupportsMissingAccept(): void
    {
        $this->assertFalse($this->strategy->supports($this->request, $this->error));
    }

    public function testSupportsMatchingAcceptHeader(): void
    {
        $request = $this->request->withHeader('Accept', 'application/json');

        $this->assertTrue($this->strategy->supports($request, $this->error));
    }

    public function testSupportsNonMatchingAcceptHeader(): void
    {
        $request = $this->request->withHeader('Accept', 'text/html');

        $this->assertFalse($this->strategy->supports($request, $this->error));
    }

    public function testGetNameThrowsIfMissingName(): void
    {
        $unnamed = new class extends AbstractErrorStrategy {
            public function respond(ServerRequestInterface $request, Throwable $error): ResponseInterface
            {
                throw new LogicException('Not implemented.');
            }
        };

        $this->expectException(LogicException::class);

        $unnamed->getName();
    }

    public function testResolveMessageUsesExceptionInDebug(): void
    {
        $this->strategy->setDebug(true);

        $message = $this->strategy->resolveMessage($this->error);

        $this->assertSame('custom message', $message);
    }

    public function testResolveMessageUsesDefaultWithoutDebug(): void
    {
        $this->strategy->setDebug(false);

        $message = $this->strategy->resolveMessage($this->error);

        $this->assertSame('An internal error occurred.', $message);
    }

    public function testResolveStatusUsesHttpErrorCode(): void
    {
        $error = new HttpError($this->request, 404);

        $this->assertSame(404, $this->strategy->resolveStatus($error));
    }

    public function testResolveStatusUsesValidExceptionCode(): void
    {
        $error = new Exception('not found', 404);

        $this->assertSame(404, $this->strategy->resolveStatus($error));
    }

    public function testResolveStatusDefaultsOnInvalidCode(): void
    {
        $error = new Exception('invalid HTTP error code', 42);

        $this->assertSame(500, $this->strategy->resolveStatus($error));
    }
}