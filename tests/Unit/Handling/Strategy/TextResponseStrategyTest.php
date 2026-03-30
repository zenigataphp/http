<?php

declare(strict_types=1);

namespace Zenigata\Http\Test\Unit\Handling\Strategy;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Stringable;
use Zenigata\Http\Handling\Strategy\TextResponseStrategy;

use function print_r;

/**
 * Unit test for {@see Zenigata\Http\Handling\Strategy\TextResponseStrategy}.
 *
 * Covered cases:
 *
 * - Set the correct Content-Type header on the response.
 * - Write a string value directly to the body.
 * - Cast a Stringable object to string for the body.
 * - Cast a scalar value to string for the body.
 * - Serialize any other type via print_r for the body.
 */
#[CoversClass(TextResponseStrategy::class)]
final class TextResponseStrategyTest extends TestCase
{
    private TextResponseStrategy $strategy;

    private ServerRequest $request;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $factory = new Psr17Factory();

        $this->strategy = new TextResponseStrategy();
        $this->strategy->setResponseFactory($factory);
        $this->strategy->setStreamFactory($factory);

        $this->request = new ServerRequest('GET', '/');
    }

    public function testRespondSetsContentTypeHeader(): void
    {
        $response = $this->strategy->respond($this->request, '');

        $this->assertSame('text/plain; charset=utf-8', $response->getHeaderLine('Content-Type'));
    }

    public function testRespondHandlesString(): void
    {
        $response = $this->strategy->respond($this->request, 'hello');

        $this->assertSame('hello', (string) $response->getBody());
    }

    public function testRespondHandlesStringable(): void
    {
        $stringable = new class implements Stringable {
            public function __toString(): string
            {
                return 'from stringable';
            }
        };

        $response = $this->strategy->respond($this->request, $stringable);

        $this->assertSame('from stringable', (string) $response->getBody());
    }

    public function testRespondHandlesScalarValues(): void
    {
        $response = $this->strategy->respond($this->request, 42);

        $this->assertSame('42', (string) $response->getBody());
    }

    public function testRespondHandlesOtherTypesViaPrintR(): void
    {
        $data = ['a', 'b', 'c'];

        $response = $this->strategy->respond($this->request, $data);

        $this->assertSame(print_r($data, true), (string) $response->getBody());
    }
}