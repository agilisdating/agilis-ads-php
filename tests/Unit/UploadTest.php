<?php

declare(strict_types=1);

namespace Agilis\Ads\Tests\Unit;

use Agilis\Ads\Tests\Support\ClientTestCase;
use Agilis\Ads\Upload;
use GuzzleHttp\Psr7\Utils;

final class UploadTest extends ClientTestCase
{
    private const SAMPLE = __DIR__ . '/../Fixtures/sample-offer.png';

    public function testFromPath(): void
    {
        $upload = Upload::fromPath(self::SAMPLE);

        self::assertSame('sample-offer.png', $upload->filename);
        self::assertSame('image/png', $upload->contentType);
        self::assertSame(file_get_contents(self::SAMPLE), $upload->contents);
    }

    public function testFromBytesAndContentTypes(): void
    {
        self::assertSame('image/jpeg', Upload::fromBytes('x', 'a.jpg')->contentType);
        self::assertSame('image/jpeg', Upload::fromBytes('x', 'a.JPEG')->contentType);
        self::assertSame('image/gif', Upload::fromBytes('x', 'a.gif')->contentType);
        self::assertSame('image/webp', Upload::fromBytes('x', 'a.webp')->contentType);
        self::assertSame('application/octet-stream', Upload::fromBytes('x', 'a')->contentType);
        self::assertSame('image/png', (new Upload('x', 'a.bin', 'image/png'))->contentType);
    }

    public function testFromStreams(): void
    {
        $resource = fopen('php://memory', 'r+');
        self::assertNotFalse($resource);
        fwrite($resource, 'BYTES');
        rewind($resource);

        $upload = Upload::fromStream($resource, 'photo.png');
        self::assertSame('BYTES', $upload->contents);
        self::assertSame('photo.png', $upload->filename);

        $psr = Utils::streamFor('PSR');
        self::assertSame('PSR', Upload::fromStream($psr, 'p.jpg')->contents);

        $file = fopen(self::SAMPLE, 'rb');
        self::assertNotFalse($file);
        self::assertSame('sample-offer.png', Upload::fromStream($file)->filename);
        fclose($file);
    }

    public function testAMissingFileIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Upload::fromPath(__DIR__ . '/missing.png');
    }

    public function testAPathAsTheImageIsUploaded(): void
    {
        $this->client()->events->preview(['title' => 'T', 'image' => self::SAMPLE]);

        $parts = self::parts($this->http->last());
        self::assertSame('image', $parts[1]['name']);
        self::assertSame('sample-offer.png', $parts[1]['filename']);
        self::assertSame('image/png', $parts[1]['type']);
        self::assertSame(file_get_contents(self::SAMPLE), $parts[1]['value']);
    }

    public function testAStreamAsTheImageIsUploaded(): void
    {
        $file = fopen(self::SAMPLE, 'rb');
        self::assertNotFalse($file);

        $this->client()->events->preview(['image' => $file]);
        fclose($file);

        $parts = self::parts($this->http->last());
        self::assertSame('sample-offer.png', $parts[0]['filename']);
        self::assertSame(filesize(self::SAMPLE), strlen($parts[0]['value']));
    }

    public function testRepeatedPartsKeepAlreadyBracketedNames(): void
    {
        $this->client()->events->preview(['location[]' => [1.25, 2], 'crop[x]' => 5]);

        self::assertSame(['location[]' => ['1.25', '2'], 'crop[x]' => ['5'], 'environment' => ['TEST']], self::fields($this->http->last()));
    }
}
