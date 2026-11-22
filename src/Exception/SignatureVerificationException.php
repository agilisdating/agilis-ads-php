<?php

declare(strict_types=1);

namespace Agilis\Ads\Exception;

/**
 * A webhook message could not be verified by `constructEvent`.
 *
 * `reason` says why: `header` (the signature header is missing or malformed), `timestamp`
 * (outside the tolerance), `signature` (no `v1` signature matches) or `payload` (not JSON).
 */
class SignatureVerificationException extends AgilisException
{
    public const REASON_HEADER = 'header';
    public const REASON_TIMESTAMP = 'timestamp';
    public const REASON_SIGNATURE = 'signature';
    public const REASON_PAYLOAD = 'payload';

    public function __construct(string $message, public readonly string $reason)
    {
        parent::__construct($message);
    }
}
