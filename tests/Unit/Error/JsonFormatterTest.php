<?php

declare(strict_types=1);

namespace Zenigata\Http\Test\Unit\Error;

use RuntimeException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Zenigata\Http\Error\JsonFormatter;

use function json_decode;

/**
 * Unit test for {@see JsonFormatter}.
 *
 * Covered cases:
 * 
 * - Default state returns correct content type.
 * - Produces valid JSON in both debug and non-debug modes.
 * - Contains expected keys: message, and optionally type, file, line, trace.
 * - Properly encodes Unicode and slashes.
 * - Handles empty message.
 */
#[CoversClass(JsonFormatter::class)]
final class JsonFormatterTest extends TestCase
{
    private JsonFormatter $formatter;

    private RuntimeException $error;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->formatter = new JsonFormatter();
        $this->error = new RuntimeException('Custom error message');
    }

    public function testDefaults(): void
    {
        $contentTypes = $this->formatter->getContentTypes();

        $this->assertSame(['application/json'], $contentTypes);
    }

    public function testFormatWithoutDebug(): void
    {
        $output = $this->formatter->format($this->error, false);
        $data = json_decode($output, true);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('error', $data);
        $this->assertSame('Custom error message', $data['error']['message']);
        $this->assertArrayNotHasKey('type', $data['error']);
        $this->assertJson($output);
    }

    public function testFormatWithDebugIncludesDiagnostics(): void
    {
        $output = $this->formatter->format($this->error, true);
        $data = json_decode($output, true);

        $this->assertArrayHasKey('error', $data);
        $this->assertSame('Custom error message', $data['error']['message']);
        $this->assertArrayHasKey('type', $data['error']);
        $this->assertArrayHasKey('file', $data['error']);
        $this->assertArrayHasKey('line', $data['error']);
        $this->assertArrayHasKey('trace', $data['error']);
        $this->assertIsArray($data['error']['trace']);
        $this->assertJson($output);
    }

    public function testFormatHandlesUnicodeAndSlashes(): void
    {
        $error = new RuntimeException('Error in /test ✓');
        $output = $this->formatter->format($error, false);

        $this->assertStringContainsString('/test', $output);
        $this->assertStringContainsString('✓', $output);
    }

    public function testFormatHandlesEmptyMessage(): void
    {
        $output = $this->formatter->format(new RuntimeException(), false);
        $data = json_decode($output, true);

        $this->assertSame('', $data['error']['message']);
    }
}
