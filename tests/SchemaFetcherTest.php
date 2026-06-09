<?php

declare(strict_types=1);

namespace Aksonov\GraphqlGenerator\Tests;

use Aksonov\GraphqlGenerator\SchemaFetcher;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class SchemaFetcherTest extends TestCase
{
    public function testGetSchemaKeysByTypeName(): void
    {
        $payload = json_encode([
            'data' => [
                '__schema' => [
                    'types' => [
                        ['name' => 'Product', 'kind' => 'OBJECT'],
                        ['name' => 'String', 'kind' => 'SCALAR'],
                    ],
                ],
            ],
        ]);
        $this->assertIsString($payload);

        $client = new MockHttpClient([new MockResponse($payload, ['http_code' => 200])]);
        $fetcher = new SchemaFetcher($client);

        $schema = $fetcher->getSchema('https://example.com/graphql');

        $this->assertArrayHasKey('Product', $schema);
        $this->assertArrayHasKey('String', $schema);
        $this->assertSame('OBJECT', $schema['Product']['kind']);
    }

    public function testGetSchemaThrowsOnNon200Response(): void
    {
        $client = new MockHttpClient([new MockResponse('Unauthorized', ['http_code' => 401])]);
        $fetcher = new SchemaFetcher($client);

        $this->expectException(\Exception::class);
        $fetcher->getSchema('https://example.com/graphql');
    }

    public function testGetSchemaPassesAuthHeader(): void
    {
        $payload = json_encode(['data' => ['__schema' => ['types' => []]]]);
        $this->assertIsString($payload);

        $capturedOptions = [];
        $client = new MockHttpClient(
            function (string $method, string $url, array $options) use ($payload, &$capturedOptions): MockResponse {
                $capturedOptions = $options;
                return new MockResponse($payload, ['http_code' => 200]);
            }
        );

        $fetcher = new SchemaFetcher($client);
        $fetcher->getSchema('https://example.com/graphql', ['X-Token' => 'abc123']);

        $headers = $capturedOptions['headers'] ?? [];
        $this->assertIsArray($headers);
        $this->assertContains('X-Token: abc123', $headers);
    }
}
