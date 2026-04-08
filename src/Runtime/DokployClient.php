<?php

declare(strict_types=1);

namespace CarmeloSantana\CoquiToolkitDokploy\Runtime;

use Symfony\Component\HttpClient\CurlHttpClient;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * HTTP client for the Dokploy REST API.
 *
 * Auth header: `x-api-key: <token>`
 * Base URL pattern: `{baseUrl}/api/{endpoint}`
 */
final class DokployClient
{
    private const int TIMEOUT = 30;

    private string $resolvedToken = '';
    private string $resolvedBaseUrl = '';

    public function __construct(
        private readonly string $baseUrl = '',
        private readonly string $apiToken = '',
        private readonly HttpClientInterface $httpClient = new CurlHttpClient(),
    ) {}

    public static function fromEnv(): self
    {
        return new self(
            baseUrl: self::envString('DOKPLOY_BASE_URL'),
            apiToken: self::envString('DOKPLOY_API_TOKEN'),
        );
    }

    // -- HTTP verbs -------------------------------------------------------

    /** @param array<string, mixed> $query */
    public function get(string $endpoint, array $query = []): DokployResult
    {
        return $this->request('GET', $endpoint, query: $query);
    }

    /** @param array<string, mixed> $body */
    public function post(string $endpoint, array $body = []): DokployResult
    {
        return $this->request('POST', $endpoint, body: $body);
    }

    /** @param array<string, mixed> $body */
    public function put(string $endpoint, array $body = []): DokployResult
    {
        return $this->request('PUT', $endpoint, body: $body);
    }

    /** @param array<string, mixed> $body */
    public function patch(string $endpoint, array $body = []): DokployResult
    {
        return $this->request('PATCH', $endpoint, body: $body);
    }

    /** @param array<string, mixed> $body */
    public function delete(string $endpoint, array $body = []): DokployResult
    {
        return $this->request('DELETE', $endpoint, body: $body);
    }

    // -- Internal ---------------------------------------------------------

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $body
     */
    private function request(
        string $method,
        string $endpoint,
        array $query = [],
        array $body = [],
    ): DokployResult {
        $baseUrl = $this->resolveBaseUrl();
        if ($baseUrl === '') {
            return DokployResult::error(
                'DOKPLOY_BASE_URL is not configured. '
                . 'Set it via the credentials tool: credentials(action: "set", key: "DOKPLOY_BASE_URL", value: "https://dokploy.example.com")',
            );
        }

        $token = $this->resolveApiToken();
        if ($token === '') {
            return DokployResult::error(
                'DOKPLOY_API_TOKEN is not configured. '
                . 'Set it via the credentials tool: credentials(action: "set", key: "DOKPLOY_API_TOKEN", value: "your-token")',
            );
        }

        $options = [
            'headers' => [
                'x-api-key' => $token,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'timeout' => self::TIMEOUT,
        ];

        if ($query !== []) {
            $options['query'] = $this->filterQuery($query);
        }

        if ($body !== [] && in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $options['json'] = $body;
        }

        $url = rtrim($baseUrl, '/') . '/api/' . ltrim($endpoint, '/');

        try {
            $response = $this->httpClient->request($method, $url, $options);
            $statusCode = $response->getStatusCode();
            return $this->parseResponse($response, $statusCode);
        } catch (HttpExceptionInterface $e) {
            $statusCode = $e->getResponse()->getStatusCode();
            try {
                $json = $e->getResponse()->toArray(false);
                $message = $json['message'] ?? $json['error'] ?? $e->getMessage();
                $errors = is_array($json['errors'] ?? null)
                    ? array_map(
                        static fn(mixed $err): array => is_array($err) ? $err : ['message' => (string) $err],
                        $json['errors'],
                    )
                    : [['message' => (string) $message]];
            } catch (\Throwable) {
                $errors = [['message' => $e->getMessage()]];
            }
            return new DokployResult(success: false, data: null, errors: $errors, statusCode: $statusCode);
        } catch (TransportExceptionInterface $e) {
            return DokployResult::error('Transport error: ' . $e->getMessage());
        }
    }

    private function parseResponse(ResponseInterface $response, int $statusCode): DokployResult
    {
        $content = $response->getContent(false);

        if ($content === '' || $content === '[]') {
            return new DokployResult(
                success: $statusCode >= 200 && $statusCode < 300,
                data: null,
                statusCode: $statusCode,
            );
        }

        $json = json_decode($content, true);

        if (!is_array($json)) {
            return new DokployResult(
                success: $statusCode >= 200 && $statusCode < 300,
                data: $content,
                statusCode: $statusCode,
            );
        }

        if ($statusCode >= 400) {
            $message = $json['message'] ?? $json['error'] ?? 'API error';
            $errors = is_array($json['errors'] ?? null)
                ? array_map(
                    static fn(mixed $err): array => is_array($err) ? $err : ['message' => (string) $err],
                    $json['errors'],
                )
                : [['message' => (string) $message]];
            return new DokployResult(success: false, data: null, errors: $errors, statusCode: $statusCode);
        }

        return new DokployResult(success: true, data: $json, statusCode: $statusCode);
    }

    /**
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    private function filterQuery(array $query): array
    {
        return array_filter($query, static fn(mixed $v): bool => $v !== null && $v !== '');
    }

    private function resolveApiToken(): string
    {
        if ($this->resolvedToken !== '') {
            return $this->resolvedToken;
        }
        if ($this->apiToken !== '') {
            $this->resolvedToken = $this->apiToken;
            return $this->resolvedToken;
        }
        $env = getenv('DOKPLOY_API_TOKEN');
        $this->resolvedToken = is_string($env) && $env !== '' ? $env : '';
        return $this->resolvedToken;
    }

    private function resolveBaseUrl(): string
    {
        if ($this->resolvedBaseUrl !== '') {
            return $this->resolvedBaseUrl;
        }
        if ($this->baseUrl !== '') {
            $this->resolvedBaseUrl = rtrim($this->baseUrl, '/');
            return $this->resolvedBaseUrl;
        }
        $env = getenv('DOKPLOY_BASE_URL');
        $this->resolvedBaseUrl = is_string($env) && $env !== '' ? rtrim($env, '/') : '';
        return $this->resolvedBaseUrl;
    }

    private static function envString(string $name): string
    {
        $value = getenv($name);
        return is_string($value) && $value !== '' ? $value : '';
    }
}
