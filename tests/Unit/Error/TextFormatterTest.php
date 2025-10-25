<?php

declare(strict_types=1);

namespace Zenigata\Http\Test\Unit\Error;

use RuntimeException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Zenigata\Http\Error\TextFormatter;

/**
 * Unit test for {@see TextFormatter}.
 *
 * Covered cases:
 * 
 * - Default state returns correct content type.
 * - Formats message in non-debug mode (concise).
 * - Includes detailed information in debug mode (type, file, line, trace).
 * - Handles empty messages gracefully.
 */
#[CoversClass(TextFormatter::class)]
final class TextFormatterTest extends TestCase
{
    private TextFormatter $formatter;

    private RuntimeException $error;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->formatter = new TextFormatter();
        $this->error = new RuntimeException('Custom error message');
    }

    public function testDefaults(): void
    {
        $contentTypes = $this->formatter->getContentTypes();

        $this->assertSame(['text/plain'], $contentTypes);
    }

    public function testFormatWithoutDebug(): void
    {
        $output = $this->formatter->format($this->error, false);

        $this->assertStringContainsString('Message: Custom error message', $output);
        $this->assertStringNotContainsString('Type:', $output);
        $this->assertStringEndsWith("\n", $output);
    }

    public function testFormatWithDebug(): void
    {
        $output = $this->formatter->format($this->error, true);

        $this->assertStringContainsString('Message: Custom error message', $output);
        $this->assertStringContainsString('Type: RuntimeException', $output);
        $this->assertStringContainsString('File:', $output);
        $this->assertStringContainsString('Line:', $output);
        $this->assertStringContainsString('Trace:', $output);
    }

    public function testFormatHandlesEmptyMessage(): void
    {
        $output = $this->formatter->format(new RuntimeException(), false);
        
        $this->assertStringContainsString('Message: ', $output);
    }
}
