<?php

declare(strict_types=1);

namespace Zenigata\Http\Handling\Strategy;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use SimpleXMLElement;

use const ENT_QUOTES;
use const ENT_XML1;

use function htmlspecialchars;
use function is_array;
use function is_string;
use function is_object;

/**
 * XML response strategy.
 *
 * Creates an XML response from the result, if the content type is supported.
 */
class XmlResponseStrategy extends AbstractResponseStrategy
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
    public function respond(ServerRequestInterface $request, mixed $data): ResponseInterface
    {
        $document = new SimpleXMLElement('<response/>');

        $this->append($document, $data);

        $xml = $document->asXML() ?: '';

        $response = $this->getResponseFactory()->createResponse($this->status);

        return $response
            ->withHeader('Content-Type', "application/xml; charset={$this->charset}")
            ->withBody($this->getStreamFactory()->createStream($xml));
    }

    /**
     * Recursively appends data to the XML node.
     */
    protected function append(SimpleXMLElement $node, mixed $data): void
    {
        if (!is_array($data) && !is_object($data)) {
            $node[0] = $this->escape((string) $data);

            return;
        }

        foreach ((array) $data as $key => $value) {
            $child = $node->addChild(is_string($key) ? $key : 'item');

            $this->append($child, $value);
        }
    }

    /**
     * Encodes text for safe inclusion in XML nodes.
     */
    protected function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, $this->charset);
    }
}