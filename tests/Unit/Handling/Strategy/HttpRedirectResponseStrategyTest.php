<?php

declare(strict_types=1);

namespace Zenigata\Http\Test\Unit\Handling\Strategy;

use InvalidArgumentException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zenigata\Http\Handling\Strategy\HttpRedirect;
use Zenigata\Http\Handling\Strategy\HttpRedirectResponseStrategy;

/**
 * Unit test for {@see Zenigata\Http\Handling\Strategy\HttpRedirectResponseStrategy}.
 *
 * Covered cases:
 *
 * - Return true from supports() only for HttpRedirect instances.
 * - Set the Location header from the HttpRedirect location.
 * - Use the status code from the HttpRedirect object.
 * - Forward additional headers from the HttpRedirect onto the response.
 * - Throw InvalidArgumentException for status codes outside the allowed redirect set.
 */
#[CoversClass(HttpRedirectResponseStrategy::class)]
final class HttpRedirectResponseStrategyTest extends TestCase
{
    private HttpRedirectResponseStrategy $strategy;

    private ServerRequest $request;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $factory = new Psr17Factory();

        $this->strategy = new HttpRedirectResponseStrategy();
        $this->strategy->setResponseFactory($factory);

        $this->request = new ServerRequest('GET', '/');
    }

    public function testSupportsHttpRedirect(): void
    {
        $this->assertTrue($this->strategy->supports($this->request, new HttpRedirect('/target')));
        $this->assertFalse($this->strategy->supports($this->request, '/target'));
        $this->assertFalse($this->strategy->supports($this->request, null));
    }

    public function testRespondSetsLocationHeader(): void
    {
        $response = $this->strategy->respond($this->request, new HttpRedirect('/target', 302));

        $this->assertSame('/target', $response->getHeaderLine('Location'));
    }

    public function testRespondUsesStatusCodeFromRedirect(): void
    {
        $response = $this->strategy->respond($this->request, new HttpRedirect('/target', 301));

        $this->assertSame(301, $response->getStatusCode());
    }

    public function testRespondForwardsAdditionalHeaders(): void
    {
        $redirect = new HttpRedirect('/target', 302, ['X-Custom' => 'value']);
        $response = $this->strategy->respond($this->request, $redirect);

        $this->assertSame('value', $response->getHeaderLine('X-Custom'));
    }

    public function testRespondThrowsIfInvalidStatusCode(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->strategy->respond($this->request, new HttpRedirect('/target', 200));
    }
}