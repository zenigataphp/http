<?php

declare(strict_types=1);

namespace Zenigata\Http\Test\Unit\Middleware\BodyParser;

use Nyholm\Psr7\Stream;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Zenigata\Http\Middleware\BodyParser\UrlEncodedBodyParser;

/**
 * Unit test for {@see Zenigata\Http\Middleware\BodyParser\UrlEncodedBodyParser}.
 *
 * Covered cases:
 *
 * - Return an empty array for an empty body.
 * - Decode a valid URL encoded body into an associative array.
 * - Throw RuntimeException for an invalid URL encoded string.
 */
#[CoversClass(UrlEncodedBodyParser::class)]
final class UrlEncodedBodyParserTest extends TestCase
{
    private UrlEncodedBodyParser $parser;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->parser = new UrlEncodedBodyParser();
    }

    public function testParseEmptyBody(): void
    {
        $stream = Stream::create('');
        $result = $this->parser->parse($stream);

        $this->assertSame([], $result);
    }

    public function testParseValidUrlEncodedBody(): void
    {
        $stream = Stream::create('name=Alice&age=30');
        $result = $this->parser->parse($stream);

        $this->assertSame(['name' => 'Alice', 'age' => '30'], $result);
    }

    public function testParseThrowsInvalidUrlEncodedBody(): void
    {
        $this->expectException(RuntimeException::class);

        $this->parser->parse(Stream::create('&='));
    }
}