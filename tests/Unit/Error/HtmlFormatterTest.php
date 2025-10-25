<?php

declare(strict_types=1);

namespace Zenigata\Http\Test\Unit\Error;

use RuntimeException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Zenigata\Http\Error\HtmlFormatter;

use function str_contains;

/**
 * Unit test for {@see HtmlFormatter}.
 *
 * Covered cases:
 * 
 * - Default state returns correct content type.
 * - Uses default title unless overridden.
 * - Produces valid HTML structure.
 * - Escapes HTML characters in message.
 * - Includes diagnostics in debug mode (type, file, line, trace).
 * - Omits diagnostics in non-debug mode.
 * - Handles empty messages.
 */
#[CoversClass(HtmlFormatter::class)]
final class HtmlFormatterTest extends TestCase
{
    private HtmlFormatter $formatter;

    private RuntimeException $error;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->formatter = new HtmlFormatter();
        $this->error = new RuntimeException('Something <bad> happened & broke!');
    }

    public function testDefaults(): void
    {
        $this->assertSame(['text/html'], $this->formatter->getContentTypes());
    }

    public function testCustomTitleOverridesDefault(): void
    {
        $formatter = new HtmlFormatter('Custom Title');
        $output = $formatter->format($this->error, false);

        $this->assertStringContainsString('<title>Custom Title</title>', $output);
        $this->assertStringContainsString('<h1>Custom Title</h1>', $output);
    }

    public function testFormatWithoutDebugProducesMinimalHtml(): void
    {
        $output = $this->formatter->format($this->error, false);

        $this->assertStringContainsString('<!DOCTYPE html>', $output);
        $this->assertStringContainsString('<html>', $output);
        $this->assertStringContainsString('<body>', $output);
        $this->assertStringContainsString('Message:', $output);
        $this->assertStringNotContainsString('<h2>Trace</h2>', $output);
        $this->assertStringContainsString('&lt;bad&gt;', $output); // Escaped
        $this->assertStringContainsString('&amp;', $output); // Escaped
    }

    public function testFormatWithDebugIncludesDiagnostics(): void
    {
        $output = $this->formatter->format($this->error, true);

        $this->assertStringContainsString('Type:', $output);
        $this->assertStringContainsString('File:', $output);
        $this->assertStringContainsString('Line:', $output);
        $this->assertStringContainsString('<h2>Trace</h2>', $output);
        $this->assertStringContainsString('<pre>', $output);
    }

    public function testFormatHandlesEmptyMessage(): void
    {
        $error = new RuntimeException();
        $output = $this->formatter->format($error, false);

        $this->assertStringContainsString('<p><strong>Message:</strong>', $output);
    }

    public function testOutputLooksLikeValidHtml(): void
    {
        $output = $this->formatter->format($this->error, false);

        $this->assertTrue(str_contains($output, '<html>') && str_contains($output, '</html>'));
        $this->assertTrue(str_contains($output, '<head>') && str_contains($output, '</head>'));
        $this->assertTrue(str_contains($output, '<body>') && str_contains($output, '</body>'));
    }
}
