<?php

declare(strict_types=1);

namespace Zenigata\Http\Test\Unit\Middleware\BodyParser;

use JsonException;
use Nyholm\Psr7\Stream;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zenigata\Http\Middleware\BodyParser\JsonBodyParser;

/**
 * Unit test for {@see Zenigata\Http\Middleware\BodyParser\JsonBodyParser}.
 *
 * Covered cases:
 *
 * - Return an empty array for an empty body when associative mode is enabled.
 * - Return null for an empty body when associative mode is disabled.
 * - Decode a valid JSON body into an associative array.
 * - Throw JsonException for malformed JSON.
 */
#[CoversClass(JsonBodyParser::class)]
final class JsonBodyParserTest extends TestCase
{
    private JsonBodyParser $parser;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->parser = new JsonBodyParser();
    }

    public function testParseEmptyBodyAsArray(): void
    {
        $stream = Stream::create('');
        $result = $this->parser->parse($stream);

        $this->assertSame([], $result);
    }

    public function testParseEmptyBodyAsNull(): void
    {
        $stream = Stream::create('');
        $parser = new JsonBodyParser(associative: false);

        $this->assertNull($parser->parse($stream));
    }

    public function testParseValidJson(): void
    {
        $stream = Stream::create('{"name":"Alice","age":30}');
        $result = $this->parser->parse($stream);

        $this->assertSame(['name' => 'Alice', 'age' => 30], $result);
    }

    public function testParseThrowsIfInvalidJson(): void
    {
        $this->expectException(JsonException::class);
        $this->expectExceptionMessage('Syntax error');

        $this->parser->parse(Stream::create('{invalid json}'));
    }
}