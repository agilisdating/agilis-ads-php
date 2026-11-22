<?php

declare(strict_types=1);

namespace Agilis\Ads\Tests\Support;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * A PSR-18 client that answers from a queue and records every request it is sent.
 */
final class MockHttpClient implements ClientInterface
{
    /** @var list<RequestInterface> */
    public array $requests = [];

    /** @var list<ResponseInterface|\Closure(RequestInterface): ResponseInterface> */
    private array $queue = [];

    /**
     * @param ResponseInterface|\Closure(RequestInterface): ResponseInterface ...$responses
     */
    public function queue(ResponseInterface|\Closure ...$responses): self
    {
        foreach ($responses as $response) {
            $this->queue[] = $response;
        }

        return $this;
    }

    /**
     * @param array<string, mixed>        $body
     * @param array<string, string|list<string>> $headers
     */
    public function queueJson(array $body, int $status = 200, array $headers = []): self
    {
        return $this->queue(self::json($body, $status, $headers));
    }

    /**
     * @param array<string, mixed>        $body
     * @param array<string, string|list<string>> $headers
     */
    public static function json(array $body, int $status = 200, array $headers = []): Response
    {
        return new Response(
            $status,
            ['Content-Type' => 'application/json'] + $headers,
            json_encode($body, JSON_THROW_ON_ERROR),
        );
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;
        $next = array_shift($this->queue);

        if ($next === null) {
            return self::json(['return_code' => 'S1']);
        }

        return $next instanceof \Closure ? $next($request) : $next;
    }

    public function last(): RequestInterface
    {
        $last = end($this->requests);

        if ($last === false) {
            throw new \LogicException('No request was sent.');
        }

        return $last;
    }
}
