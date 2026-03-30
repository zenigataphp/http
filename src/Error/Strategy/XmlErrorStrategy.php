<?php

declare(strict_types=1);

namespace Zenigata\Http\Error\Strategy;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

use const ENT_QUOTES;
use const ENT_XML1;

use function htmlspecialchars;

/**
 * XML error strategy.
 *
 * Creates an XML response from the error, if the content type is supported.
 */
class XmlErrorStrategy extends AbstractErrorStrategy
{
    /**
     * @inheritDoc
     */
    protected string $name = 'xml';

    /**
     * @inheritDoc
     */
    protected array $contentTypes = [
        'application/xml',
        'text/xml',
    ];

    /**
     * @inheritDoc
     */
    public function respond(ServerRequestInterface $request, Throwable $error): ResponseInterface
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<error>
    {$this->serialize($error)}
</error>
XML;

        $response = $this->getResponseFactory()->createResponse($this->resolveStatus($error));

        return $response
            ->withHeader('Content-Type', "application/xml; charset={$this->charset}")
            ->withBody($this->getStreamFactory()->createStream($xml));
    }

    /**
     * Converts the error to XML.
     */
    protected function serialize(Throwable $error): string
    {
        $message = $this->resolveMessage($error);

        if ($this->debug === false) {
            return "<message>{$message}</message>";
        }

        $type  = $error::class;
        $code  = $error->getCode();
        $file  = $this->escape($error->getFile());
        $line  = $error->getLine();
        $trace = $error->getTraceAsString();

        return "<message>{$message}</message>"
             . "<type>{$type}</type>"
             . "<code>{$code}</code>"
             . "<file>{$file}</file>"
             . "<line>{$line}</line>"
             . "<trace><![CDATA[{$trace}]]></trace>";
    }

    /**
     * Encodes text for safe inclusion in XML nodes.
     */
    protected function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, $this->charset);
    }
}