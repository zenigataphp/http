<?php

declare(strict_types=1);

namespace Zenigata\Http\Test\Unit\Runtime;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Zenigata\Http\Runtime\RequestInitializer;
use Zenigata\Http\Test\TestableRequestInitializer;

use const UPLOAD_ERR_OK;

use function file_exists;
use function file_put_contents;
use function filesize;
use function sys_get_temp_dir;
use function tempnam;

/**
 * Unit test for {@see Zenigata\Http\Runtime\RequestInitializer}.
 *
 * Covered cases:
 *
 * - Build a complete server request from a full server array.
 * - Detect HTTPS from the HTTPS server variable.
 * - Detect HTTPS from SERVER_PORT 443.
 * - Fall back to HTTP when neither HTTPS condition applies.
 * - Detect protocol version from SERVER_PROTOCOL, falling back to 1.1.
 * - Parse cookies from the Cookie header into an associative array.
 * - Resolve Authorization from REDIRECT_HTTP_AUTHORIZATION as a fallback.
 * - Exclude blacklisted headers (HTTP_PROXY).
 * - Handle a missing REQUEST_URI gracefully.
 * - Normalize a single uploaded file into a PSR-7 UploadedFileInterface.
 * - Normalize nested uploaded files (multi-dimensional $_FILES structure).
 */
#[CoversClass(RequestInitializer::class)]
final class RequestInitializerTest extends TestCase
{
    private ?string $file = null;

    private RequestInitializer $initializer;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->initializer = new TestableRequestInitializer();
    }

    /**
     * @inheritDoc
     */
    protected function tearDown(): void
    {
        if ($this->file !== null && file_exists($this->file)) {
            unlink($this->file);
        }
    }

    public function testInitializeBuildsRequest(): void
    {
        $server = [
            'SERVER_PROTOCOL' => 'HTTP/1.1',
            'REQUEST_METHOD'  => 'POST',
            'REQUEST_URI'     => '/path/to/resource?foo=bar',
            'HTTP_HOST'       => 'example.com:8080',
            'CONTENT_TYPE'    => 'application/json',
            'HTTPS'           => 'on',
        ];

        $request = $this->initializer->initialize(
            server: $server,
            get:    ['foo' => 'bar'],
            post:   ['a' => 'b'],
            cookie: ['token' => 'xyz'],
            files:  [],
        );

        $this->assertInstanceOf(ServerRequestInterface::class, $request);
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('https', $request->getUri()->getScheme());
        $this->assertSame('example.com', $request->getUri()->getHost());
        $this->assertIsInt($request->getUri()->getPort());
        $this->assertSame('/path/to/resource', $request->getUri()->getPath());
        $this->assertSame('foo=bar', $request->getUri()->getQuery());
        $this->assertSame('application/json', $request->getHeaderLine('Content-Type'));
        $this->assertSame('1.1', $request->getProtocolVersion());
    }

    public function testDetectsHttpsFromServerFlag(): void
    {
        $request = $this->initializer->initialize(server: ['HTTPS' => 'on']);

        $this->assertSame('https', $request->getUri()->getScheme());
    }

    public function testDetectsHttpsFromPort(): void
    {
        $request = $this->initializer->initialize(server: ['SERVER_PORT' => 443]);

        $this->assertSame('https', $request->getUri()->getScheme());
    }

    public function testDefaultsToHttpScheme(): void
    {
        $request = $this->initializer->initialize(server: ['REQUEST_METHOD' => 'GET']);

        $this->assertSame('http', $request->getUri()->getScheme());
    }

    public function testDetectsProtocolVersion(): void
    {
        $request = $this->initializer->initialize(server: ['SERVER_PROTOCOL' => 'HTTP/3']);

        $this->assertSame('3', $request->getProtocolVersion());
    }

    public function testDefaultsProtocolVersion(): void
    {
        $request = $this->initializer->initialize(server: ['SERVER_PROTOCOL' => 'INVALID_PROTOCOL']);

        $this->assertSame('1.1', $request->getProtocolVersion());
    }

    public function testParsesCookieHeader(): void
    {
        $request = $this->initializer->initialize(server: ['HTTP_COOKIE' => 'a=1; b=2; c=hello']);

        $this->assertSame(['a' => '1', 'b' => '2', 'c' => 'hello'], $request->getCookieParams());
    }

    public function testUsesRedirectAuthorization(): void
    {
        $request = $this->initializer->initialize(server: ['REDIRECT_HTTP_AUTHORIZATION' => 'Bearer token-xyz']);

        $this->assertSame('Bearer token-xyz', $request->getHeaderLine('Authorization'));
    }

    public function testSkipsBlacklistedProxyHeader(): void
    {
        $request = $this->initializer->initialize(server: ['HTTP_PROXY' => 'malicious-proxy']);

        $this->assertFalse($request->hasHeader('Proxy'));
    }

    public function testHandlesMissingRequestUri(): void
    {
        $request = $this->initializer->initialize(server: ['SERVER_NAME' => 'localhost']);

        $this->assertSame('', $request->getUri()->getPath());
    }

    public function testNormalizesSingleUploadedFile(): void
    {
        $path = $this->createTestFile('file-content');

        $request = $this->initializer->initialize(files: [
            'upload' => [
                'name'     => 'upload.txt',
                'type'     => 'text/plain',
                'tmp_name' => $path,
                'error'    => UPLOAD_ERR_OK,
                'size'     => filesize($path),
            ],
        ]);

        $uploaded = $request->getUploadedFiles()['upload'];

        $this->assertSame('upload.txt', $uploaded->getClientFilename());
        $this->assertSame('text/plain', $uploaded->getClientMediaType());
        $this->assertSame('file-content', (string) $uploaded->getStream());
    }

    public function testNormalizesNestedUploadedFiles(): void
    {
        $path = $this->createTestFile('nested-content');

        $request = $this->initializer->initialize(files: [
            'docs' => [
                'name'     => ['a.txt'],
                'type'     => ['text/plain'],
                'tmp_name' => [$path],
                'error'    => [UPLOAD_ERR_OK],
                'size'     => [filesize($path)],
            ],
        ]);

        $uploaded = $request->getUploadedFiles()['docs'][0];

        $this->assertSame('a.txt', $uploaded->getClientFilename());
        $this->assertSame('text/plain', $uploaded->getClientMediaType());
        $this->assertSame('nested-content', (string) $uploaded->getStream());
    }

    /**
     * Creates a temporary file for testing purpose.
     */
    private function createTestFile(string $content): string
    {
        $this->file = tempnam(sys_get_temp_dir(), 'zenigata_test_');

        file_put_contents($this->file, $content);

        return $this->file;
    }
}