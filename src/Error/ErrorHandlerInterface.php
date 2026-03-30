<?php

declare(strict_types=1);

namespace Zenigata\Http\Error;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Defines a contract for an HTTP error handler.
 *
 * Converts an exception into a PSR-7 response,
 * using the appropriate error strategy.
 */
interface ErrorHandlerInterface
{
    /**
     * Handles the exception raised during the HTTP lifecycle.
     *
     * @param ServerRequestInterface $request The incoming request.
     * @param Throwable              $error   The caught exception.
     *
     * @return ResponseInterface The generated error response.
     */
    public function handle(ServerRequestInterface $request, Throwable $error): ResponseInterface;

    /**
     * Adds an error strategy.
     *
     * @param ErrorStrategyInterface|string $strategy Error strategy, or resolvable string identifier.
     */
    public function addStrategy(ErrorStrategyInterface|string $strategy): void;

    /**
     * Returns the registered error strategies. 
     *
     * @return array<string,ErrorStrategyInterface> List of registered error strategies.
     */
    public function getStrategies(): array;

    /**
     * Returns the default error strategy.
     *
     * @return ErrorStrategyInterface The default error strategy.
     */
    public function getDefaultStrategy(): ErrorStrategyInterface;

    /**
     * Sets the default error strategy.
     *
     * @param string $name The name of the error strategy.
     */
    public function setDefaultStrategy(string $name): void;

    /**
     * Sets the logger instance.
     * 
     * @param LoggerInterface $logger The logger instance.
     */
    public function setLogger(LoggerInterface $logger): void;
}