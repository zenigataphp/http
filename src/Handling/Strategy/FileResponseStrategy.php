<?php

declare(strict_types=1);

namespace Zenigata\Http\Handling\Strategy;

use finfo;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use SplFileInfo;

use const FILEINFO_MIME_TYPE;

use function filesize;
use function fopen;
use function is_readable;
use function rawurlencode;
use function sprintf;

/**
 * File response strategy.
 *
 * Creates a response from a {@see SplFileInfo}.
 */
class FileResponseStrategy extends AbstractResponseStrategy
{
    /**
     * @inheritDoc
     */
    protected string $name = 'file';

    /**
     * Content disposition used for the response.
     */
    protected string $disposition = 'attachment';

    /**
     * Lazily initialized MIME type detector.
     */
    private ?finfo $finfo = null;

    /**
     * @inheritDoc
     */
    public function supports(ServerRequestInterface $request, mixed $data): bool
    {
        return $data instanceof SplFileInfo;
    }

    /**
     * @inheritDoc
     * 
     * @param SplFileInfo $data
     * 
     * @throws RuntimeException If the file is not readable or missing.
     */
    public function respond(ServerRequestInterface $request, mixed $data): ResponseInterface
    {
        $path = $data->getPathname();

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

        $response = $this->getResponseFactory()->createResponse($this->status);

        $size = filesize($path);

        if ($size !== false) {
            $response = $response->withHeader('Content-Length', (string) $size);
        }

        return $response
            ->withHeader('Content-Type', $this->detectMimeType($path))
            ->withHeader('Content-Disposition', $this->buildContentDisposition($data))
            ->withBody($this->getStreamFactory()->createStreamFromResource($resource));
    }

    /**
     * Detects the MIME type of the given file.
     */
    protected function detectMimeType(string $path): string
    {
        $this->finfo ??= new finfo(FILEINFO_MIME_TYPE);

        return $this->finfo->file($path) ?: 'application/octet-stream';
    }

    /**
     * Builds the Content-Disposition header value for the given file.
     */
    protected function buildContentDisposition(SplFileInfo $file): string
    {
        $filename = $file->getFilename();
        $encoded  = rawurlencode($filename);

        return sprintf(
            '%s; filename="%s"; filename*=UTF-8\'\'%s',
            $this->disposition,
            $filename,
            $encoded
        );
    }
}