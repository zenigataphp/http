<?php

declare(strict_types=1);

namespace Zenigata\Http\Test\Unit\Middleware\BodyParser;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;
use Zenigata\Http\Middleware\BodyParser\AbstractBodyParser;

/**
 * Unit test for {@see Zenigata\Http\Middleware\BodyParser\AbstractBodyParser}.
 *
 * Covered cases:
 *
 * - Return true from supports() when the content type is in the supported list.
 * - Return false from supports() when the content type is not in the supported list.
 */
#[CoversClass(AbstractBodyParser::class)]
final class AbstractBodyParserTest extends TestCase
{
    private AbstractBodyParser $parser;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->parser = new class extends AbstractBodyParser {
            protected array $contentTypes = [
                'application/json',
                'application/ld+json',
            ];

            public function parse(StreamInterface $body): mixed
            {
                return null;
            }
        };
    }

    public function testSupportsRegisteredContentType(): void
    {
        $this->assertTrue($this->parser->supports('application/json'));
    }

    public function testSupportsUnregisteredContentType(): void
    {
        $this->assertFalse($this->parser->supports('text/plain'));
    }

    public function testSupportsExactMatch(): void
    {
        // Partial match not supported
        $this->assertFalse($this->parser->supports('application/json; charset=utf-8'));
    }
}