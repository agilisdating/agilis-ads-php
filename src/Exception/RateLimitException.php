<?php

declare(strict_types=1);

namespace Agilis\Ads\Exception;

/**
 * Too many requests (HTTP 429). `retryAfter` is the server's Retry-After, in seconds, when it sent one.
 */
class RateLimitException extends AgilisException
{
    /**
     * @param array<string, list<string>> $headers
     */
    public function __construct(
        string $message,
        ?int $httpStatus = null,
        ?string $returnCode = null,
        mixed $body = null,
        array $headers = [],
        public readonly ?float $retryAfter = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $httpStatus, $returnCode, $body, $headers, $previous);
    }
}
