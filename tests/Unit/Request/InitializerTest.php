<?php

declare(strict_types=1);

namespace Zenigata\Http\Test\Unit\Request;

use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Zenigata\Http\Request\Initializer;
use Zenigata\Http\Test\TestableInitializer;

use const UPLOAD_ERR_OK;

/**
 * Unit test for {@see Initializer}.
 *
 * Covered cases:
 * 
 * - Detection of URI (scheme, host, port, path, query).
 * - Header extraction and normalization (including Authorization quirk).
 * - Cookie parsing from header.
 * - Protocol version detection and fallback.
 * - Uploaded files normalization (single and nested).
 * - HTTPS and HTTP handling consistency.
 */
#[CoversClass(Initializer::class)]
final class InitializerTest extends TestCase
{
    private Initializer $initializer;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->initializer = new TestableInitializer();
    }

    public function testInitializeBuildsValidServerRequest(): void
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
            files:  []
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

    public function testDetectsProtocolVersionFallback(): void
    {
        $server = ['SERVER_PROTOCOL' => 'INVALID_PROTOCOL'];
        
        $request = $this->initializer->initialize(server: $server);

        $this->assertSame('1.1', $request->getProtocolVersion());
    }

    public function testDetectsGenericHttpVersion(): void
    {
        $server = ['SERVER_PROTOCOL' => 'HTTP/3'];
        
        $request = $this->initializer->initialize(server: $server);

        $this->assertSame('3', $request->getProtocolVersion());
    }

    public function testParsesCookieHeaderIntoArray(): void
    {
        $server = ['HTTP_COOKIE' => 'a=1; b=2; c=hello'];
        
        $request = $this->initializer->initialize(server: $server);

        $this->assertSame(['a' => '1', 'b' => '2', 'c' => 'hello'], $request->getCookieParams());
    }

    public function testHandlesRedirectHttpAuthorizationQuirk(): void
    {
        $server = [
            'REDIRECT_HTTP_AUTHORIZATION' => 'Bearer token-xyz',
            'SERVER_PROTOCOL'             => 'HTTP/1.1',
        ];

        $request = $this->initializer->initialize(server: $server);

        $this->assertSame('Bearer token-xyz', $request->getHeaderLine('Authorization'));
    }

    public function testSkipsBlacklistedProxyHeader(): void
    {
        $server = [
            'HTTP_PROXY'      => 'malicious-proxy',
            'SERVER_PROTOCOL' => 'HTTP/1.1',
        ];

        $request = $this->initializer->initialize(server: $server);

        $this->assertFalse($request->hasHeader('Proxy'));
    }

    public function testDetectsHttpsByPort(): void
    {
        $server = [
            'SERVER_PORT'     => 443,
            'REQUEST_METHOD'  => 'GET',
            'SERVER_PROTOCOL' => 'HTTP/2',
        ];

        $request = $this->initializer->initialize(server: $server);

        $this->assertSame('https', $request->getUri()->getScheme());
    }

    public function testHandlesMissingRequestUriGracefully(): void
    {
        $server = [
            'SERVER_PROTOCOL' => 'HTTP/1.0',
            'SERVER_NAME'     => 'localhost',
        ];

        $request = $this->initializer->initialize(server: $server);

        $this->assertSame('', $request->getUri()->getPath());
    }

    public function testNormalizeUploadedFilesSingle(): void
    {
        $root = vfsStream::setup('uploads');
        $file = vfsStream::newFile('upload.txt')->at($root)->setContent('file-content');

        $files = [
            'upload' => [
                'name'     => 'upload.txt',
                'type'     => 'text/plain',
                'tmp_name' => $file->url(),
                'error'    => UPLOAD_ERR_OK,
                'size'     => $file->size(),
            ],
        ];

        $request = $this->initializer->initialize(files: $files);

        $uploaded = $request->getUploadedFiles()['upload'];

        $this->assertSame('upload.txt', $uploaded->getClientFilename());
        $this->assertSame('text/plain', $uploaded->getClientMediaType());
        $this->assertSame('file-content', (string) $uploaded->getStream());
    }

    public function testNormalizeNestedUploadedFiles(): void
    {
        $root = vfsStream::setup('uploads');
        $file = vfsStream::newFile('nested.txt')->at($root)->setContent('nested-content');

        $files = [
            'docs' => [
                'name'     => ['a.txt'],
                'type'     => ['text/plain'],
                'tmp_name' => [$file->url()],
                'error'    => [UPLOAD_ERR_OK],
                'size'     => [$file->size()],
            ],
        ];

        $request = $this->initializer->initialize(files: $files);

        $uploaded = $request->getUploadedFiles()['docs'][0];

        $this->assertSame('a.txt', $uploaded->getClientFilename());
        $this->assertSame('text/plain', $uploaded->getClientMediaType());
        $this->assertSame('nested-content', (string) $uploaded->getStream());
    }
}
