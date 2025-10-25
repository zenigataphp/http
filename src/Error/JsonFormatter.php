<?php

declare(strict_types=1);

namespace Zenigata\Http\Error;

use Throwable;

use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

use function explode;
use function json_encode;

/**
 * Formats errors into a JSON object representation.
 *
 * Produces a JSON structure with the error message and code.
 */
final class JsonFormatter extends AbstractFormatter
{
    /**
     * {@inheritDoc}
     */
    protected array $contentTypes = [
        'application/json',
    ];

    /**
     * {@inheritDoc}
     */
    public function format(Throwable $error, bool $debug): string
    {
        $data = [
            'error' => [
                'message' => $error->getMessage(),
            ],
        ];

        if ($debug === true) {
            $data['error'] += [
                'type'  => $error::class,
                'file'  => $error->getFile(),
                'line'  => $error->getLine(),
                'trace' => explode("\n", $error->getTraceAsString()),
            ];
        }

        return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);   
    }
}