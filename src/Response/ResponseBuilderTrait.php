<?php

declare(strict_types=1);

namespace Zenigata\Http\Response;

use InvalidArgumentException;
use RuntimeException;
use Middlewares\Utils\Factory;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;

use function basename;
use function fopen;
use function get_debug_type;
use function implode;
use function in_array;
use function is_readable;
use function is_resource;
use function is_string;
use function json_encode;
use function json_last_error_msg;
use function mime_content_type;
use function rawurlencode;
use function sprintf;

/**
 * Utility trait for building responses in various formats.
 *
 * Provides convenience methods for generating PSR-7 responses in common formats.
 */
trait ResponseBuilderTrait
{
    /**
     * Valid status codes for redirects.
     *
     * @var int[]
     */
    private const REDIRECT_STATUS_CODES = [301, 302, 303, 307, 308];
    
    /**
     * Factory used to generate PSR-7 responses.
     *
     * @var ResponseFactoryInterface|null
     */
    protected ?ResponseFactoryInterface $responseFactory = null;
    
    /**
     * Factory used to generate PSR-7 streams.
     *
     * @var StreamFactoryInterface|null
     */
    protected ?StreamFactoryInterface $streamFactory = null;

    /**
     * Creates a new response instance.
     *
     * @param int                  $status  HTTP status code.
     * @param mixed                $body    The response body.
     * @param array<string,string> $headers Additional response headers.
     * 
     * @return ResponseInterface The generated PSR-7 response.
     */
    public function createResponse(int $status = 200, mixed $body = null, array $headers = []): ResponseInterface
    {   
        $responseFactory = $this->responseFactory ?? Factory::getResponseFactory();
        $response = $responseFactory->createResponse($status);

        if ($body !== null) {
            $response = $response->withBody($this->createStream($body));
        }

        foreach ($headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return $response;
    }

    /**
     * Creates a new stream instance.
     *
     * @param string|resource|StreamInterface $body The body content as a string, resource, or PSR-7 stream.
     * 
     * @return StreamInterface The generated PSR-7 stream.
     * @throws InvalidArgumentException If the body type is not a valid.
     */
    public function createStream(mixed $body): StreamInterface
    {
        if ($body instanceof StreamInterface) {
            return $body;
        }

        $streamFactory = $this->streamFactory ?? Factory::getStreamFactory();

        return match (true) {
            is_string($body)   => $streamFactory->createStream($body),
            is_resource($body) => $streamFactory->createStreamFromResource($body),
            default            => throw new InvalidArgumentException(sprintf(
                "Invalid stream body type. Expected string or resource, got '%s'",
                get_debug_type($body)
            ))
        };
    }

    /** 
     * Creates a JSON response.
     * 
     * @param mixed $data    The data to encode as JSON.
     * @param int   $status  The HTTP status code.
     * @param array $headers Additional response headers.
     * @param int   $flags   JSON encoding option flags.
     * @param int   $depth   Maximum depth for JSON encoding.
     * 
     * @return ResponseInterface The generated PSR-7 response.
     * @throws RuntimeException If JSON encoding fails.
     */
    public function jsonResponse(
        mixed $data,
        int $status    = 200,
        array $headers = [],
        int $flags = 0,
        int $depth = 512
    ): ResponseInterface {
        $json = json_encode($data, $flags, $depth);

        if ($json === false) {
            throw new RuntimeException(sprintf(
                'Failed to encode response body to JSON: %s.',
                json_last_error_msg()
            ));
        }

        $headers['Content-Type'] = ['application/json'];
        $body = $this->createStream($json);

        return $this->createResponse($status, $body, $headers);
    }

    /**
     * Creates an HTML response.
     *
     * @param string               $html    HTML string.
     * @param int                  $status  HTTP status code.
     * @param array<string,string> $headers Additional headers.
     *
     * @return ResponseInterface The generated PSR-7 response.
     */
    public function htmlResponse(string $html, int $status = 200, array $headers = []): ResponseInterface
    {
        $headers['Content-Type'] = ['text/html'];
        $body = $this->createStream($html);

        return $this->createResponse($status, $body, $headers);
    }

    /** 
     * Creates a plain text response.
     *
     * @param string               $text    Text string.
     * @param int                  $status  HTTP status code.
     * @param array<string,string> $headers Additional headers.
     *
     * @return ResponseInterface The generated PSR-7 response.
     */
    public function textResponse(string $text, int $status = 200, array $headers = []): ResponseInterface
    {
        $headers['Content-Type'] = ['text/plain'];
        $body = $this->createStream($text);

        return $this->createResponse($status, $body, $headers);
    }

    /** 
     * Creates an XML response.
     *
     * @param string               $xml     XML string.
     * @param int                  $status  HTTP status code.
     * @param array<string,string> $headers Additional headers.
     *
     * @return ResponseInterface The generated PSR-7 response.
     */
    public function xmlResponse(string $xml, int $status = 200, array $headers = []): ResponseInterface
    {
        $headers['Content-Type'] = ['application/xml'];
        $body = $this->createStream($xml);

        return $this->createResponse($status, $body, $headers);
    }

    /**
     * Creates a redirect response.
     *
     * @param string               $location Target URL.
     * @param int                  $status   Redirect status code (301, 302, 303, 307, 308).
     * @param array<string,string> $headers  Additional headers.
     *
     * @return ResponseInterface The generated PSR-7 response.
     * @throws InvalidArgumentException If status code is not a redirect.
     */
    public function redirectResponse(string $location, int $status = 302, array $headers = []): ResponseInterface
    {
        if (!in_array($status, self::REDIRECT_STATUS_CODES, true)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid redirect status code: %s. Allowed values are: %s.',
                $status,
                implode(',', self::REDIRECT_STATUS_CODES)
            ));
        }

        $headers['Location'] = [$location];
        $body = $this->createStream('');
        
        return $this->createResponse($status, $body, $headers);
    }

    /**
     * Creates an empty response with no body.
     *
     * @param int                  $status  HTTP status code, defaults to 204.
     * @param array<string,string> $headers Additional headers.
     *
     * @return ResponseInterface The generated PSR-7 response.
     */
    public function emptyResponse(int $status = 204, array $headers = []): ResponseInterface
    {
        $body = $this->createStream('');

        return $this->createResponse($status, $body, $headers);
    }

    /** 
     * Creates a file download response.
     *
     * @param string               $path        Absolute file path.
     * @param string|null          $filename    Optional filename override.
     * @param string               $disposition Content disposition, defaults to "attachment".
     * @param int                  $status      HTTP status code.
     * @param array<string,string> $headers     Additional headers.
     *
     * @return ResponseInterface The generated PSR-7 response.
     * @throws RuntimeException If the file is not readable or missing.
     */
    public function fileResponse(
        string $path,
        ?string $filename   = null,
        string $disposition = 'attachment',
        int $status         = 200,
        array $headers      = []
    ): ResponseInterface {
        if (!is_readable($path)) {
            throw new RuntimeException(sprintf(
                "Cannot create file response: file '%s' is not readable or does not exist.",
                $path
            ));
        }

        $resource = fopen($path, 'rb');

        if ($resource === false) {
            throw new RuntimeException(sprintf(
                "Cannot create file response: failed to open file '%s' for reading.",
                $path
            ));
        }

        $body = $this->createStream($resource);
        
        $filename   = $filename ?? basename($path);
        $encodedUrl = rawurlencode($filename);
        
        $headers['Content-Disposition'] = ["{$disposition}; filename=\"{$filename}\"; filename*=UTF-8''{$encodedUrl}"];
        $headers['Content-Type']        = [mime_content_type($path) ?: 'application/octet-stream'];
        
        $size = filesize($path);

        if ($size !== false) {
            $headers['Content-Length'] = [(string) $size];
        }

        return $this->createResponse($status, $body, $headers);
    }
}
