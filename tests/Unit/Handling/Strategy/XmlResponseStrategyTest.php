<?php

declare(strict_types=1);

namespace Zenigata\Http\Test\Unit\Handling\Strategy;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zenigata\Http\Handling\Strategy\XmlResponseStrategy;

/**
 * Unit test for {@see Zenigata\Http\Handling\Strategy\XmlResponseStrategy}.
 *
 * Covered cases:
 *
 * - Set the correct Content-Type header on the response.
 * - Render a scalar value as the content of the root XML element.
 * - Render an associative array as nested XML child elements.
 */
#[CoversClass(XmlResponseStrategy::class)]
final class XmlResponseStrategyTest extends TestCase
{
    private XmlResponseStrategy $strategy;
    
    private ServerRequest $request;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $factory = new Psr17Factory();

        $this->strategy = new XmlResponseStrategy();
        $this->strategy->setResponseFactory($factory);
        $this->strategy->setStreamFactory($factory);

        $this->request = new ServerRequest('GET', '/');
    }

    public function testRespondSetsContentTypeHeader(): void
    {
        $response = $this->strategy->respond($this->request, '');

        $this->assertSame('application/xml; charset=utf-8', $response->getHeaderLine('Content-Type'));
    }

    public function testRespondHandlesScalarValues(): void
    {
        $response = $this->strategy->respond($this->request, 'hello');

        $this->assertStringContainsString('<response>hello</response>', (string) $response->getBody());
    }

    public function testRespondHandlesArrayValues(): void
    {
        $response = $this->strategy->respond($this->request, [
            'name' => 'Alice',
            'age'  => '30',
        ]);

        $xml = (string) $response->getBody();

        $this->assertStringContainsString('<name>Alice</name>', $xml);
        $this->assertStringContainsString('<age>30</age>', $xml);
    }
}