<?php

declare(strict_types=1);

namespace Zenigata\Http\Handling;

use Http\Discovery\Psr17FactoryDiscovery;
use InvalidArgumentException;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Zenigata\Http\Handling\Strategy\FileResponseStrategy;
use Zenigata\Http\Handling\Strategy\HttpRedirectResponseStrategy;
use Zenigata\Http\Handling\Strategy\JsonResponseStrategy;
use Zenigata\Http\Handling\Strategy\TextResponseStrategy;
use Zenigata\Http\Handling\Strategy\XmlResponseStrategy;
use Zenigata\Http\Routing\RouteMatch;
use Zenigata\Utility\Awareness\ContainerAwareInterface;
use Zenigata\Utility\Awareness\ContainerAwareTrait;
use Zenigata\Utility\Awareness\ResponseFactoryAwareInterface;
use Zenigata\Utility\Awareness\StreamFactoryAwareInterface;
use Zenigata\Utility\Helper\ReflectionResolver;

use function array_keys;
use function implode;
use function is_string;
use function sprintf;

/**
 * Implementation of {@see Zenigata\Http\Handling\RouteHandlerInterface}.
 * 
 * Supports handler normalization and configurable invocation.
 * 
 * If no response strategy is configured, the defaults will be used:
 * redirect, file, JSON, XML, text.
 */
class RouteHandler implements RouteHandlerInterface, ContainerAwareInterface
{
    use ContainerAwareTrait;

    /**
     * List of registered response strategies.
     *
     * @var array<string,ResponseStrategyInterface>
     */
    private array $strategies = [];

    /**
     * The default response strategy, if no more specific one is found.
     */
    private ResponseStrategyInterface $defaultStrategy;

    /**
     * Creates a new route handler instance.
     *
     * @param list<ResponseStrategyInterface> $strategies      List of strategies used to create responses.
     * @param string                          $defaultStrategy The default strategy to use.
     * @param HandlerNormalizerInterface      $normalizer      Normalizer to convert handler definitions into callables.
     * @param HandlerInvokerInterface         $invoker         Invoker to exectute callable handlers.
     * @param ResponseFactoryInterface|null   $responseFactory The response factory, automatically detected if not provided.
     * @param StreamFactoryInterface|null     $streamFactory   The stream factory, automatically detected if not provided.
     */
    public function __construct(
        array $strategies = [],
        string $defaultStrategy = 'text',
        private HandlerNormalizerInterface $normalizer = new HandlerNormalizer(),
        private HandlerInvokerInterface $invoker = new DefaultHandlerInvoker(),
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
     * @inheritdoc
     */
    public function handle(ServerRequestInterface $request, RouteMatch $route): ResponseInterface
    {
        $handler = $this->normalizer->normalize($route->handler);
        $result  = $this->invoker->invoke($request, $handler, $route->parameters);

        if ($result instanceof ResponseInterface) {
            return $result;
        }

        $strategy = $this->detectStrategy($request, $result);

        if ($strategy instanceof ResponseFactoryAwareInterface) {
            $strategy->setResponseFactory($this->responseFactory);
        }

        if ($strategy instanceof StreamFactoryAwareInterface) {
            $strategy->setStreamFactory($this->streamFactory);
        }

        return $strategy->respond($request, $result);
    }

    /**
     * @inheritDoc
     * 
     * @throws InvalidArgumentException If the strategy definition cannot be resolved.
     */
    public function addStrategy(ResponseStrategyInterface|string $strategy): void
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
    public function getDefaultStrategy(): ResponseStrategyInterface
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
                "Unknown default strategy '%s'. Registered response strategies are: [%s].",
                $name,
                implode(', ', array_keys($this->strategies))
            ));
        }

        $this->defaultStrategy = $this->strategies[$name];
    }

    /**
     * @inheritDoc
     */
    public function setContainer(ContainerInterface $container): void
    {
        $this->container = $container;

        if ($this->normalizer instanceof ContainerAwareInterface) {
            $this->normalizer->setContainer($container);
        }
    }

    /**
     * Detects the most appropriate strategy or return the default one.
     */
    private function detectStrategy(ServerRequestInterface $request, mixed $data): ResponseStrategyInterface
    {
        foreach ($this->strategies as $strategy) {
            if ($strategy->supports($request, $data)) {
                return $strategy;
            }
        }

        return $this->defaultStrategy;
    }

    /**
     * Resolves a string definition into a response strategy instance.
     * 
     * @throws InvalidArgumentException If the strategy cannot be resolved or has the wrong type.
     */
    private function resolveStrategy(string $strategy): ResponseStrategyInterface
    {
        $instance = $this->container?->has($strategy)
            ? $this->container->get($strategy)
            : ReflectionResolver::resolve($strategy);

        if (!$instance instanceof ResponseStrategyInterface) {
            throw new InvalidArgumentException(sprintf(
                "Invalid type for '%s'. Expected [%s], got '%s'.",
                $strategy,
                ResponseStrategyInterface::class,
                $instance::class
            ));
        }

        return $instance;
    }

    /**
     * @return list<ResponseStrategyInterface>
     */
    private static function defaultStrategies(): array
    {
        return [
            new HttpRedirectResponseStrategy(),
            new FileResponseStrategy(),
            new JsonResponseStrategy(),
            new XmlResponseStrategy(),
            new TextResponseStrategy(),
        ];
    }
}