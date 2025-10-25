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
 * Implementation of {@see ErrorHandlerInterface}.
 *
 * Translates exceptions into PSR-7 responses using registered formatter instances.
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
     * @param FormatterInterface[]          $formatters
     * @param bool                          $debug
     * @param LoggerInterface|null          $logger
     * @param ResponseFactoryInterface|null $responseFactory
     */
    public function __construct(
        array $formatters = [],
        private bool $debug = false,
        private ?LoggerInterface $logger = null,
        ?ResponseFactoryInterface $responseFactory = null,
    ) {
        foreach ($formatters as $formatter) {
            $this->addFormatter($formatter);
        }

        $this->responseFactory = $responseFactory ?? Factory::getResponseFactory();
    }

    /**
     * {@inheritDoc}
     */
    public function addFormatter(FormatterInterface $formatter): void
    {
        if ($formatter->getContentTypes() === []) {
            throw new LogicException(sprintf(
                'Formatter %s must declare at least one supported content type.',
                $formatter::class
            ));
        }

        $this->formatters[] = $formatter;
    }

    /**
     * {@inheritDoc}
     * 
     * Determines the best formatter based on the request’s `Accept` header
     * and produces a response containing the serialized error.
     */
    public function handle(Throwable $error, ServerRequestInterface $request): ResponseInterface
    {
        $request = $error instanceof HttpError ? $error->getRequest() : $request;
        $code    = $error instanceof HttpError ? $error->getCode() : 500;

        if ($this->logger !== null) {
            $this->logError($error, $request);
        }

        if ($this->formatters === []) {
            $this->formatters = $this->defaultFormatters();
        }

        $result  = $this->detectFormatter($request);

        $formatter   = $result->formatter;
        $contentType = $result->contentType;

        if ($this->debug === false) {
            $error = new HttpError($request, $code);
        }

        $body = $formatter->format($error, $this->debug);

        $response = $this->responseFactory->createResponse($code);
        $response->getBody()->write($body);

        return $response->withHeader('Content-Type', $contentType);
    }

    /**
     * Logs contextual information about the error and request, if a logger is configured.
     */
    private function logError(Throwable $error, ServerRequestInterface $request): void
    {
        $context = [
            'request_method'    => $request->getMethod(),
            'request_uri'       => (string) $request->getUri(),
            'exception_message' => $error->getMessage(),
            'exception_type'    => $error::class,
            'exception_code'    => $error->getCode(),
            'exception_file'    => $error->getFile(),
            'exception_line'    => $error->getLine(),
            'exception_trace'   => $error->getTraceAsString(),
        ];

        $this->logger->error($error->getMessage(), $context);
    }

    /**
     * Selects the most appropriate formatter based on the request’s `Accept` header.
     *
     * @return FormatterMatch The chosen formatter and matching content type.
     */
    private function detectFormatter(ServerRequestInterface $request): FormatterMatch
    {
        $accept = $request->getHeaderLine('Accept');

        $formatter   = null;
        $contentType = null;

        foreach ($this->formatters as $candidate) {
            foreach ($candidate->getContentTypes() as $type) {
                if (str_contains($accept, $type)) {
                    $formatter = $candidate;
                    $contentType = $type;

                    break 2;
                }
            }
        }

        $formatter   ??= $this->formatters[0];
        $contentType ??= $formatter->getContentTypes()[0];

        return new FormatterMatch($formatter, $contentType);
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