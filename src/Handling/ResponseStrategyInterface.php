<?php

declare(strict_types=1);

namespace Zenigata\Http\Handling;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Defines a contract for a response strategy.
 * 
 * Converts an handler result into a PSR-7 response,
 * based on the supported criteria.
 */
interface ResponseStrategyInterface
{
    /**
     * Returns the strategy name.
     *
     * @return string The strategy name.
     */
    public function getName(): string;

    /**
     * Indicates if the strategy can handle the result.
     *
     * @param ServerRequestInterface $request The incoming request.
     * @param mixed                  $data    The route handler result.
     * 
     * @return bool True if the strategy can handle the result.
     */
    public function supports(ServerRequestInterface $request, mixed $data): bool;

    /**
     * Generates a final response from the result.
     *
     * @param ServerRequestInterface $request The incoming request.
     * @param mixed                  $data    The route handler result.
     * 
     * @return ResponseInterface The generated response.
     */
    public function respond(ServerRequestInterface $request, mixed $data): ResponseInterface;
}