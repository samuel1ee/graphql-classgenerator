<?php

declare(strict_types=1);

namespace Aksonov\GraphqlGenerator;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class SchemaFetcher
{
    // language=GraphQL
    private const QUERY = <<<'GQL'
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

    public function __construct()
    {
        $this->client = HttpClient::create();
    }

    /**
     * @param string $endpoint
     * @param array<string, string> $headers
     * @return array
     *
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function getSchema(string $endpoint, array $headers = []): array
    {
        $schema = $this->fetchGraphQLSchema($endpoint, $headers);
        $decoded = json_decode($schema, true);
        $schema = [];

        foreach ($decoded['data']['__schema']['types'] as $type) {
            $schema[$type['name']] = $type;
        }

        return $schema;
    }

    /**
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     */
    private function fetchGraphQLSchema($endpoint, $headers = []): string
    {
        $response = $this->client->request('POST', $endpoint, [
            'headers' => [
                ...$headers,
                'Content-Type' => 'application/json',
            ],
            'json'    => ['query' => self::QUERY],
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new \Exception('Error fetching schema: ' . $response->getContent());
        }

        return $response->getContent();
    }
}
