<?php

declare(strict_types=1);

namespace Zenigata\Http\Test\Unit\Middleware\BodyParser;

use Nyholm\Psr7\Stream;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SimpleXMLElement;
use Zenigata\Http\Middleware\BodyParser\XmlBodyParser;

/**
 * Unit test for {@see Zenigata\Http\Middleware\BodyParser\XmlBodyParser}.
 *
 * Covered cases:
 *
 * - Return null for an empty body.
 * - Return a SimpleXMLElement for a valid XML body.
 * - Throw RuntimeException for malformed XML.
 */
#[CoversClass(XmlBodyParser::class)]
final class XmlBodyParserTest extends TestCase
{
    private XmlBodyParser $parser;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->parser = new XmlBodyParser();
    }

    public function testParseEmptyXmlBody(): void
    {
        $stream = Stream::create('');
        $result = $this->parser->parse($stream);

        $this->assertNull($result);
    }

    public function testParseValidXml(): void
    {
        $stream = Stream::create('<root><name>Alice</name></root>');
        $result = $this->parser->parse($stream);

        $this->assertInstanceOf(SimpleXMLElement::class, $result);
        $this->assertSame('Alice', (string) $result->name);
    }

    public function testParseThrowsInvalidXml(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Malformed XML:');

        $this->parser->parse(Stream::create('<unclosed>'));
    }
}