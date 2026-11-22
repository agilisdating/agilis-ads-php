<?php

declare(strict_types=1);

namespace Agilis\Ads\Resource;

use Agilis\Ads\ApiResponse;
use Agilis\Ads\Internal\Body;
use Agilis\Ads\Internal\Transport;
use Agilis\Ads\Page;

/**
 * @internal the shared plumbing of the resource classes
 */
abstract class AbstractResource
{
    /**
     * @param string $mode `test` or `live`
     */
    public function __construct(
        protected readonly Transport $transport,
        protected readonly string $mode,
    ) {}

    /**
     * @param array<string, mixed> $query
     */
    protected function getJson(string $path, array $query = []): ApiResponse
    {
        return $this->transport->request('GET', $path, $query);
    }

    /**
     * @param array<string, mixed> $query
     */
    protected function getPage(string $path, array $query = []): Page
    {
        $page = $this->transport->request('GET', $path, $query, page: true);
        \assert($page instanceof Page);

        return $page;
    }

    /**
     * Follows `next_cursor` until `has_more` is false, yielding every item.
     *
     * @param array<string, mixed> $query
     *
     * @return \Generator<int, array<string, mixed>>
     */
    protected function autoPage(string $path, array $query): \Generator
    {
        while (true) {
            $page = $this->getPage($path, $query);

            foreach ($page->items() as $item) {
                yield $item;
            }

            $cursor = $page->nextCursor();

            if (!$page->hasMore() || $cursor === null) {
                return;
            }

            $query['cursor'] = $cursor;
        }
    }

    /**
     * @param array<string, mixed>|null $params null sends no body
     * @param array<string, mixed>      $query
     * @param array<string, string>     $headers
     */
    protected function sendJson(string $method, string $path, ?array $params, array $query = [], array $headers = []): ApiResponse
    {
        return $this->transport->request($method, $path, $query, $params === null ? null : Body::json($params), $headers);
    }

    /**
     * @param array<string, mixed>  $params
     * @param array<string, mixed>  $query
     * @param array<string, string> $headers
     */
    protected function sendMultipart(string $method, string $path, array $params, array $query = [], array $headers = []): ApiResponse
    {
        return $this->transport->request($method, $path, $query, Body::multipart($params), $headers);
    }

    /**
     * @param array<string, mixed> $query
     */
    protected function sendDelete(string $path, array $query = []): ApiResponse
    {
        return $this->transport->request('DELETE', $path, $query);
    }

    /**
     * @return array<string, mixed>
     */
    protected static function dryRunQuery(bool $dryRun): array
    {
        return $dryRun ? ['dry_run' => true] : [];
    }

    /**
     * Adds `environment` from the key's mode when the caller left it out.
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    protected function withEnvironment(array $params, bool $upper = true): array
    {
        if (!isset($params['environment']) || $params['environment'] === '') {
            $params['environment'] = $upper ? strtoupper($this->mode) : $this->mode;
        }

        return $params;
    }

    protected static function idempotencyKey(?string $key): string
    {
        if ($key !== null && $key !== '') {
            return $key;
        }

        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    protected static function segment(int|string $id): string
    {
        return rawurlencode((string) $id);
    }
}
