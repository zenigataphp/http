<?php

declare(strict_types=1);

namespace Zenigata\Http\Test\Unit\Error\Strategy;

use Exception;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zenigata\Http\Error\Strategy\JsonErrorStrategy;

use function json_decode;

/**
 * Unit test for {@see Zenigata\Http\Error\Strategy\JsonErrorStrategy}.
 *
 * Covered cases:
 *
 * - Set the correct Content-Type header.
 * - Include only the default message in the body when debug is disabled.
 * - Include full error details in the body when debug is enabled.
 * - Use the exception code as HTTP status when it is a valid HTTP error code.
 */
#[CoversClass(JsonErrorStrategy::class)]
final class JsonErrorStrategyTest extends TestCase
{
    private Exception $error;

    private JsonErrorStrategy $strategy;

    private ServerRequest $request;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->error = new Exception('custom message');

        $factory = new Psr17Factory();

        $this->strategy = new JsonErrorStrategy();
        $this->strategy->setResponseFactory($factory);
        $this->strategy->setStreamFactory($factory);

        $this->request = new ServerRequest('GET', '/');
    }

    public function testRespondSetsContentTypeHeader(): void
    {
        $response = $this->strategy->respond($this->request, $this->error);

        $this->assertSame('application/json; charset=utf-8', $response->getHeaderLine('Content-Type'));
    }

    public function testRespondUsesDefaultMessageWithoutDebug(): void
    {
        $this->strategy->setDebug(false);

        $response = $this->strategy->respond($this->request, $this->error);
        $body     = json_decode((string) $response->getBody(), true);

        $this->assertSame('An internal error occurred.', $body['error']['message']);
        $this->assertArrayNotHasKey('type', $body['error']);
    }

    public function testRespondIncludesFullDetailsInDebug(): void
    {
        $this->strategy->setDebug(true);

        $response = $this->strategy->respond($this->request, $this->error);
        $body     = json_decode((string) $response->getBody(), true);

        $this->assertSame('custom message', $body['error']['message']);
        $this->assertArrayHasKey('type', $body['error']);
        $this->assertArrayHasKey('trace', $body['error']);
    }

    public function testRespondUsesValidExceptionCodeAsStatus(): void
    {
        $error = new Exception('not found', 404);

        $response = $this->strategy->respond($this->request, $error);

        $this->assertSame(404, $response->getStatusCode());
    }
}