<?php

declare(strict_types=1);

namespace Zenigata\Http\Test\Unit\Handling\Strategy;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zenigata\Http\Handling\Strategy\JsonResponseStrategy;

/**
 * Unit test for {@see Zenigata\Http\Handling\Strategy\JsonResponseStrategy}.
 *
 * Covered cases:
 *
 * - Set the correct Content-Type header on the response.
 * - Encode the handler result as JSON in the response body.
 */
#[CoversClass(JsonResponseStrategy::class)]
final class JsonResponseStrategyTest extends TestCase
{
    private JsonResponseStrategy $strategy;

    private ServerRequest $request;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $factory = new Psr17Factory();

        $this->strategy = new JsonResponseStrategy();
        $this->strategy->setResponseFactory($factory);
        $this->strategy->setStreamFactory($factory);

        $this->request = new ServerRequest('GET', '/');
    }

    public function testRespondSetsContentTypeHeader(): void
    {
        $response = $this->strategy->respond($this->request, []);

        $this->assertSame('application/json; charset=utf-8', $response->getHeaderLine('Content-Type'));
    }

    public function testRespondHandlesJsonSerializables(): void
    {
        $response = $this->strategy->respond($this->request, [
            'id'   => 1,
            'name' => 'Alice',
        ]);

        $this->assertSame('{"id":1,"name":"Alice"}', (string) $response->getBody());
    }
}