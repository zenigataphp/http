<?php

declare(strict_types=1);

namespace Zenigata\Http\Test\Unit\Error;

use Exception;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Http\Message\ServerRequestInterface;
use Zenigata\Http\Error\HttpError;

/**
 * Unit test for {@see Zenigata\Http\Error\HttpError}.
 *
 * Covered cases:
 * 
 * - Accepts valid HTTP error codes (4xx–5xx).
 * - Falls back to standard reason phrase if message is empty.
 * - Uses custom message if provided.
 * - Normalizes invalid codes (e.g., below 400 or above 599) to 500.
 * - Preserves the original PSR-7 request.
 * - Supports chaining via the previous exception.
 */
#[CoversClass(HttpError::class)]
final class HttpErrorTest extends TestCase
{
    private ServerRequestInterface $request;

    protected function setUp(): void
    {
        $this->request = new ServerRequest('GET', '/'); 
    }

    public function testCreatesWithValidClientErrorCode(): void
    {
        $error = new HttpError($this->request, 404);

        $this->assertInstanceOf(HttpError::class, $error);
        $this->assertSame(404, $error->getCode());
        $this->assertNotEmpty($error->getMessage());
        $this->assertStringContainsString('Not Found', $error->getMessage());
    }

    public function testCreatesWithValidServerErrorCode(): void
    {
        $error = new HttpError($this->request, 500);

        $this->assertSame(500, $error->getCode());
        $this->assertStringContainsString('Internal Server Error', $error->getMessage());
    }

    public function testCreatesWithCustomMessage(): void
    {
        $error = new HttpError($this->request, 500, 'Custom Message');

        $this->assertSame('Custom Message', $error->getMessage());
        $this->assertSame(500, $error->getCode());
    }

    public function testInvalidCodeDefaultsTo500(): void
    {
        $error = new HttpError($this->request, 200);

        $this->assertSame(500, $error->getCode());
        $this->assertStringContainsString('Internal Server Error', $error->getMessage());
    }

    public function testGetRequestReturnsOriginalRequest(): void
    {
        $error = new HttpError($this->request, 400);

        $this->assertSame($this->request, $error->getRequest());
        $this->assertInstanceOf(ServerRequestInterface::class, $error->getRequest());
    }

    public function testSupportsPreviousException(): void
    {
        $previous = new Exception('Previous Exception');
        $error = new HttpError($this->request, 502, '', $previous);

        $this->assertSame($previous, $error->getPrevious());
        $this->assertSame('Bad Gateway', $error->getMessage());
    }

    public function testEdgeCaseBelow400(): void
    {
        $error = new HttpError($this->request, 399);

        $this->assertSame(500, $error->getCode(), 'Codes below 400 should default to 500.');
    }

    public function testEdgeCaseAbove599(): void
    {
        $error = new HttpError($this->request, 600);

        $this->assertSame(500, $error->getCode(), 'Codes above 599 should default to 500.');
    }
}
