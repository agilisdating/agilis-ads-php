<?php

declare(strict_types=1);

namespace Agilis\Ads;

/**
 * A parsed API response: the JSON body as an array with every field kept, plus the HTTP status and headers.
 *
 * Read fields as an array (`$response['return_code']`) or with `get()`.
 *
 * @implements \ArrayAccess<string, mixed>
 * @implements \IteratorAggregate<string, mixed>
 */
class ApiResponse implements \ArrayAccess, \IteratorAggregate, \Countable, \JsonSerializable
{
    /**
     * @param array<string, mixed>        $data    the parsed JSON body
     * @param array<string, list<string>> $headers names in lower case
     */
    public function __construct(
        public readonly array $data,
        public readonly int $statusCode,
        public readonly array $headers = [],
        public readonly string $rawBody = '',
    ) {}

    public function get(string $key, mixed $default = null): mixed
    {
        return array_key_exists($key, $this->data) ? $this->data[$key] : $default;
    }

    public function returnCode(): ?string
    {
        $code = $this->data['return_code'] ?? null;

        return is_string($code) ? $code : null;
    }

    public function header(string $name): ?string
    {
        $values = $this->headers[strtolower($name)] ?? [];

        return $values === [] ? null : implode(', ', $values);
    }

    /** The `X-RateLimit-Remaining` header, when present. */
    public function rateLimitRemaining(): ?int
    {
        $value = $this->header('X-RateLimit-Remaining');

        return $value !== null && is_numeric($value) ? (int) $value : null;
    }

    /** The `X-RateLimit-Limit` header, when present. */
    public function rateLimitLimit(): ?int
    {
        $value = $this->header('X-RateLimit-Limit');

        return $value !== null && is_numeric($value) ? (int) $value : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }

    public function offsetExists(mixed $offset): bool
    {
        return array_key_exists($offset, $this->data);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->data[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): never
    {
        throw new \LogicException('API responses are read-only.');
    }

    public function offsetUnset(mixed $offset): never
    {
        throw new \LogicException('API responses are read-only.');
    }

    /**
     * @return \ArrayIterator<string, mixed>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->data);
    }

    public function count(): int
    {
        return count($this->data);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->data;
    }
}
