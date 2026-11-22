<?php

declare(strict_types=1);

namespace Agilis\Ads\Internal;

use Agilis\Ads\Upload;
use Psr\Http\Message\StreamInterface;

/**
 * An encoded request body. Built once per call, so every retry sends the same bytes.
 *
 * @internal
 */
final class Body
{
    private function __construct(
        public readonly string $contentType,
        public readonly string $content,
    ) {}

    /**
     * @param array<string, mixed> $params
     */
    public static function json(array $params): self
    {
        array_walk_recursive($params, static function (mixed &$value): void {
            if ($value instanceof \DateTimeInterface) {
                $value = self::utc($value);
            }
        });

        $content = json_encode(
            $params === [] ? new \stdClass() : $params,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION,
        );

        return new self('application/json', $content);
    }

    /**
     * multipart/form-data. Lists go as repeated `name[]` parts, maps (such as `crop`) as `name[key]`.
     *
     * @param array<string, mixed> $params
     */
    public static function multipart(array $params, ?string $boundary = null): self
    {
        $boundary ??= 'agilis-' . bin2hex(random_bytes(16));
        $content = '';

        foreach (self::flatten($params) as [$name, $value]) {
            $content .= '--' . $boundary . "\r\n";

            if ($value instanceof Upload) {
                $content .= sprintf(
                    "Content-Disposition: form-data; name=\"%s\"; filename=\"%s\"\r\nContent-Type: %s\r\n\r\n",
                    self::quote($name),
                    self::quote($value->filename),
                    $value->contentType,
                );
                $content .= $value->contents . "\r\n";
            } else {
                $content .= sprintf("Content-Disposition: form-data; name=\"%s\"\r\n\r\n", self::quote($name));
                $content .= $value . "\r\n";
            }
        }

        $content .= '--' . $boundary . "--\r\n";

        return new self('multipart/form-data; boundary=' . $boundary, $content);
    }

    /**
     * application/x-www-form-urlencoded, with the same field naming as multipart.
     *
     * @param array<string, mixed> $params
     */
    public static function form(array $params): self
    {
        $pairs = [];

        foreach (self::flatten($params) as [$name, $value]) {
            if ($value instanceof Upload) {
                throw new \InvalidArgumentException('A file cannot be sent in a form-encoded body.');
            }

            $pairs[] = rawurlencode($name) . '=' . rawurlencode($value);
        }

        return new self('application/x-www-form-urlencoded', implode('&', $pairs));
    }

    /**
     * Whether the parameters hold a file to upload.
     *
     * @param array<string, mixed> $params
     */
    public static function hasFile(array $params): bool
    {
        foreach ($params as $key => $value) {
            if (self::isFile($key, $value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Turns one scalar into its text form on the wire.
     */
    public static function scalar(mixed $value): string
    {
        return match (true) {
            is_bool($value) => $value ? 'true' : 'false',
            is_int($value) => (string) $value,
            is_float($value) => self::formatFloat($value),
            is_string($value) => $value,
            $value instanceof \Stringable => (string) $value,
            $value instanceof \DateTimeInterface => self::utc($value),
            default => throw new \InvalidArgumentException(sprintf('Cannot send a value of type %s.', get_debug_type($value))),
        };
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return list<array{0: string, 1: string|Upload}>
     */
    private static function flatten(array $params): array
    {
        $out = [];

        foreach ($params as $key => $value) {
            $key = (string) $key;

            if ($value === null) {
                continue;
            }

            if (self::isFile($key, $value)) {
                $out[] = [$key, Upload::from($value)];
                continue;
            }

            if (is_array($value)) {
                $isList = array_is_list($value);
                $base = str_ends_with($key, '[]') ? substr($key, 0, -2) : $key;

                foreach ($value as $subKey => $item) {
                    if ($item === null) {
                        continue;
                    }

                    $out[] = [$isList ? $base . '[]' : $base . '[' . $subKey . ']', self::scalar($item)];
                }

                continue;
            }

            $out[] = [$key, self::scalar($value)];
        }

        return $out;
    }

    private static function isFile(string|int $key, mixed $value): bool
    {
        return $value instanceof Upload
            || $value instanceof StreamInterface
            || is_resource($value)
            || ($key === 'image' && is_string($value) && $value !== '');
    }

    private static function formatFloat(float $value): string
    {
        if (floor($value) === $value && abs($value) < 1e15) {
            return number_format($value, 0, '.', '');
        }

        $text = rtrim(rtrim(sprintf('%.10F', $value), '0'), '.');

        return $text === '-0' ? '0' : $text;
    }

    private static function utc(\DateTimeInterface $value): string
    {
        return \DateTimeImmutable::createFromInterface($value)
            ->setTimezone(new \DateTimeZone('UTC'))
            ->format('Y-m-d\TH:i:s\Z');
    }

    private static function quote(string $value): string
    {
        return str_replace(['"', "\r", "\n"], ['%22', '%0D', '%0A'], $value);
    }
}
