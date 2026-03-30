<?php

declare(strict_types=1);

namespace Zenigata\Http\Test\Unit\Handling\Strategy;

use LogicException;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zenigata\Http\Handling\Strategy\AbstractResponseStrategy;

/**
 * Unit test for {@see Zenigata\Http\Handling\Strategy\AbstractResponseStrategy}.
 *
 * Covered cases:
 *
 * - Return false from supports() when the Accept header is absent.
 * - Return true from supports() when the Accept header matches a content type.
 * - Return false from supports() when the Accept header does not match any content type.
 * - Throw LogicException when the strategy name is not defined.
 */
#[CoversClass(AbstractResponseStrategy::class)]
final class AbstractResponseStrategyTest extends TestCase
{
    private AbstractResponseStrategy $strategy;

    private ServerRequest $request;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->strategy = new class extends AbstractResponseStrategy {
            protected string $name = 'test';

            protected array $contentTypes = ['application/json'];

            public function respond(ServerRequestInterface $request, mixed $data): ResponseInterface
            {
                throw new LogicException('Not implemented.');
            }
        };

        $this->request = new ServerRequest('GET', '/');
    }

    public function testSupportsMissingAccept(): void
    {
        $this->assertFalse($this->strategy->supports($this->request, null));
    }

    public function testSupportsMatchingAcceptHeader(): void
    {
        $request = $this->request->withHeader('Accept', 'application/json');

        $this->assertTrue($this->strategy->supports($request, null));
    }

    public function testSupportsNonMatchingAcceptHeader(): void
    {
        $request = $this->request->withHeader('Accept', 'text/html');

        $this->assertFalse($this->strategy->supports($request, null));
    }

    public function testGetNameThrowsIfMissingName(): void
    {
        $strategy = new class extends AbstractResponseStrategy {
            public function respond(ServerRequestInterface $request, mixed $data): ResponseInterface
            {
                throw new LogicException('Not implemented.');
            }
        };

        $this->expectException(LogicException::class);

        $strategy->getName();
    }
}