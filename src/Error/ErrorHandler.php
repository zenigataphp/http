<?php

declare(strict_types=1);

namespace Zenigata\Http\Error;

use Http\Discovery\Psr17FactoryDiscovery;
use InvalidArgumentException;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Throwable;
use Zenigata\Http\Error\Strategy\HtmlErrorStrategy;
use Zenigata\Http\Error\Strategy\JsonErrorStrategy;
use Zenigata\Http\Error\Strategy\TextErrorStrategy;
use Zenigata\Http\Error\Strategy\XmlErrorStrategy;
use Zenigata\Utility\Awareness\ContainerAwareInterface;
use Zenigata\Utility\Awareness\ContainerAwareTrait;
use Zenigata\Utility\Awareness\DebugAwareInterface;
use Zenigata\Utility\Awareness\DebugAwareTrait;
use Zenigata\Utility\Awareness\ResponseFactoryAwareInterface;
use Zenigata\Utility\Awareness\StreamFactoryAwareInterface;
use Zenigata\Utility\Helper\ReflectionResolver;

use function array_keys;
use function implode;
use function is_string;
use function sprintf;

/**
 * Implementation of {@see Zenigata\Http\Error\ErrorHandlerInterface}.
 *
 * Supports configurable logging and debug mode.
 * 
 * If no error strategy is configured, the defaults will be used:
 * HTML, JSON, XML, text.
 */
class ErrorHandler implements ErrorHandlerInterface, ContainerAwareInterface, DebugAwareInterface
{
    use ContainerAwareTrait;
    use DebugAwareTrait;
    use LoggerAwareTrait;

    /**
     * List of registered error strategies.
     *
     * @var array<string,ErrorStrategyInterface>
     */
    private array $strategies = [];

    /**
     * The default error strategy, if no more specific one is found.
     */
    private ErrorStrategyInterface $defaultStrategy;

    /**
     * Creates a new error handler instance.
     *
     * @param list<ErrorStrategyInterface>  $strategies      List of strategies used to create responses.
     * @param string                        $defaultStrategy The default strategy to use.
     * @param ResponseFactoryInterface|null $responseFactory The response factory, automatically detected if not provided.
     * @param StreamFactoryInterface|null   $streamFactory   The stream factory, automatically detected if not provided.
     */
    public function __construct(
        array $strategies = [],
        string $defaultStrategy = 'text',
        private ?ResponseFactoryInterface $responseFactory = null,
        private ?StreamFactoryInterface $streamFactory = null,
    ) {
        if ($strategies === []) {
            $strategies = self::defaultStrategies();
        }

        foreach ($strategies as $strategy) {
            $this->addStrategy($strategy);
        }

        $this->setDefaultStrategy($defaultStrategy);

        $this->responseFactory ??= Psr17FactoryDiscovery::findResponseFactory();
        $this->streamFactory   ??= Psr17FactoryDiscovery::findStreamFactory();
    }

    /**
     * @inheritDoc
     */
    public function handle(ServerRequestInterface $request, Throwable $error): ResponseInterface
    {
        if ($error instanceof HttpError) {
            $request = $error->getRequest();
        }

        $this->logError($error, $request);

        $strategy = $this->detectStrategy($request, $error);

        if ($strategy instanceof DebugAwareInterface) {
            $strategy->setDebug($this->debug);
        }

        if ($strategy instanceof ResponseFactoryAwareInterface) {
            $strategy->setResponseFactory($this->responseFactory);
        }

        if ($strategy instanceof StreamFactoryAwareInterface) {
            $strategy->setStreamFactory($this->streamFactory);
        }

        return $strategy->respond($request, $error);
    }

    /**
     * @inheritDoc
     * 
     * @throws InvalidArgumentException If the strategy definition cannot be resolved.
     */
    public function addStrategy(ErrorStrategyInterface|string $strategy): void
    {
        if (is_string($strategy)) {
            $strategy = $this->resolveStrategy($strategy);
        }

        $this->strategies[$strategy->getName()] = $strategy;
    }

    /**
     * @inheritDoc
     */
    public function getStrategies(): array
    {
        return $this->strategies;
    }

    /**
     * @inheritDoc
     */
    public function getDefaultStrategy(): ErrorStrategyInterface
    {
        return $this->defaultStrategy;
    }

    /**
     * @inheritDoc
     * 
     * @throws InvalidArgumentException If the default strategy is not in the registered.
     */
    public function setDefaultStrategy(string $name): void
    {
        if (!isset($this->strategies[$name])) {
            throw new InvalidArgumentException(sprintf(
                "Unknown default strategy '%s'. Registered error strategies are: [%s].",
                $name,
                implode(', ', array_keys($this->strategies))
            ));
        }

        $this->defaultStrategy = $this->strategies[$name];
    }

    /**
     * Log the error, if a logger is provided.
     */
    protected function logError(Throwable $error, ServerRequestInterface $request): void
    {
        $this->logger?->error($error->getMessage(), [
            'exception'      => $error,
            'request_method' => $request->getMethod(),
            'request_uri'    => (string) $request->getUri(),
        ]);
    }

    /**
     * Detects the most appropriate strategy or return the default one.
     */
    private function detectStrategy(ServerRequestInterface $request, Throwable $error): ErrorStrategyInterface
    {
        foreach ($this->strategies as $strategy) {
            if ($strategy->supports($request, $error)) {
                return $strategy;
            }
        }

        return $this->defaultStrategy;
    }

    /**
     * Resolves a string definition into an error strategy instance.
     * 
     * @throws InvalidArgumentException If the strategy cannot be resolved or has the wrong type.
     */
    private function resolveStrategy(string $strategy): ErrorStrategyInterface
    {
        $instance = $this->container?->has($strategy)
            ? $this->container->get($strategy)
            : ReflectionResolver::resolve($strategy);

        if (!$instance instanceof ErrorStrategyInterface) {
            throw new InvalidArgumentException(sprintf(
                "Invalid type for '%s'. Expected [%s], got '%s'.",
                $strategy,
                ErrorStrategyInterface::class,
                $instance::class
            ));
        }

        return $instance;
    }

    /**
     * @return list<ErrorStrategyInterface>
     */
    private static function defaultStrategies(): array
    {
        return [
            new HtmlErrorStrategy(),
            new JsonErrorStrategy(),
            new XmlErrorStrategy(),
            new TextErrorStrategy(),
        ];
    }
}