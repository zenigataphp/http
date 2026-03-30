<?php

declare(strict_types=1);

namespace Zenigata\Http\Error\Strategy;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

use const ENT_QUOTES;

use function htmlspecialchars;
use function nl2br;

/**
 * HTML error strategy.
 *
 * Creates an HTML response from the error, if the content type is supported.
 */
class HtmlErrorStrategy extends AbstractErrorStrategy
{
    /**
     * @inheritDoc
     */
    protected string $name = 'html';

    /**
     * @inheritDoc
     */
    protected array $contentTypes = [
        'text/html',
    ];

    /**
     * The HTML page title.
     */
    protected string $title = 'Something went wrong';

    /**
     * @inheritDoc
     */
    public function respond(ServerRequestInterface $request, Throwable $error): ResponseInterface
    {
        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="{$this->charset}">
    <title>{$this->title}</title>
    <style>
        body { font-family: sans-serif; margin: 0; padding: 2rem; color: #333; }
        pre  { background: #f8f8f8; margin: 0; padding: 1rem; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>{$this->title}</h1>
    {$this->serialize($error)}
</body>
</html>
HTML;

        $response = $this->getResponseFactory()->createResponse($this->resolveStatus($error));

        return $response
            ->withHeader('Content-Type', "text/html; charset={$this->charset}")
            ->withBody($this->getStreamFactory()->createStream($html));
    }

    /**
     * Converts the error to HTML.
     */
    protected function serialize(Throwable $error): string
    {
        $message = $this->resolveMessage($error);

        if ($this->debug === false) {
            return "<p><strong>Message:</strong> {$message}</p>";
        }

        $type  = $error::class;
        $code  = $error->getCode();
        $file  = $this->escape($error->getFile());
        $line  = $error->getLine();
        $trace = nl2br($this->escape($error->getTraceAsString()));

        return "<p><strong>Message:</strong> {$message}</p>"
             . "<p><strong>Type:</strong> {$type}</p>"
             . "<p><strong>Code:</strong> {$code}</p>"
             . "<p><strong>File:</strong> {$file}</p>"
             . "<p><strong>Line:</strong> {$line}</p>"
             . "<h2>Trace</h2><pre>{$trace}</pre>";
    }

    /**
     * Encodes text for safe inclusion in HTML output.
     */
    protected function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, $this->charset);
    }
}