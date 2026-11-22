<?php

declare(strict_types=1);

namespace Agilis\Ads;

use Psr\Http\Message\StreamInterface;

/**
 * A file to upload, such as an Offer or Event image.
 *
 * Make one from a file path, from bytes or from a stream. The content type comes from the file name.
 * Wherever a parameter takes an image you can also pass a file path (string), an open stream resource
 * or a PSR-7 stream directly.
 */
final class Upload
{
    private const TYPES = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
    ];

    public readonly string $contentType;

    public function __construct(
        public readonly string $contents,
        public readonly string $filename,
        ?string $contentType = null,
    ) {
        $this->contentType = $contentType ?? self::contentTypeFor($filename);
    }

    public static function fromPath(string $path, ?string $filename = null): self
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new \InvalidArgumentException(sprintf('Cannot read the file "%s".', $path));
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new \InvalidArgumentException(sprintf('Cannot read the file "%s".', $path));
        }

        return new self($contents, $filename ?? basename($path));
    }

    public static function fromBytes(string $bytes, string $filename): self
    {
        return new self($bytes, $filename);
    }

    /**
     * @param resource|StreamInterface $stream read from its current position to the end
     */
    public static function fromStream(mixed $stream, ?string $filename = null): self
    {
        if ($stream instanceof StreamInterface) {
            return new self($stream->getContents(), $filename ?? self::nameFromUri($stream->getMetadata('uri')));
        }

        if (!is_resource($stream)) {
            throw new \InvalidArgumentException('Expected a stream resource or a PSR-7 stream.');
        }

        $contents = stream_get_contents($stream);

        if ($contents === false) {
            throw new \InvalidArgumentException('Cannot read the stream.');
        }

        return new self($contents, $filename ?? self::nameFromUri(stream_get_meta_data($stream)['uri'] ?? null));
    }

    /**
     * Turns any accepted image value into an Upload: an Upload, a file path, a stream resource or a PSR-7 stream.
     */
    public static function from(mixed $value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        if (is_string($value)) {
            return self::fromPath($value);
        }

        if ($value instanceof StreamInterface || is_resource($value)) {
            return self::fromStream($value);
        }

        throw new \InvalidArgumentException('An image must be an Upload, a file path, a stream resource or a PSR-7 stream.');
    }

    public static function contentTypeFor(string $filename): string
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return self::TYPES[$extension] ?? 'application/octet-stream';
    }

    private static function nameFromUri(mixed $uri): string
    {
        if (is_string($uri) && $uri !== '' && !str_starts_with($uri, 'php://')) {
            return basename($uri);
        }

        return 'upload';
    }
}
