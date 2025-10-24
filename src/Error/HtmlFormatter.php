<?php

declare(strict_types=1);

namespace Zenigata\Http\Error;

use Throwable;

use const ENT_QUOTES;

use function htmlspecialchars;
use function implode;
use function nl2br;

/**
 * Formats errors into an HTML representation.
 *
 * Provides a minimal HTML template suitable for browser responses.
 */
final class HtmlFormatter implements FormatterInterface
{
    /**
     * The HTML page title common to all errors.
     *
     * @var string
     */
    private string $title;

    /**
     * Creates a new html formatter instance.
     *
     * @param string $title The HTML page title.
     */
    public function __construct(string $title = 'Something went wrong')
    {
        $this->title = $title;
    }

    /**
     * {@inheritDoc}
     */
    public function contentTypes(): array
    {
        return ['text/html'];   
    }

    /**
     * {@inheritDoc}
     */
    public function format(Throwable $error, bool $debug): string
    {
        $message = $this->escape($error->getMessage());
        
        $details = $debug === true
            ? $this->createDetails($error)
            : '';

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{$this->title}</title>
    <style>
        body { font-family: sans-serif; padding: 2rem; color: #333; }
        pre  { background: #f8f8f8; padding: 1rem; border-radius: 6px; }
    </style>
</head>
<body>
    <h1>{$this->title}</h1>
    <p><strong>Message:</strong> {$message}</p>
    {$details}
</body>
</html>
HTML;
    }

    /**
     * Generates the HTML markup for error details.
     */
    private function createDetails(Throwable $error): string
    {
        $details = [
            '<p><strong>Type:</strong> %s</p>',
            '<p><strong>File:</strong> %s</p>',
            '<p><strong>Line:</strong> %d</p>',
            '<h2>Trace</h2><pre>%s</pre>',
        ];

        return sprintf(
            implode("\n", $details),
            $this->escape($error::class),
            $this->escape($error->getFile()),
            $error->getLine(),
            nl2br($this->escape($error->getTraceAsString()))
        );
    }

    /**
     * Encodes text for safe inclusion in HTML output.
     */
    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES);
    }
}
