<?php

declare(strict_types=1);

namespace Agilis\Ads\Internal;

use Agilis\Ads\ApiResponse;
use Agilis\Ads\Exception\AgilisException;
use Agilis\Ads\Exception\ApiException;
use Agilis\Ads\Exception\AuthenticationException;
use Agilis\Ads\Exception\ConflictException;
use Agilis\Ads\Exception\ConnectionException;
use Agilis\Ads\Exception\InvalidRequestException;
use Agilis\Ads\Exception\NotFoundException;
use Agilis\Ads\Exception\PermissionException;
use Agilis\Ads\Exception\RateLimitException;
use Agilis\Ads\Page;
use Agilis\Ads\Version;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Client\RequestExceptionInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Sends requests: headers, retries, waits and error mapping.
 *
 * @internal
 */
final class Transport
{
    private const RETRYABLE_SERVER_STATUSES = [500, 502, 503, 504];
    private const BASE_WAIT = 0.5;
    private const MAX_WAIT = 8.0;

    private readonly string $userAgent;

    /** @var \Closure(float): void */
    private readonly \Closure $sleep;

    /** @var \Closure(): float */
    private readonly \Closure $random;

    /**
     * @param (callable(float): void)|null $sleep  waits the given number of seconds
     * @param (callable(): float)|null     $random a number from 0 to 1, for the jitter
     */
    public function __construct(
        #[\SensitiveParameter]
        private readonly string $apiKey,
        private readonly string $baseUrl,
        private readonly int $maxRetries,
        private readonly ClientInterface $http,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
        ?callable $sleep = null,
        ?callable $random = null,
    ) {
        $this->userAgent = sprintf('agilis-ads-php/%s (PHP %s)', Version::VERSION, PHP_VERSION);
        $this->sleep = $sleep !== null
            ? \Closure::fromCallable($sleep)
            : static function (float $seconds): void {
                if ($seconds > 0) {
                    usleep((int) round($seconds * 1_000_000));
                }
            };
        $this->random = $random !== null
            ? \Closure::fromCallable($random)
            : static fn(): float => mt_rand() / mt_getrandmax();
    }

    /**
     * @param array<string, mixed>  $query
     * @param array<string, string> $headers
     */
    public function request(
        string $method,
        string $path,
        array $query = [],
        ?Body $body = null,
        array $headers = [],
        bool $page = false,
    ): ApiResponse {
        $hasIdempotencyKey = isset($headers['Idempotency-Key']);
        $attempt = 0;

        while (true) {
            $request = $this->buildRequest($method, $path, $query, $body, $headers);

            try {
                $response = $this->http->sendRequest($request);
            } catch (ClientExceptionInterface $e) {
                $retryable = $e instanceof NetworkExceptionInterface || !$e instanceof RequestExceptionInterface;

                if ($retryable && $attempt < $this->maxRetries) {
                    ($this->sleep)($this->backoff($attempt));
                    ++$attempt;
                    continue;
                }

                throw new ConnectionException($this->redact(sprintf(
                    'Could not get a response from the Agilis API (%s): %s',
                    (new \ReflectionClass($e))->getShortName(),
                    $e->getMessage(),
                )));
            }

            $status = $response->getStatusCode();

            if ($attempt < $this->maxRetries && $this->shouldRetry($method, $status, $hasIdempotencyKey)) {
                ($this->sleep)($this->retryAfter($response) ?? $this->backoff($attempt));
                ++$attempt;
                continue;
            }

            return $this->handle($response, $page);
        }
    }

    public function shouldRetry(string $method, int $status, bool $hasIdempotencyKey): bool
    {
        if ($status === 429) {
            return true;
        }

        if (!in_array($status, self::RETRYABLE_SERVER_STATUSES, true)) {
            return false;
        }

        $method = strtoupper($method);

        return $method === 'GET' || $method === 'DELETE' || ($method === 'POST' && $hasIdempotencyKey);
    }

    /**
     * The wait before retry number `$attempt + 1`: 0.5 s × 2^attempt, ±25% jitter, at most 8 s.
     */
    public function backoff(int $attempt): float
    {
        $base = min(self::MAX_WAIT, self::BASE_WAIT * (2 ** $attempt));
        $jitter = 1 + ((($this->random)() * 0.5) - 0.25);

        return min(self::MAX_WAIT, $base * $jitter);
    }

    /**
     * The scrubbed text: the API key, if it ever appears, is replaced.
     */
    public function redact(string $text): string
    {
        return $this->apiKey === '' ? $text : str_replace($this->apiKey, '[redacted]', $text);
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return ['baseUrl' => $this->baseUrl, 'maxRetries' => $this->maxRetries, 'apiKey' => '[redacted]'];
    }

    /**
     * @param array<string, mixed>  $query
     * @param array<string, string> $headers
     */
    private function buildRequest(string $method, string $path, array $query, ?Body $body, array $headers): RequestInterface
    {
        $url = rtrim($this->baseUrl, '/') . $path;
        $queryString = self::queryString($query);

        if ($queryString !== '') {
            $url .= '?' . $queryString;
        }

        $request = $this->requestFactory->createRequest($method, $url)
            ->withHeader('x-api-key', $this->apiKey)
            ->withHeader('Accept', 'application/json')
            ->withHeader('User-Agent', $this->userAgent);

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        if ($body !== null) {
            $request = $request
                ->withHeader('Content-Type', $body->contentType)
                ->withBody($this->streamFactory->createStream($body->content));
        }

        return $request;
    }

    /**
     * @param array<string, mixed> $query
     */
    private static function queryString(array $query): string
    {
        $pairs = [];

        foreach ($query as $name => $value) {
            if ($value === null) {
                continue;
            }

            if (is_array($value)) {
                foreach ($value as $item) {
                    $pairs[] = rawurlencode($name . '[]') . '=' . rawurlencode(Body::scalar($item));
                }

                continue;
            }

            $pairs[] = rawurlencode((string) $name) . '=' . rawurlencode(Body::scalar($value));
        }

        return implode('&', $pairs);
    }

    private function retryAfter(ResponseInterface $response): ?float
    {
        $value = trim($response->getHeaderLine('Retry-After'));

        if ($value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return max(0.0, (float) $value);
        }

        $time = strtotime($value);

        return $time === false ? null : (float) max(0, $time - time());
    }

    private function handle(ResponseInterface $response, bool $page): ApiResponse
    {
        $status = $response->getStatusCode();
        $raw = (string) $response->getBody();
        $headers = self::headers($response);
        $decoded = null;

        if ($raw !== '') {
            try {
                $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                $decoded = null;
            }
        }

        /** @var array<string, mixed> $data */
        $data = is_array($decoded) ? (array_is_list($decoded) && $decoded !== [] ? ['data' => $decoded] : $decoded) : [];
        $returnCode = isset($data['return_code']) && is_string($data['return_code']) ? $data['return_code'] : null;

        if ($status >= 200 && $status < 300) {
            if ($returnCode !== null && str_starts_with(strtoupper($returnCode), 'E')) {
                throw new InvalidRequestException(
                    $this->message($data, $status, $returnCode),
                    $status,
                    $returnCode,
                    $decoded,
                    $headers,
                );
            }

            return $page
                ? new Page($data, $status, $headers, $raw)
                : new ApiResponse($data, $status, $headers, $raw);
        }

        throw $this->error($status, $returnCode, $data, $decoded ?? ($raw === '' ? null : $raw), $headers, $response);
    }

    /**
     * @param array<string, mixed>        $data
     * @param array<string, list<string>> $headers
     */
    private function error(
        int $status,
        ?string $returnCode,
        array $data,
        mixed $body,
        array $headers,
        ResponseInterface $response,
    ): AgilisException {
        $message = $this->message($data, $status, $returnCode);

        return match (true) {
            $status === 401 => new AuthenticationException($message, $status, $returnCode, $body, $headers),
            $status === 403 => new PermissionException($message, $status, $returnCode, $body, $headers),
            $status === 404 => new NotFoundException($message, $status, $returnCode, $body, $headers),
            $status === 409 => new ConflictException($message, $status, $returnCode, $body, $headers),
            $status === 400, $status === 422 => new InvalidRequestException($message, $status, $returnCode, $body, $headers),
            $status === 429 => new RateLimitException($message, $status, $returnCode, $body, $headers, $this->retryAfter($response)),
            default => new ApiException($message, $status, $returnCode, $body, $headers),
        };
    }

    /**
     * @param array<string, mixed> $data
     */
    private function message(array $data, int $status, ?string $returnCode): string
    {
        $message = $data['message'] ?? $data['error'] ?? null;

        if (is_string($message) && trim($message) !== '') {
            $text = $message;
        } elseif ($returnCode !== null) {
            $text = sprintf('The Agilis API refused the request (HTTP %d, return code %s).', $status, $returnCode);
        } else {
            $text = sprintf('The Agilis API returned HTTP %d.', $status);
        }

        return $this->redact($text);
    }

    /**
     * @return array<string, list<string>>
     */
    private static function headers(ResponseInterface $response): array
    {
        $out = [];

        foreach ($response->getHeaders() as $name => $values) {
            $out[strtolower((string) $name)] = array_values($values);
        }

        return $out;
    }
}
