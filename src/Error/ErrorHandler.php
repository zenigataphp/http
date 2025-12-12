<?php

declare(strict_types=1);

namespace Zenigata\Http\Error;

use LogicException;
use Throwable;
use Middlewares\Utils\Factory;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

use function sprintf;
use function str_contains;

/**
 * Implementation of {@see Zenigata\Http\Error\ErrorHandlerInterface}.
 *
 * Converts exceptions into PSR-7 responses using registered formatter instances.
 * Supports configurable logging, debug mode, and automatic content negotiation.
 */
class ErrorHandler implements ErrorHandlerInterface
{
    /**
     * Registered formatters used to create error representations.
     *
     * @var FormatterInterface[]
     */
    private array $formatters = [];

    /**
     * Factory used to generate PSR-7 responses.
     *
     * @var ResponseFactoryInterface
     */
    private ResponseFactoryInterface $responseFactory;

    /**
     * Creates a new error handler instance.
     *
     * @param iterable<FormatterInterface>  $formatters      List of formatters used to serialize error responses.
     * @param LoggerInterface|null          $logger          Optional PSR-3 logger for recording exceptions and request context.
     * @param ResponseFactoryInterface|null $responseFactory Optional factory to create PSR-7 response instances.
     */
    public function __construct(
        iterable $formatters = [],
        private ?LoggerInterface $logger = null,
        ?ResponseFactoryInterface $responseFactory = null,
    ) {
        foreach ($formatters as $formatter) {
            $this->addFormatter($formatter);
        }

        $this->responseFactory = $responseFactory ?? Factory::getResponseFactory();
    }

    /**
     * @inheritDoc
     */
    public function addFormatter(FormatterInterface $formatter): void
    {
        if ($formatter->contentTypes() === []) {
            throw new LogicException(sprintf(
                'Formatter %s must declare at least one supported content type.',
                $formatter::class
            ));
        }

        $this->formatters[] = $formatter;
    }

    /**
     * @inheritDoc
     * 
     * Determines the best formatter based on the request’s `Accept` header
     * and produces a response containing the serialized error.
     */
    public function handle(ServerRequestInterface $request, Throwable $error, bool $debug = false): ResponseInterface
    {
        $request = $error instanceof HttpError ? $error->getRequest() : $request;
        $code    = $error instanceof HttpError ? $error->getCode() : 500;

        $this->logger?->error($error->getMessage(), [
            'exception'      => $error,
            'request_method' => $request->getMethod(),
            'request_uri'    => (string) $request->getUri(),
        ]);

        if ($this->formatters === []) {
            $this->formatters = $this->defaultFormatters();
        }

        [$formatter, $contentType]  = $this->detectFormatter($request);

        if ($debug === false) {
            $error = new HttpError($request, $code);
        }

        $body = $formatter->format($error, $debug);

        $response = $this->responseFactory->createResponse($code);
        $response->getBody()->write($body);

        return $response->withHeader('Content-Type', $contentType);
    }

    /**
     * Selects the most appropriate formatter based on the request’s `Accept` header.
     *
     * @return array{0:FormatterInterface,1:string} The chosen formatter and matching content type.
     */
    private function detectFormatter(ServerRequestInterface $request): array
    {
        $accept = $request->getHeaderLine('Accept');

        foreach ($this->formatters as $formatter) {
            foreach ($formatter->contentTypes() as $contentType) {
                if (str_contains($accept, $contentType)) {
                    return [$formatter, $contentType];
                }
            }
        }

        $formatter   = $this->formatters[0];
        $contentType = $formatter->contentTypes()[0];

        return [$formatter, $contentType];
    }

    /**
     * Returns a default set of formatters used when none are explicitly registered.
     *
     * @return FormatterInterface[] A list of fallback formatters.
     */
    private function defaultFormatters(): array
    {
        return [
            new HtmlFormatter(),
            new JsonFormatter(),
            new XmlFormatter(),
            new TextFormatter(),
        ];
    }
}