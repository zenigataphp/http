<?php

declare(strict_types=1);

namespace Zenigata\Http\Test\Unit\Handler;

use InvalidArgumentException;
use RuntimeException;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;
use Zenigata\Http\Handler\ResponseBuilder;

use function fopen;
use function tmpfile;

/**
 * Unit test for {@see Zenigata\Http\Handler\ResponseBuilder}.
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
#[CoversClass(ResponseBuilder::class)]
final class ResponseBuilderTest extends TestCase
{
    private ResponseBuilder $responseBuilder;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->responseBuilder = new ResponseBuilder();
    }

    public function testCreateResponse(): void
    {
        $response = $this->responseBuilder->createResponse(200, 'hello');

        $this->assertSame('hello', (string) $response->getBody());
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testCreateStreamFromString(): void
    {
        $body = $this->responseBuilder->createStream('string content');

        $this->assertInstanceOf(StreamInterface::class, $body);
        $this->assertSame('string content', (string) $body);
    }

    public function testCreateStreamFromResource(): void
    {
        $root = vfsStream::setup('root');
        $file = vfsStream::newFile('resource.txt')->at($root)->setContent('resource content');
        
        $body = $this->responseBuilder->createStream(fopen($file->url(), 'rb'));

        $this->assertInstanceOf(StreamInterface::class, $body);
        $this->assertSame('resource content', (string) $body);
    }

    public function testCreateStreamWithInvalidType(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->responseBuilder->createStream(42); // Not string or resource
    }

    public function testJsonResponse(): void
    {
        $response = $this->responseBuilder->jsonResponse(['jsonResponse' => true]);

        $this->assertSame('{"jsonResponse":true}', (string) $response->getBody());
        $this->assertSame(['application/json'], $response->getHeader('Content-Type'));
    }

    public function testJsonEncodingFailure(): void
    {
        $this->expectException(RuntimeException::class);

        $this->responseBuilder->jsonResponse(tmpfile()); // Not serializable
    }

    public function testHtmlResponse(): void
    {
        $response = $this->responseBuilder->htmlResponse('<h1>html response</h1>');

        $this->assertSame(['text/html'], $response->getHeader('Content-Type'));
        $this->assertSame('<h1>html response</h1>', (string) $response->getBody());
    }

    public function testTextResponse(): void
    {
        $response = $this->responseBuilder->textResponse("text response");

        $this->assertSame(['text/plain'], $response->getHeader('Content-Type'));
        $this->assertSame('text response', (string) $response->getBody());
    }

    public function testXmlResponse(): void
    {
        $response = $this->responseBuilder->xmlResponse('<xml>xml response</xml>');

        $this->assertSame(['application/xml'], $response->getHeader('Content-Type'));
        $this->assertSame('<xml>xml response</xml>', (string) $response->getBody());
    }

    public function testEmptyResponse(): void
    {
        $response = $this->responseBuilder->emptyResponse(204);

        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame('', (string) $response->getBody());
    }

    public function testFileResponse(): void
    {
        $root = vfsStream::setup('root');
        $file = vfsStream::newFile('example.txt')->at($root)->setContent('file content');
        
        $response = $this->responseBuilder->fileResponse($file->url());
        
        $this->assertSame('file content', (string) $response->getBody());
        $this->assertSame(['attachment; filename="example.txt"; filename*=UTF-8\'\'example.txt'], $response->getHeader('Content-Disposition'));
        $this->assertSame([(string) $file->size()], $response->getHeader('Content-Length'));
    }

    public function testInlineFileResponse(): void
    {
        $root = vfsStream::setup('root');
        $file = vfsStream::newFile('inline.txt')->at($root)->setContent('inline content');

        $response = $this->responseBuilder->fileResponse($file->url(), disposition: 'inline');

        $this->assertSame('inline content', (string) $response->getBody());
        $this->assertSame(['inline; filename="inline.txt"; filename*=UTF-8\'\'inline.txt'], $response->getHeader('Content-Disposition'));
        $this->assertSame([(string) $file->size()], $response->getHeader('Content-Length'));
        $this->assertSame(['text/plain'], $response->getHeader('Content-Type'));  // Verifies MIME type is set
    }
    public function testRedirectResponse(): void
    {
        $response = $this->responseBuilder->redirectResponse('/redirect/url', 302);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame(['/redirect/url'], $response->getHeader('Location'));
    }

    public function testInvalidRedirectStatus(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->responseBuilder->redirectResponse('/invalid', 418); // Invalid status for redirect
    }
}