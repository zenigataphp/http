<?php

declare(strict_types=1);

namespace Zenigata\Http\Request;

use Throwable;
use Middlewares\Utils\Factory;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\UriInterface;

use const UPLOAD_ERR_NO_FILE;
use const UPLOAD_ERR_OK;

use function explode;
use function implode;
use function in_array;
use function is_array;
use function is_string;
use function is_uploaded_file;
use function preg_match;
use function str_contains;
use function str_replace;
use function str_starts_with;
use function strtolower;
use function strtoupper;
use function substr;
use function trim;
use function ucwords;
use function urldecode;

/**
 * Implementation of {@see Zenigata\Http\Request\InitializerInterface}.
 *
 * Builds the initial server request that drives the HTTP lifecycle
 * in full compliance with PSR standards.
 */
class Initializer implements InitializerInterface
{
    /**
     * List of server variables that should be treated as content headers.
     * 
     * @var string[]
     */
    private const ALLOWED_CONTENT_HEADERS = [
        'CONTENT_LENGTH',
        'CONTENT_MD5',
        'CONTENT_TYPE',
    ];
    
    /**
     * Default blacklist of headers to exclude for security reasons.
     * 
     * @var string[]
     */
    private const BLACKLISTED_HEADERS = [
        'HTTP_PROXY', // https://httpoxy.org
    ];

    private ServerRequestFactoryInterface $serverRequestFactory;
    private StreamFactoryInterface $streamFactory;
    private UploadedFileFactoryInterface $uploadedFileFactory;
    private UriFactoryInterface $uriFactory;
    
    /**
     * Creates a new initializer instance.
     *
     * @param ServerRequestFactoryInterface|null $serverRequestFactory Optional server request factory.
     * @param StreamFactoryInterface|null        $streamFactory        Optional stream factory.
     * @param UploadedFileFactoryInterface|null  $uploadedFileFactory  Optional uploaded file factory.
     * @param UriFactoryInterface|null           $uriFactory           Optional URI factory.
     */
    public function __construct(
        ?ServerRequestFactoryInterface $serverRequestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
        ?UploadedFileFactoryInterface $uploadedFileFactory = null,
        ?UriFactoryInterface $uriFactory = null
    ) {
        $this->serverRequestFactory = $serverRequestFactory ?? Factory::getServerRequestFactory();
        $this->streamFactory        = $streamFactory        ?? Factory::getStreamFactory();
        $this->uploadedFileFactory  = $uploadedFileFactory  ?? Factory::getUploadedFileFactory();
        $this->uriFactory           = $uriFactory           ?? Factory::getUriFactory();
    }

    /**
     * @inheritDoc
     */
    public function initialize(
        ?array $server = null,
        ?array $get    = null,
        ?array $post   = null,
        ?array $cookie = null,
        ?array $files  = null,
    ): ServerRequestInterface
    {
        $server  ??= $_SERVER;

        $uri     = $this->createUri($server);
        $request = $this->createServerRequest($uri, $server);
        $headers = $this->detectHeaders($server);
        
        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }
        
        $cookie   = isset($headers['Cookie']) ? $this->detectCookie($headers['Cookie']) : $_COOKIE;
        $protocol = $this->detectProtocolVersion($server);
        $files    = $this->normalizeFiles($files ?? $_FILES);
        
        return $request
            ->withQueryParams($get ?? $_GET)
            ->withParsedBody($post ?? $_POST)
            ->withCookieParams($cookie)
            ->withProtocolVersion($protocol)
            ->withUploadedFiles($files);
    }
    
    /**
     * Determines if the given file was uploaded via HTTP POST.
     */
    protected function isUploadedFile(string $filename): bool
    {
        return is_uploaded_file($filename);
    }

    /**
     * Detects and normalizes HTTP headers from the given server array.
     * 
     * @param array<string,mixed> $server
     * 
     * @return array<string,string>
     */
    private function detectHeaders(array $server): array
    {
        $headers = [];

        foreach ($server as $name => $value) {
            if (!is_string($value) || in_array($name, self::BLACKLISTED_HEADERS, true)) {
                continue;
            }

            // 1. Handle standard HTTP_ prefix
            if (str_starts_with($name, 'HTTP_')) {
                $normalized = $this->formatHeader(substr($name, 5));
                $headers[$normalized] = $value;
                continue;
            }

            // 2. Allow content headers
            if (in_array($name, self::ALLOWED_CONTENT_HEADERS, true)) {
                $normalized = $this->formatHeader($name);
                $headers[$normalized] = $value;
                continue;
            }

            // 3. Handle Authorization (Apache/FastCGI quirk)
            if ($name === 'REDIRECT_HTTP_AUTHORIZATION' && !isset($headers['Authorization'])) {
                $headers['Authorization'] = $value;
            }
        }

        // Normalize duplicate headers into arrays
        foreach ($headers as $key => $val) {
            if (is_array($val)) {
                $headers[$key] = implode(',', $val);
            }
        }

        return $headers;
    }

    /**
     * Detects and normalizes cookies from the Cookie header.
     * 
     * @return array<string,string>
     */
    private function detectCookie(string $header): array
    {
        $cookie = [];

        foreach (explode(';', $header) as $part) {
            $part = trim($part);

            if ($part === '') {
                continue;
            }

            $pair = explode('=', $part, 2);
            $name = trim($pair[0]);

            if ($name === '') {
                continue;
            }

            $value = isset($pair[1]) ? urldecode(trim($pair[1])) : '';
            $cookie[$name] = $value;
        }

        return $cookie;
    }

    /**
     * Detects the HTTP protocol version from server parameters.
     * 
     * @param array<string,mixed> $server
     */
    private function detectProtocolVersion(array $server): string
    {
        $protocol = strtoupper($server['SERVER_PROTOCOL'] ?? '');

        if (!str_starts_with($protocol, 'HTTP/')) {
            return '1.1';
        }

        $version = substr($protocol, 5);

        // Allow any valid numeric HTTP version
        return preg_match('/^\d+(?:\.\d+)?$/', $version) ? $version : '1.1';
    }

    /**
     * Formats a server variable into a valid HTTP header name
     * (e.g., CONTENT_TYPE → Content-Type).
     */
    private function formatHeader(string $header): string
    {
        $header = str_replace('_', ' ', strtolower($header));
        $header = str_replace(' ', '-', ucwords($header));
        
        return $header;
    }

    /**
     * Normalizes the uploaded files array into PSR-7 uploaded files.
     * 
     * @param array<string,mixed> $files
     * 
     * @return array<string,UploadedFileInterface[]|UploadedFileInterface>
     */
    private function normalizeFiles(array $files): array
    {
        $normalized = [];

        foreach ($files as $field => $file) {
            $normalized[$field] = !is_array($file['name'])
                ? $this->createUploadedFile($file)
                : $this->normalizeNestedFiles($file);
        }
        
        return $normalized;
    }

    /**
     * Normalizes a nested set of uploaded files (multi-dimensional $_FILES).
     * 
     * @param array<string,mixed> $file
     * 
     * @return array<string,UploadedFileInterface[]|UploadedFileInterface>
     */
    private function normalizeNestedFiles(array $file): array
    {
        $normalized = [];

        foreach ($file['name'] as $key => $name) {
            $nested = [
                'name'     => $file['name'][$key],
                'type'     => $file['type'][$key],
                'tmp_name' => $file['tmp_name'][$key],
                'error'    => $file['error'][$key],
                'size'     => $file['size'][$key],
            ];

            $normalized[$key] = !is_array($name)
                ? $this->createUploadedFile($nested)
                : $this->normalizeNestedFiles($nested);
        }

        return $normalized;
    }

    /**
     * Creates a PSR-7 uploaded file from raw $_FILES data.
     * 
     * @param array<string,mixed> $file
     */
    private function createUploadedFile(array $file): UploadedFileInterface
    {
        $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        $tmpName  = $file['tmp_name'] ?? null;

        if ($error === UPLOAD_ERR_OK && $tmpName !== null && $this->isUploadedFile($tmpName)) {
            try {
                $stream = $this->streamFactory->createStreamFromFile($tmpName);
            } catch (Throwable) {
                $stream = $this->streamFactory->createStream('');
            }
        } else {
            $stream = $this->streamFactory->createStream('');
        }

        return $this->uploadedFileFactory->createUploadedFile(
            stream:          $stream,
            size:            (int) ($file['size'] ?? 0),
            error:           $error,
            clientFilename:  $file['name'] ?? null,
            clientMediaType: $file['type'] ?? null,
        );
    }

    /**
     * Creates a PSR-7 URI from server parameters.
     *
     * @param array<string,mixed> $server
     */
    private function createUri(array $server): UriInterface
    {
        $uri = $this->uriFactory->createUri();

        // Scheme
        $uri = $uri->withScheme($this->isHttps($server) ? 'https' : 'http');

        // Host + Port
        if (isset($server['HTTP_HOST']) || isset($server['SERVER_NAME'])) {
            $host = $server['HTTP_HOST'] ?? $server['SERVER_NAME'];

            // Handle host:port
            if (str_contains($host, ':')) {
                [$host, $port] = explode(':', $host, 2);

                $uri = $uri->withHost($host)->withPort((int) $port);
            } else {
                $uri = $uri->withHost($host);
            }
        }

        // Port (if not already set)
        if (isset($server['SERVER_PORT']) && !$uri->getPort()) {
            $uri = $uri->withPort((int) $server['SERVER_PORT']);
        }

        // Path & Query
        if (isset($server['REQUEST_URI'])) {
            $parts = explode('?', $server['REQUEST_URI'], 2);
            $path  = $parts[0];
            $query = $parts[1] ?? '';

            $uri = $uri->withPath($path);

            if ($query !== '') {
                $uri = $uri->withQuery($query);
            }
        }

        return $uri;
    }

    /**
     * Creates a PSR-7 server request from server parameters.
     *
     * @param array<string,mixed> $server
     */
    private function createServerRequest(UriInterface $uri, array $server): ServerRequestInterface
    {
        $method  = strtoupper($server['REQUEST_METHOD'] ?? 'GET');

        return $this->serverRequestFactory->createServerRequest($method, $uri, $server);
    }

    /**
     * Determines whether the current request was made over HTTPS.
     *
     * @param array<string,mixed> $server
     */
    private function isHttps(array $server): bool
    {
        return (!empty($server['HTTPS']) && strtolower($server['HTTPS']) !== 'off')
            || (isset($server['SERVER_PORT']) && (int) $server['SERVER_PORT'] === 443);
    }
}
