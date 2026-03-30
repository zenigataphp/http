<?php

declare(strict_types=1);

namespace Zenigata\Http\Test\Unit;

use ErrorException;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Zenigata\Http\Application;
use Zenigata\Http\Error\ErrorHandler;
use Zenigata\Http\Error\ErrorHandlerInterface;
use Zenigata\Http\Handling\RouteHandler;
use Zenigata\Http\Handling\RouteHandlerInterface;
use Zenigata\Http\Middleware\MiddlewareDispatcherInterface;
use Zenigata\Http\Routing\RouteMatch;
use Zenigata\Http\Routing\Router;
use Zenigata\Http\Routing\RouterInterface;
use Zenigata\Http\Runtime\HttpRunnerInterface;
use Zenigata\Http\Test\FakeErrorHandler;
use Zenigata\Http\Test\FakeErrorStrategy;
use Zenigata\Http\Test\FakeMiddleware;
use Zenigata\Http\Test\FakeMiddlewareDispatcher;
use Zenigata\Http\Test\FakeResponseStrategy;
use Zenigata\Http\Test\FakeRoute;
use Zenigata\Http\Test\TestableApplication;
use Zenigata\Utility\Testing\FakeContainer;

use const E_ALL;
use const E_USER_WARNING;

use function error_reporting;
use function file_exists;
use function file_put_contents;
use function restore_error_handler;
use function sys_get_temp_dir;
use function tempnam;
use function trigger_error;

/**
 * Unit test for {@see Zenigata\Http\Application}.
 *
 * Covered cases:
 *
 * - handle() dispatches through the middleware pipeline and returns the response.
 * - handle() enriches the request with route parameters as attributes.
 * - handle() attaches the RouteInfo attribute under ROUTE_ATTRIBUTE_NAME.
 * - handle() appends route-level middleware to the cloned dispatcher.
 * - handle() delegates to the error handler when an exception is thrown.
 * - handle() registers the shutdown handler only once across multiple calls.
 * - registerPhpErrorHandler() converts PHP warnings into ErrorException.
 * - registerPhpErrorHandler() ignores silenced PHP warnings.
 * - setContainer() propagates the container to all ContainerAware components.
 * - setDebug() propagates the debug flag to all DebugAware components.
 * - run() delegates to the HTTP runner.
 * - Load routes, middleware and strategies using configuration files.
 */
#[CoversClass(Application::class)]
final class ApplicationTest extends TestCase
{
    private ?string $file = null;
    
    private ServerRequest $request;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->request = new ServerRequest('GET', '/');
    }

    /**
     * @inheritDoc
     */
    protected function tearDown(): void
    {
        restore_error_handler();

        if ($this->file !== null && file_exists($this->file)) {
            unlink($this->file);
        }
    }

    public function testHandleReturnsResponse(): void
    {
        $response = $this->createApplication()->handle($this->request);

        $this->assertSame(204, $response->getStatusCode());
    }

    public function testHandleAddsRouteParameters(): void
    {
        $router = $this->createRouter(new RouteMatch('GET', '/', fn() => null, parameters: ['id' => '42']));
        $app    = $this->createApplication(router: $router);

        $app->handle($this->request);

        $this->assertSame('42', $app->getEnrichedRequest()->getAttribute('id'));
    }

    public function testHandleAddsRouteInfo(): void
    {
        $app = $this->createApplication();

        $app->handle($this->request);

        $this->assertNotNull($app->getEnrichedRequest()->getAttribute(Application::ROUTE_ATTRIBUTE_NAME));
    }

    public function testHandleAddsRouteMiddleware(): void
    {
        $middleware = new FakeMiddleware();
        $router     = $this->createRouter(new RouteMatch('GET', '/', fn() => null, middleware: [$middleware]));
        $app        = $this->createApplication(router: $router);

        $app->handle($this->request);

        $this->assertContains($middleware, $app->getPreparedDispatcher()->getMiddleware());
    }

    public function testHandleUsesErrorHandlerOnException(): void
    {
        $routeHandler = $this->createMock(RouteHandlerInterface::class);
        $routeHandler->method('handle')->willThrowException(new RuntimeException('error'));

        $app = $this->createApplication(routeHandler: $routeHandler, errorHandler: new FakeErrorHandler());

        $response = $app->handle($this->request);

        $this->assertSame(500, $response->getStatusCode());
    }

    public function testHandleRegistersShutdownOnce(): void
    {
        $app = $this->createApplication();

        $app->handle($this->request);
        $app->handle($this->request);

        $this->assertSame(1, $app->getShutdownRegistrationCount());
    }

    public function testPhpErrorHandlerThrowsOnWarnings(): void
    {
        error_reporting(E_ALL);

        $this->createApplication();

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage('test warning');

        trigger_error('test warning', E_USER_WARNING);
    }

    public function testPhpErrorHandlerIgnoresSilencedWarnings(): void
    {
        error_reporting(E_ALL);

        $this->createApplication();

        $result = @trigger_error('silenced warning', E_USER_WARNING);

        $this->assertTrue($result);
    }

    public function testSetContainerUpdatesComponents(): void
    {
        $container  = new FakeContainer();
        $dispatcher = new FakeMiddlewareDispatcher();

        $this->createApplication(dispatcher: $dispatcher)->setContainer($container);

        $this->assertSame($container, $dispatcher->getContainer());
    }

    public function testSetDebugUpdatesComponents(): void
    {
        $errorHandler = new FakeErrorHandler();

        $this->createApplication(errorHandler: $errorHandler)->setDebug(true);

        $this->assertSame(true, $errorHandler->isDebugEnabled());
    }

    public function testRunUsesRunner(): void
    {
        $runner = $this->createMock(HttpRunnerInterface::class);
        $runner->expects($this->once())->method('run');

        $this->createApplication(runner: $runner)->run($this->request);
    }

    public function testLoadMiddlewareFromConfigFile(): void
    {
        $file = $this->createConfigFile(FakeMiddleware::class);
        $app  = $this->createApplication();

        $app->loadMiddleware($file);

        $this->assertContains(FakeMiddleware::class, $app->getMiddleware());
    }

    public function testLoadRoutesFromConfigFile(): void
    {
        $file = $this->createConfigFile(FakeRoute::class);
        $app  = $this->createApplication(router: new Router());

        $app->loadRoutes($file);

        $routes = $app->getRoutes();

        $this->assertSame('/hello', $routes[0]->getPath());
    }

    public function testLoadErrorStrategiesFromConfigFile(): void
    {
        $file = $this->createConfigFile(FakeErrorStrategy::class);
        $app  = $this->createApplication(errorHandler: new ErrorHandler());

        $app->loadErrorStrategies($file);

        $this->assertArrayHasKey('fake', $app->getErrorStrategies());
    }

    public function testLoadResponseStrategiesFromConfigFile(): void
    {
        $file = $this->createConfigFile(FakeResponseStrategy::class);
        $app  = $this->createApplication(routeHandler: new RouteHandler());

        $app->loadResponseStrategies($file);

        $this->assertArrayHasKey('fake', $app->getResponseStrategies());
    }

    /**
     * Creates a new testable application instance.
     */
    private function createApplication(
        ?MiddlewareDispatcherInterface $dispatcher = null,
        ?RouterInterface $router = null,
        ?RouteHandlerInterface $routeHandler = null,
        ?ErrorHandlerInterface $errorHandler = null,
        ?HttpRunnerInterface $runner = null,
    ): TestableApplication {
        return new TestableApplication(
            dispatcher:   $dispatcher   ?? new FakeMiddlewareDispatcher(),
            router:       $router       ?? $this->createRouter(),
            routeHandler: $routeHandler ?? $this->createRouteHandler(),
            errorHandler: $errorHandler ?? new FakeErrorHandler(),
            runner:       $runner,
        );
    }

    /**
     * Creates a new mock router that returns a route match.
     */
    private function createRouter(?RouteMatch $match = null): RouterInterface
    {
        $router = $this->createMock(RouterInterface::class);
        $router->method('match')->willReturn($match ?? new RouteMatch('GET', '/', fn() => null));

        return $router;
    }

    /**
     * Creates a new mock route handler that returns an empty response.
     */
    private function createRouteHandler(): RouteHandlerInterface
    {
        $handler = $this->createMock(RouteHandlerInterface::class);
        $handler->method('handle')->willReturn(new Response(204));

        return $handler;
    }

    /**
     * Creates a temporary file for testing purpose.
     */
    private function createConfigFile(string $content): string
    {
        $this->file = tempnam(sys_get_temp_dir(), 'zenigata_test_');

        file_put_contents($this->file, "<?php\nreturn ['{$content}'];");

        return $this->file;
    }
}
