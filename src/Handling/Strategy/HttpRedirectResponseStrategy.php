<?php

declare(strict_types=1);

namespace Zenigata\Http\Handling\Strategy;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

use function get_debug_type;
use function implode;
use function in_array;
use function sprintf;

/**
 * HTTP redirect response strategy.
 * 
 * Creates a redirect response from a {@see Zenigata\Http\Handling\Strategy\HttpRedirect}.
 */
class HttpRedirectResponseStrategy extends AbstractResponseStrategy
{
    /**
     * Valid status codes for redirects.
     *
     * @var list<int>
     */
    protected const HTTP_REDIRECT_CODES = [301, 302, 303, 307, 308];

    /**
     * @inheritDoc
     */
    protected string $name = 'redirect';

    /**
     * @inheritDoc
     */
    public function supports(ServerRequestInterface $request, mixed $data): bool
    {
        return $data instanceof HttpRedirect;
    }

    /**
     * @inheritDoc
     * 
     * @throws RuntimeException If status code is not a redirect, or data type is not supported.
     */
    public function respond(ServerRequestInterface $request, mixed $data): ResponseInterface
    {
        if (!$data instanceof HttpRedirect) {
            throw new RuntimeException(sprintf(
                "Unsupported data type. Expected '%s', got '%s'.",
                HttpRedirect::class,
                get_debug_type($data)
            ));
        }

        if (!in_array($data->status, self::HTTP_REDIRECT_CODES, true)) {
            throw new RuntimeException(sprintf(
                'Invalid redirect status code: %s. Allowed values are: %s.',
                $data->status,
                implode(',', self::HTTP_REDIRECT_CODES)
            ));
        }

        $response = $this->getResponseFactory()->createResponse($data->status);

        foreach ($data->headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return $response->withHeader('Location', $data->location);
    }
}