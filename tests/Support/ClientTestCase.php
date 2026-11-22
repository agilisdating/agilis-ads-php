<?php

declare(strict_types=1);

namespace Agilis\Ads\Tests\Support;

use Agilis\Ads\AgilisClient;
use GuzzleHttp\Psr7\HttpFactory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

abstract class ClientTestCase extends TestCase
{
    protected const TEST_KEY = 'pk_test_unit0123456789abcdef';
    protected const LIVE_KEY = 'pk_live_unit0123456789abcdef';

    protected MockHttpClient $http;

    /** @var list<float> */
    protected array $sleeps = [];

    protected function setUp(): void
    {
        $this->http = new MockHttpClient();
        $this->sleeps = [];
    }

    protected function client(string $key = self::TEST_KEY, int $maxRetries = 2): AgilisClient
    {
        $factory = new HttpFactory();

        return new AgilisClient(
            $key,
            maxRetries: $maxRetries,
            httpClient: $this->http,
            requestFactory: $factory,
            streamFactory: $factory,
            sleep: function (float $seconds): void {
                $this->sleeps[] = $seconds;
            },
        );
    }

    /**
     * @return array<string, string>
     */
    protected static function query(RequestInterface $request): array
    {
        parse_str($request->getUri()->getQuery(), $query);

        /** @var array<string, string> $query */
        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    protected static function jsonBody(RequestInterface $request): array
    {
        $decoded = json_decode((string) $request->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /**
     * The parts of a multipart body, in order.
     *
     * @return list<array{name: string, filename: ?string, type: ?string, value: string}>
     */
    protected static function parts(RequestInterface $request): array
    {
        $contentType = $request->getHeaderLine('Content-Type');
        self::assertStringStartsWith('multipart/form-data; boundary=', $contentType);
        $boundary = substr($contentType, strlen('multipart/form-data; boundary='));
        $body = (string) $request->getBody();
        self::assertStringEndsWith('--' . $boundary . "--\r\n", $body);

        $chunks = explode('--' . $boundary, $body);
        $parts = [];

        foreach (array_slice($chunks, 1, -1) as $chunk) {
            $chunk = substr($chunk, 2, -2);
            [$head, $value] = explode("\r\n\r\n", $chunk, 2);
            preg_match('/name="([^"]*)"/', $head, $name);
            preg_match('/filename="([^"]*)"/', $head, $filename);
            preg_match('/Content-Type: (\S+)/', $head, $type);
            $parts[] = [
                'name' => $name[1] ?? '',
                'filename' => $filename[1] ?? null,
                'type' => $type[1] ?? null,
                'value' => $value,
            ];
        }

        return $parts;
    }

    /**
     * Field name => values, for the non-file parts.
     *
     * @return array<string, list<string>>
     */
    protected static function fields(RequestInterface $request): array
    {
        $out = [];

        foreach (self::parts($request) as $part) {
            if ($part['filename'] === null) {
                $out[$part['name']][] = $part['value'];
            }
        }

        return $out;
    }
}
