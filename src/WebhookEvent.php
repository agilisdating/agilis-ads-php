<?php

declare(strict_types=1);

namespace Agilis\Ads;

/**
 * A verified webhook event, as returned by `constructEvent`. Every field of the message is kept in `raw`.
 *
 * @implements \ArrayAccess<string, mixed>
 */
final class WebhookEvent implements \ArrayAccess, \JsonSerializable
{
    /**
     * @param array<string, mixed>      $data    `data`: `object` (the Offer, Event or invoice) and any extra facts
     * @param array<string, mixed>|null $request `request`: `source` and `idempotency_key`
     * @param array<string, mixed>      $raw     the whole message
     */
    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly int $created,
        public readonly bool $livemode,
        public readonly int $version,
        public readonly array $data,
        public readonly ?array $request,
        public readonly array $raw,
    ) {}

    /**
     * @param array<string, mixed> $message
     */
    public static function fromArray(array $message): self
    {
        $data = $message['data'] ?? [];
        $request = $message['request'] ?? null;

        /** @var array<string, mixed> $data */
        $data = is_array($data) ? $data : [];
        /** @var array<string, mixed>|null $request */
        $request = is_array($request) ? $request : null;

        return new self(
            is_string($message['id'] ?? null) ? $message['id'] : '',
            is_string($message['type'] ?? null) ? $message['type'] : '',
            is_int($message['created'] ?? null) ? $message['created'] : 0,
            ($message['livemode'] ?? false) === true,
            is_int($message['version'] ?? null) ? $message['version'] : 1,
            $data,
            $request,
            $message,
        );
    }

    /**
     * What the event is about, from its type: `offer`, `event`, `balance`, `invoice` or `terms`.
     */
    public function resource(): string
    {
        $dot = strpos($this->type, '.');

        return $dot === false ? $this->type : substr($this->type, 0, $dot);
    }

    /**
     * `data.object`: the Offer, Event or invoice as the API returns it elsewhere.
     *
     * @return array<string, mixed>
     */
    public function object(): array
    {
        $object = $this->data['object'] ?? [];

        /** @var array<string, mixed> */
        return is_array($object) ? $object : [];
    }

    public function isOfferEvent(): bool
    {
        return $this->resource() === 'offer';
    }

    public function isEventEvent(): bool
    {
        return $this->resource() === 'event';
    }

    public function offsetExists(mixed $offset): bool
    {
        return array_key_exists($offset, $this->raw);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->raw[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): never
    {
        throw new \LogicException('Webhook events are read-only.');
    }

    public function offsetUnset(mixed $offset): never
    {
        throw new \LogicException('Webhook events are read-only.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->raw;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->raw;
    }
}
