<?php

declare(strict_types=1);

namespace Zenigata\Http\Error;

use Exception;
use Throwable;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Represents an HTTP-specific error.
 *
 * Contains the original PSR-7 request that caused the error.
 * Validates HTTP status code during instantiation.
 */
final class HttpError extends Exception
{
    /**
     * List of standard HTTP error status codes
     * and their associated reason phrases.
     *
     * @var array<int,string>
     */
    public const ERROR_CODES = [
        // Client Error
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Time-out',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Large',
        415 => 'Unsupported Media Type',
        416 => 'Requested range not satisfiable',
        417 => 'Expectation Failed',
        418 => 'I\'m a teapot',
        421 => 'Misdirected Request',
        422 => 'Unprocessable Entity',
        423 => 'Locked',
        424 => 'Failed Dependency',
        425 => 'Unordered Collection',
        426 => 'Upgrade Required',
        428 => 'Precondition Required',
        429 => 'Too Many Requests',
        431 => 'Request Header Fields Too Large',
        444 => 'Connection Closed Without Response',
        451 => 'Unavailable For Legal Reasons',
        // Server Error
        499 => 'Client Closed Request',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Time-out',
        505 => 'HTTP Version not supported',
        506 => 'Variant Also Negotiates',
        507 => 'Insufficient Storage',
        508 => 'Loop Detected',
        510 => 'Not Extended',
        511 => 'Network Authentication Required',
        599 => 'Network Connect Timeout Error',
    ];

    /**
     * Creates a new HTTP error instance.
     *
     * Ensures the code is a valid HTTP error (4xx–5xx). If message is empty,
     * it uses the standard reason phrase for the status code.
     *
     * @param ServerRequestInterface $request  The request that caused the error.
     * @param int                    $code     HTTP status code (400–599).
     * @param string                 $message  Optional error message.
     * @param Throwable|null         $previous Optional previous exception.
     */
    public function __construct(
        private ServerRequestInterface $request,
        int $code = 500,
        string $message = '',
        ?Throwable $previous = null
    ) {
        if (!isset(self::ERROR_CODES[$code])) {
            $code = 500;
        }

        if ($message === '') {
            $message = self::ERROR_CODES[$code];
        }

        parent::__construct(
            message:  $message,
            code:     $code,
            previous: $previous
        );
    }

    /**
     * Returns the request that caused the error.
     *
     * @return ServerRequestInterface
     */
    public function getRequest(): ServerRequestInterface
    {
        return $this->request;
    }
}