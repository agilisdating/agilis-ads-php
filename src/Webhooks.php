<?php

declare(strict_types=1);

namespace Agilis\Ads;

use Agilis\Ads\Exception\SignatureVerificationException;

/**
 * Verifies the webhook messages Agilis sends to your endpoints.
 *
 * Needs no API key: `new Webhooks()` works on its own in a receiver.
 */
final class Webhooks
{
    /** The request header that carries the signature. */
    public const SIGNATURE_HEADER = 'Agilis-Signature';

    /** How far, in seconds, the signed timestamp may be from now. */
    public const DEFAULT_TOLERANCE = 300;

    /**
     * Checks the signature of a received message and returns the parsed event.
     *
     * @param string   $payload         the request body exactly as received, before any JSON parsing
     * @param string   $signatureHeader the `Agilis-Signature` header
     * @param string   $secret          the endpoint's signing secret (`whsec_…`)
     * @param int      $tolerance       seconds; 0 or less turns the timestamp check off
     * @param int|null $now             the current Unix time, for tests
     *
     * @throws SignatureVerificationException when the message cannot be verified
     */
    public function constructEvent(
        string $payload,
        string $signatureHeader,
        #[\SensitiveParameter]
        string $secret,
        int $tolerance = self::DEFAULT_TOLERANCE,
        ?int $now = null,
    ): WebhookEvent {
        $this->verifyHeader($payload, $signatureHeader, $secret, $tolerance, $now);

        try {
            $message = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new SignatureVerificationException('The webhook body is not valid JSON.', SignatureVerificationException::REASON_PAYLOAD);
        }

        if (!is_array($message) || array_is_list($message)) {
            throw new SignatureVerificationException('The webhook body is not a JSON object.', SignatureVerificationException::REASON_PAYLOAD);
        }

        /** @var array<string, mixed> $message */
        return WebhookEvent::fromArray($message);
    }

    /**
     * Checks the signature only. Accepts the message when any `v1` signature matches.
     *
     * @throws SignatureVerificationException
     */
    public function verifyHeader(
        string $payload,
        string $signatureHeader,
        #[\SensitiveParameter]
        string $secret,
        int $tolerance = self::DEFAULT_TOLERANCE,
        ?int $now = null,
    ): void {
        [$timestamp, $signatures] = self::parseHeader($signatureHeader);

        if ($secret === '') {
            throw new SignatureVerificationException('No webhook secret was given.', SignatureVerificationException::REASON_SIGNATURE);
        }

        $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
        $matched = false;

        foreach ($signatures as $signature) {
            if (hash_equals($expected, strtolower($signature))) {
                $matched = true;
            }
        }

        if (!$matched) {
            throw new SignatureVerificationException(
                'No signature in the Agilis-Signature header matches the body. Check the secret and that the body is passed exactly as received.',
                SignatureVerificationException::REASON_SIGNATURE,
            );
        }

        $now ??= time();

        if ($tolerance > 0 && abs($now - $timestamp) > $tolerance) {
            throw new SignatureVerificationException(
                'The Agilis-Signature timestamp is outside the tolerance.',
                SignatureVerificationException::REASON_TIMESTAMP,
            );
        }
    }

    /**
     * @return array{0: int, 1: list<string>}
     */
    private static function parseHeader(string $header): array
    {
        $timestamp = null;
        $signatures = [];

        foreach (explode(',', $header) as $part) {
            $pair = explode('=', trim($part), 2);

            if (count($pair) !== 2) {
                continue;
            }

            [$key, $value] = [trim($pair[0]), trim($pair[1])];

            if ($key === 't' && $timestamp === null) {
                $timestamp = $value;
            } elseif ($key === 'v1' && $value !== '') {
                $signatures[] = $value;
            }
        }

        if ($timestamp === null || $timestamp === '' || !ctype_digit($timestamp)) {
            throw new SignatureVerificationException(
                'The Agilis-Signature header has no valid timestamp.',
                SignatureVerificationException::REASON_HEADER,
            );
        }

        if ($signatures === []) {
            throw new SignatureVerificationException(
                'The Agilis-Signature header has no v1 signature.',
                SignatureVerificationException::REASON_HEADER,
            );
        }

        return [(int) $timestamp, $signatures];
    }
}
