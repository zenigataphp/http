<?php

declare(strict_types=1);

namespace Zenigata\Http\Test\Unit\Response;

use InvalidArgumentException;
use RuntimeException;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversTrait;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zenigata\Http\Response\ResponseBuilderTrait;

use function fopen;
use function tmpfile;

/**
 * Unit test for {@see ResponseBuilderTrait}.
 *
 * Verifies that the response builder utility correctly creates various types of PSR-7
 * {@see ResponseInterface} instances from different inputs, handling headers, status codes,
 * and content formats.
 *
 * Covered cases:
 * 
 * - Create responses from strings, streams, or resources with correct headers and status.  
 * - Shortcuts (`json()`, `html()`, `text()`, `xml()`, `empty()`) set proper `Content-Type` and body.  
 * - File responses set `Content-Disposition`, `Content-Length`, and preserve filenames (RFC 5987).
 * - Inline file responses set correct `Content-Disposition: inline` and preserve filename and size.  
 * - Redirects set `Location` header and only allow valid redirect codes.  
 * - Streams from strings/resources yield {@see StreamInterface}.
 * - JSON encoding errors throw {@see RuntimeException}.  
 */
#[CoversTrait(ResponseBuilderTrait::class)]
final class ResponseBuilderTraitTest extends TestCase
{
    /**
     * Anonymous class instance using {@see ResponseBuilderTrait}.
     * 
     * @var object
     */
    private object $instance;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->instance = new class implements RequestHandlerInterface {
            use ResponseBuilderTrait;

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                throw new RuntimeException('Not used in tests');
            }
        };
    }

    public function testCreateResponse(): void
    {
        $response = $this->instance->createResponse(200, 'hello');

        $this->assertSame('hello', (string) $response->getBody());
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testCreateStreamFromString(): void
    {
        $body = $this->instance->createStream('string content');

        $this->assertInstanceOf(StreamInterface::class, $body);
        $this->assertSame('string content', (string) $body);
    }

    public function testCreateStreamFromResource(): void
    {
        $root = vfsStream::setup('root');
        $file = vfsStream::newFile('resource.txt')->at($root)->setContent('resource content');
        
        $body = $this->instance->createStream(fopen($file->url(), 'rb'));

        $this->assertInstanceOf(StreamInterface::class, $body);
        $this->assertSame('resource content', (string) $body);
    }

    public function testCreateStreamWithInvalidType(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->instance->createStream(42); // Not string or resource
    }

    public function testJsonResponse(): void
    {
        $response = $this->instance->jsonResponse(['jsonResponse' => true]);

        $this->assertSame('{"jsonResponse":true}', (string) $response->getBody());
        $this->assertSame(['application/json'], $response->getHeader('Content-Type'));
    }

    public function testJsonEncodingFailure(): void
    {
        $this->expectException(RuntimeException::class);

        $this->instance->jsonResponse(tmpfile()); // Not serializable
    }

    public function testHtmlResponse(): void
    {
        $response = $this->instance->htmlResponse('<h1>html response</h1>');

        $this->assertSame(['text/html'], $response->getHeader('Content-Type'));
        $this->assertSame('<h1>html response</h1>', (string) $response->getBody());
    }

    public function testTextResponse(): void
    {
        $response = $this->instance->textResponse("text response");

        $this->assertSame(['text/plain'], $response->getHeader('Content-Type'));
        $this->assertSame('text response', (string) $response->getBody());
    }

    public function testXmlResponse(): void
    {
        $response = $this->instance->xmlResponse('<xml>xml response</xml>');

        $this->assertSame(['application/xml'], $response->getHeader('Content-Type'));
        $this->assertSame('<xml>xml response</xml>', (string) $response->getBody());
    }

    public function testEmptyResponse(): void
    {
        $response = $this->instance->emptyResponse(204);

        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame('', (string) $response->getBody());
    }

    public function testFileResponse(): void
    {
        $root = vfsStream::setup('root');
        $file = vfsStream::newFile('example.txt')->at($root)->setContent('file content');
        
        $response = $this->instance->fileResponse($file->url());
        
        $this->assertSame('file content', (string) $response->getBody());
        $this->assertSame(['attachment; filename="example.txt"; filename*=UTF-8\'\'example.txt'], $response->getHeader('Content-Disposition'));
        $this->assertSame([(string) $file->size()], $response->getHeader('Content-Length'));
    }

    public function testInlineFileResponse(): void
    {
        $root = vfsStream::setup('root');
        $file = vfsStream::newFile('inline.txt')->at($root)->setContent('inline content');

        $response = $this->instance->fileResponse($file->url(), disposition: 'inline');

        $this->assertSame('inline content', (string) $response->getBody());
        $this->assertSame(['inline; filename="inline.txt"; filename*=UTF-8\'\'inline.txt'], $response->getHeader('Content-Disposition'));
        $this->assertSame([(string) $file->size()], $response->getHeader('Content-Length'));
        $this->assertSame(['text/plain'], $response->getHeader('Content-Type'));  // Verifies MIME type is set
    }
    public function testRedirectResponse(): void
    {
        $response = $this->instance->redirectResponse('/redirect/url', 302);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame(['/redirect/url'], $response->getHeader('Location'));
    }

    public function testInvalidRedirectStatus(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->instance->redirectResponse('/invalid', 418); // Invalid status for redirect
    }
}