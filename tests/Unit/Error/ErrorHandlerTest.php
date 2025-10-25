<?php

declare(strict_types=1);

namespace Zenigata\Http\Test\Unit\Error;

use LogicException;
use RuntimeException;
use Throwable;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Http\Message\ResponseInterface;
use Zenigata\Http\Error\ErrorHandler;
use Zenigata\Http\Error\HttpError;
use Zenigata\Http\Test\FakeFormatter;
use Zenigata\Utility\Psr\FakeLogger;

use function json_encode;

/**
 * Unit test for {@see ErrorHandler}.
 *
 * Covered cases:
 * 
 * - Registers valid formatters and rejects empty content types.
 * - Selects correct formatter based on Accept header.
 * - Falls back to default formatters when none registered.
 * - Handles both generic and HTTP-specific errors.
 * - Applies debug mode flag correctly.
 * - Produces valid PSR-7 responses with expected status, headers, and body.
 * - Logs contextual information if a logger is present.
 * - Edge cases: missing Accept header, empty formatters, invalid formatter.
 */
#[CoversClass(ErrorHandler::class)]
final class ErrorHandlerTest extends TestCase
{
    private RuntimeException $error;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->error = new RuntimeException('Custom error message');
    }

    public function testAddFormatterRejectsEmptyContentTypes(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('must declare at least one supported content type');

        $invalidFormatter = new FakeFormatter(
            types:  [],
            format: fn() => ''
        );

        new ErrorHandler([$invalidFormatter]);
    }

    public function testHandleWithCustomFormatterAndDebugOff(): void
    {
        $formatter = new FakeFormatter(
            types:  ['application/json'],
            format: function (Throwable $error) {
                return json_encode(['error' => $error->getMessage()]);
            }
        );

        $request = new ServerRequest('GET', '/', ['Accept' => 'application/json']);
        $handler = new ErrorHandler([$formatter], debug: false);

        $response = $handler->handle($this->error, $request);

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertStringContainsString('Internal Server Error', (string) $response->getBody());
    }

    public function testHandleWithDebugTruePreservesOriginalError(): void
    {
        $formatter = new FakeFormatter(
            types:  ['text/plain'],
            format: function (Throwable $error, bool $debug) {
                return $debug === true
                    ? 'DEBUG: ' . $error->getMessage()
                    : 'NO_DEBUG';
            }
        ); 

        $request = new ServerRequest('GET', '/', ['Accept' => 'text/plain']);
        $handler = new ErrorHandler([$formatter], debug: true);

        $response = $handler->handle($this->error, $request);

        $this->assertSame(500, $response->getStatusCode());
        $this->assertStringContainsString('DEBUG: Custom error message', (string) $response->getBody());
    }

    public function testHandleUsesHttpErrorRequestAndCode(): void
    {
        $formatter = new FakeFormatter(
            types:  ['application/json'],
            format: fn() => 'ok'
        );

        $httpError = new HttpError(new ServerRequest('POST', '/hello'), 404);
        $handler = new ErrorHandler([$formatter]);

        $response = $handler->handle($httpError, new ServerRequest('GET', '/ignored'));

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertSame('ok', (string) $response->getBody());
    }

    public function testHandleLogsErrorWhenLoggerProvided(): void
    {
        $formatter = new FakeFormatter(
            types:  ['text/htm'],
            format: fn() => '<html>Error</html>'
        );

        $logger = new FakeLogger();
        $handler = new ErrorHandler([$formatter], logger: $logger);
        $request = new ServerRequest('GET', '/test');

        $handler->handle($this->error, $request);

        $logs = $logger->all();

        $this->assertNotEmpty($logs);
        $this->assertStringContainsString('[ERROR]', $logs[0]);
        $this->assertStringContainsString('Custom error message', $logs[0]);
        $this->assertStringContainsString('"request_uri":"\/test"', $logs[0]);
    }

    public function testHandleFallsBackToDefaultFormatters(): void
    {
        $handler = new ErrorHandler();
        $request = new ServerRequest('GET', '/', ['Accept' => 'text/plain']);

        $response = $handler->handle($this->error, $request);

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertSame(500, $response->getStatusCode());
        $this->assertNotEmpty($response->getHeaderLine('Content-Type'));
        $this->assertNotEmpty((string) $response->getBody());
    }

    public function testDetectFormatterSelectsBestMatch(): void
    {
        $jsonFormatter = new FakeFormatter(
            types:  ['application/json'],
            format: fn() => '{}'
        );

        $xmlFormatter = new FakeFormatter(
            types:  ['application/xml'],
            format: fn() => '<xml />'
        );

        $handler = new ErrorHandler([$jsonFormatter, $xmlFormatter]);
        $request = new ServerRequest('GET', '/', ['Accept' => 'application/xml']);

        $response = $handler->handle($this->error, $request);

        $this->assertSame('application/xml', $response->getHeaderLine('Content-Type'));
        $this->assertStringContainsString('<xml', (string) $response->getBody());
    }

    public function testHandleDefaultsToFirstFormatterWhenNoAcceptHeader(): void
    {
        $formatterA = new FakeFormatter(
            types:  ['text/plain'],
            format: fn() => 'plain'
        );

        $formatterB = new FakeFormatter(
            types:  ['application/json'],
            format: fn() => 'json'
        );

        $handler = new ErrorHandler([$formatterA, $formatterB]);
        $response = $handler->handle($this->error, new ServerRequest('GET', '/'));

        $this->assertSame('text/plain', $response->getHeaderLine('Content-Type'));
        $this->assertSame('plain', (string) $response->getBody());
    }
}
