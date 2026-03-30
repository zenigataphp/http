<?php

declare(strict_types=1);

namespace Zenigata\Http\Error;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * Defines a contract for an error strategy.
 * 
 * Converts an exception into a PSR-7 response,
 * based on the supported criteria.
 */
interface ErrorStrategyInterface
{
    /**
     * Returns the strategy name.
     *
     * @return string The strategy name.
     */
    public function getName(): string;

    /**
     * Indicates if the strategy can handle the error.
     *
     * @param ServerRequestInterface $request The incoming request.
     * @param Throwable              $error   The caught exception.
     * 
     * @return bool True if the strategy can handle the error.
     */
    public function supports(ServerRequestInterface $request, Throwable $error): bool;

    /**
     * Generates a final response from the error.
     *
     * @param ServerRequestInterface $request The incoming request.
     * @param Throwable              $error   The caught exception.
     * 
     * @return ResponseInterface The generated response.
     */
    public function respond(ServerRequestInterface $request, Throwable $error): ResponseInterface;
}