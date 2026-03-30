<?php

declare(strict_types=1);

namespace Zenigata\Http\Test\Unit\Handling;

use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Zenigata\Http\Handling\DefaultHandlerInvoker;

/**
 * Unit test for {@see Zenigata\Http\Handling\DefaultHandlerInvoker}.
 *
 * Covered cases:
 *
 * - Pass the request as the first argument to the handler.
 * - Spread provided parameters as named arguments.
 */
#[CoversClass(DefaultHandlerInvoker::class)]
final class DefaultHandlerInvokerTest extends TestCase
{
    private DefaultHandlerInvoker $invoker;

    private ServerRequest $request;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->invoker = new DefaultHandlerInvoker();
        $this->request = new ServerRequest('GET', '/');
    }

    public function testPassesRequestToHandler(): void
    {
        $received = null;

        $callable = function (ServerRequestInterface $request) use (&$received) {
            $received = $request;
        };
            
        $this->invoker->invoke($this->request, $callable);

        $this->assertSame($this->request, $received);
    }

    public function testSpreadsParametersAsNamedArguments(): void
    {
        $received   = null;

        $callable = function (ServerRequestInterface $request, string $id, int $page) use (&$received): void {
            $received = [
                'id'   => $id,
                'page' => $page,
            ];
        };

        $parameters = [
            'id'   => 'abc',
            'page' => 2,
        ];

        $this->invoker->invoke($this->request, $callable, $parameters);

        $this->assertSame($parameters, $received);
    }
}