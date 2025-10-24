<?php

declare(strict_types=1);

namespace Zenigata\Http\Error;

use Exception;
use Throwable;
use Alexanderpas\Common\HTTP\ReasonPhrase;
use Alexanderpas\Common\HTTP\StatusCode;
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
        if (!$this->isHttpErrorCode($code)) {
            $code = 500;
        }

        $code = StatusCode::fromInteger($code)->value;

        if ($message === '') {
            $message = ReasonPhrase::fromInteger($code)->value;
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

    /**
     * Checks whether the given code is a valid HTTP error status (4xx–5xx).
     * See https://httpwg.org/specs/rfc9110.html#status.codes
     */
    private function isHttpErrorCode(int $code): bool
    {
        return $code >= 400 && $code <= 599;
    }
}