<?php

declare(strict_types=1);

namespace Zenigata\Http\Error;

use Throwable;

use const ENT_QUOTES;

use function htmlspecialchars;

/**
 * Formats errors into an HTML representation.
 *
 * Provides a minimal HTML template suitable for browser responses.
 */
final class HtmlFormatter implements FormatterInterface
{
    /**
     * Creates a new html formatter instance.
     *
     * @param string $title
     */
    public function __construct(
        private string $title = 'Something went wrong',
    ) {}

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

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{$this->title}</title>
    <style>html {font-family: sans-serif;}</style>
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
    <h1>{$this->title}</h1>
    <p>{$message}</p>
</body>
</html>
HTML;
    }

    /**
     * // TODO aggiungere una riga di spiegazione
     */
    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES);
    }
}
