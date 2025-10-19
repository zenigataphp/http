<?php

declare(strict_types=1);

namespace Zenigata\Http;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Defines the contract for a runner that executes a PSR-15 based HTTP application.
 *
 * Manages middleware registration, bootstraps the request/response lifecycle,
 * and executes the pipeline to produce and emit the final response.
 */
interface HttpRunnerInterface
{
    /**
     * Runs the HTTP lifecycle.
     *
     * Executes the configured middleware pipeline and emits the response.
     * If no request is provided, a {@see ServerRequestInterface} is created from PHP globals.
     * An optional request can be passed to override this, useful for testing or custom injection.
     *
     * @param ServerRequestInterface|null $request Optional server request to handle.
     *
     * @return void
     */
    public function run(?ServerRequestInterface $request = null): void;
}