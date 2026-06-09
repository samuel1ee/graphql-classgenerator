<?php

declare(strict_types=1);

namespace Aksonov\GraphqlGenerator;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use RuntimeException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class SchemaFetcher
{
    // language=GraphQL
    private const string QUERY = <<<'GQL'
query IntrospectionQuery {
  __schema {
    types {
      kind
      name
      description
      possibleTypes {
        kind
        name
      }
      enumValues {
        name
        description
      }
      interfaces {
        kind
        name
      }
      fields(includeDeprecated: true) {
        name
        description
        isDeprecated
        deprecationReason
        type {
          kind
          name
          possibleTypes {
            kind
            name
          }
          ofType {
            kind
            name
            possibleTypes {
              kind
              name
            }
            ofType {
              kind
              name
              possibleTypes {
                kind
                name
              }
              ofType {
                kind
                name
                possibleTypes {
                  kind
                  name
                }
              }
            }
          }
        }
      }
      inputFields(includeDeprecated: true) {
        name
        description
        defaultValue
        isDeprecated
        deprecationReason
        type {
          kind
          name
          ofType {
            kind
            name
            possibleTypes {
              kind
              name
            }
            ofType {
              kind
              name
              possibleTypes {
                kind
                name
              }
              ofType {
                kind
                name
                possibleTypes {
                  kind
                  name
                }
              }
            }
          }
        }
      }
    }
  }
}
GQL;
    private readonly HttpClientInterface $client;

    public function __construct(?HttpClientInterface $client = null)
    {
        $this->client = $client ?? HttpClient::create();
    }

    /**
     * @param array<string, string> $headers
     * @return array<string, array<string, mixed>>
     *
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function getSchema(string $endpoint, array $headers = []): array
    {
        $body = $this->fetchGraphQLSchema($endpoint, $headers);
        $decoded = json_decode($body, true);

        if (!is_array($decoded)) {
            throw new RuntimeException('GraphQL response is not valid JSON.');
        }

        /** @var array{data: array{__schema: array{types: list<array<string, mixed>>}}} $decoded */
        $schema = [];
        foreach ($decoded['data']['__schema']['types'] as $type) {
            if (isset($type['name']) && is_string($type['name'])) {
                $schema[$type['name']] = $type;
            }
        }

        return $schema;
    }

    /**
     * @param array<string, string> $headers
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     */
    private function fetchGraphQLSchema(string $endpoint, array $headers = []): string
    {
        $response = $this->client->request('POST', $endpoint, [
            'headers' => [
                ...$headers,
                'Content-Type' => 'application/json',
            ],
            'json'    => ['query' => self::QUERY],
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new RuntimeException('Error fetching schema: ' . $response->getContent());
        }

        return $response->getContent();
    }
}
