<?php

declare(strict_types=1);

namespace Zenigata\Http;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Defines the contract for a runner that executes a PSR-15 based HTTP application.
 * 
 * Bootstraps the request/response lifecycle, and executes the configured handler
 * to produce and emit the final response.
 */
interface HttpRunnerInterface
{
    /**
     * Runs the full HTTP lifecycle.
     *
     * If no request is provided, it will be created from PHP globals.
     *
     * @param ServerRequestInterface|null $request Optional server request to handle.
     *
     * @return void
     */
    public function run(?ServerRequestInterface $request = null): void;
}