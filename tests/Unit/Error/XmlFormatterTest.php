<?php

declare(strict_types=1);

namespace Zenigata\Http\Test\Unit\Error;

use RuntimeException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use SimpleXMLElement;
use Zenigata\Http\Error\XmlFormatter;

use function str_contains;
use function simplexml_load_string;

/**
 * Unit test for {@see Zenigata\Http\Error\XmlFormatter}.
 *
 * Covered cases:
 * 
 * - Default state returns correct content types.
 * - Produces valid XML document.
 * - Escapes XML special characters.
 * - Includes diagnostics in debug mode (type, file, line, trace).
 * - Omits diagnostics in non-debug mode.
 * - Handles empty messages.
 */
#[CoversClass(XmlFormatter::class)]
final class XmlFormatterTest extends TestCase
{
    private XmlFormatter $formatter;

    private RuntimeException $error;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->formatter = new XmlFormatter();
        $this->error = new RuntimeException('Invalid <tag> & broken!');
    }

    public function testDefaults(): void
    {
        $this->assertSame(
            ['text/xml', 'application/xml', 'application/x-xml'],
            $this->formatter->contentTypes()
        );
    }

    public function testFormatWithoutDebugProducesMinimalXml(): void
    {
        $output = $this->formatter->format($this->error, false);

        $this->assertStringContainsString('<?xml version="1.0"', $output);
        $this->assertStringContainsString('<error>', $output);
        $this->assertStringContainsString('<message>', $output);
        $this->assertStringContainsString('&lt;tag&gt;', $output); // Escaped
        $this->assertStringContainsString('&amp;', $output); // Escaped
        $this->assertStringNotContainsString('<trace>', $output);

        $xml = simplexml_load_string($output);

        $this->assertInstanceOf(SimpleXMLElement::class, $xml); // Ensure it's valid XML
    }

    public function testFormatWithDebugIncludesDiagnostics(): void
    {
        $output = $this->formatter->format($this->error, true);

        $this->assertStringContainsString('<type>', $output);
        $this->assertStringContainsString('<code>', $output);
        $this->assertStringContainsString('<file>', $output);
        $this->assertStringContainsString('<line>', $output);
        $this->assertStringContainsString('<trace>', $output);

        $xml = simplexml_load_string($output);

        $this->assertInstanceOf(SimpleXMLElement::class, $xml);
        $this->assertSame('Invalid <tag> & broken!', (string) $xml->message);
    }

    public function testFormatHandlesEmptyMessage(): void
    {
        $output = $this->formatter->format(new RuntimeException(), false);
        $xml = simplexml_load_string($output);

        $this->assertSame('', (string) $xml->message);
    }

    public function testOutputLooksLikeValidXml(): void
    {
        $output = $this->formatter->format($this->error, false);

        $this->assertTrue(str_contains($output, '<error>') && str_contains($output, '</error>'));
        $this->assertTrue(str_contains($output, '<message>') && str_contains($output, '</message>'));
    }
}
