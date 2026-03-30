<?php

declare(strict_types=1);

namespace Zenigata\Http\Handling\Strategy;

use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

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
     * @param HttpRedirect $data
     * 
     * @throws InvalidArgumentException If status code is not a redirect.
     */
    public function respond(ServerRequestInterface $request, mixed $data): ResponseInterface
    {
        if (!in_array($data->status, self::HTTP_REDIRECT_CODES, true)) {
            throw new InvalidArgumentException(sprintf(
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