<?php

declare(strict_types=1);

namespace Zenigata\Http\Test\Unit\Handling\Strategy;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SplFileInfo;
use Zenigata\Http\Handling\Strategy\FileResponseStrategy;

use function basename;
use function file_exists;
use function file_put_contents;
use function filesize;
use function sys_get_temp_dir;
use function tempnam;

/**
 * Unit test for {@see Zenigata\Http\Handling\Strategy\FileResponseStrategy}.
 *
 * Covered cases:
 *
 * - Return true from supports() only for SplFileInfo instances.
 * - Set the correct Content-Type, Content-Disposition, and Content-Length headers.
 * - Write the file contents to the response body.
 * - Throw RuntimeException when the file is not readable.
 */
#[CoversClass(FileResponseStrategy::class)]
final class FileResponseStrategyTest extends TestCase
{
    private string $file;

    private SplFileInfo $data;

    private FileResponseStrategy $strategy;

    private ServerRequest $request;
    
    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->file = $this->createTestFile('file content');
        $this->data = new SplFileInfo($this->file);
        
        $factory = new Psr17Factory();

        $this->strategy = new FileResponseStrategy();
        $this->strategy->setResponseFactory($factory);
        $this->strategy->setStreamFactory($factory);
        
        $this->request = new ServerRequest('GET', '/');
    }

    /**
     * @inheritDoc
     */
    protected function tearDown(): void
    {
        if (file_exists($this->file)) {
            unlink($this->file);
        }
    }

    public function testSupportsSplFileInfo(): void
    {
        $this->assertTrue($this->strategy->supports($this->request, $this->data));
        $this->assertFalse($this->strategy->supports($this->request, 'not-a-file'));
        $this->assertFalse($this->strategy->supports($this->request, null));
    }

    public function testRespondSetsContentTypeHeader(): void
    {
        $response = $this->strategy->respond($this->request, $this->data);

        $this->assertNotEmpty($response->getHeaderLine('Content-Type'));
    }

    public function testRespondSetsContentLengthHeader(): void
    {
        $response = $this->strategy->respond($this->request, $this->data);

        $this->assertSame((string) filesize($this->file), $response->getHeaderLine('Content-Length'));
    }

    public function testRespondSetsContentDispositionHeader(): void
    {
        $response = $this->strategy->respond($this->request, $this->data);

        $this->assertStringContainsString(basename($this->file), $response->getHeaderLine('Content-Disposition'));
    }

    public function testRespondWritesFileContentsToBody(): void
    {
        $response = $this->strategy->respond($this->request, $this->data);

        $this->assertSame('file content', (string) $response->getBody());
    }

    public function testRespondThrowsWhenFileIsNotReadable(): void
    {
        $this->expectException(RuntimeException::class);

        $this->strategy->respond($this->request, new SplFileInfo('/non/existent/file.txt'));
    }

    /**
     * Creates a temporary file for testing purpose.
     */
    private function createTestFile(string $content): string
    {
        $file = tempnam(sys_get_temp_dir(), 'zenigata_test_');

        file_put_contents($file, $content);

        return $file;
    }
}