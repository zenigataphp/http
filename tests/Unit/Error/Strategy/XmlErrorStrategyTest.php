<?php

declare(strict_types=1);

namespace Zenigata\Http\Test\Unit\Error\Strategy;

use Exception;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zenigata\Http\Error\Strategy\XmlErrorStrategy;

/**
 * Unit test for {@see Zenigata\Http\Error\Strategy\XmlErrorStrategy}.
 *
 * Covered cases:
 *
 * - Set the correct Content-Type header.
 * - Include only the default message in the body when debug is disabled.
 * - Include full error details in the body when debug is enabled.
 * - Use the exception code as HTTP status when it is a valid HTTP error code.
 */
#[CoversClass(XmlErrorStrategy::class)]
final class XmlErrorStrategyTest extends TestCase
{
    private Exception $error;
    
    private XmlErrorStrategy $strategy;

    private ServerRequest $request;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->error = new Exception('custom message');

        $factory = new Psr17Factory();

        $this->strategy = new XmlErrorStrategy();
        $this->strategy->setResponseFactory($factory);
        $this->strategy->setStreamFactory($factory);

        $this->request = new ServerRequest('GET', '/');
    }

    public function testRespondSetsContentTypeHeader(): void
    {
        $response = $this->strategy->respond($this->request, $this->error);

        $this->assertSame('application/xml; charset=utf-8', $response->getHeaderLine('Content-Type'));
    }

    public function testRespondUsesDefaultMessageWithoutDebug(): void
    {
        $this->strategy->setDebug(false);

        $response = $this->strategy->respond($this->request, $this->error);
        $body     = (string) $response->getBody();

        $this->assertStringContainsString('An internal error occurred.', $body);
        $this->assertStringNotContainsString('custom message', $body);
    }

    public function testRespondIncludesFullDetailsInDebug(): void
    {
        $this->strategy->setDebug(true);

        $response = $this->strategy->respond($this->request, $this->error);
        $body      = (string) $response->getBody();

        $this->assertStringContainsString('custom message', $body);
        $this->assertStringContainsString('<type>', $body);
    }

    public function testRespondUsesValidExceptionCodeAsStatus(): void
    {
        $error = new Exception('not found', 404);

        $response = $this->strategy->respond($this->request, $error);

        $this->assertSame(404, $response->getStatusCode());
    }
}