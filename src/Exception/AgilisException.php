<?php

declare(strict_types=1);

namespace Agilis\Ads\Exception;

/**
 * The base type of every error the SDK throws for an API call.
 *
 * The API key is never part of the message.
 */
class AgilisException extends \RuntimeException
{
    /**
     * @param array<string, list<string>> $headers response headers, names in lower case
     */
    public function __construct(
        string $message,
        public readonly ?int $httpStatus = null,
        public readonly ?string $returnCode = null,
        public readonly mixed $body = null,
        public readonly array $headers = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $httpStatus ?? 0, $previous);
    }

    /**
     * One response header, or null when the response did not have it.
     */
    public function header(string $name): ?string
    {
        $values = $this->headers[strtolower($name)] ?? [];

        return $values === [] ? null : implode(', ', $values);
    }
}
