<?php

declare(strict_types=1);

namespace Goletter\Card\Platforms\Slash;

use Goletter\Card\Contract\PlatformClientInterface;
use Goletter\Card\Platforms\Slash\Exceptions\SlashApiException;
use Goletter\Card\Platforms\Slash\Exceptions\SlashTokenExpiredException;
use Goletter\Card\Support\ParsesJsonResponses;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;

/**
 * HTTP client for Slash. Sends apiKey as X-API-Key; sends rsaPublicKey as X-RSA-Public-Key when configured.
 */
class Client implements PlatformClientInterface
{
    use ParsesJsonResponses;

    protected Config $config;

    protected GuzzleClient $httpClient;

    public function __construct(Config|array $config)
    {
        if (is_array($config)) {
            $config = new Config($config);
        }
        $this->config = $config;
        $headers = [
            'Content-Type' => 'application/json',
            'X-API-Key' => $this->config->apiKey,
            'requestSource' => (string) $this->config->requestSource,
            'Accept-Encoding' => 'identity',
        ];
        if ($this->config->rsaPublicKey !== '') {
            $headers['X-RSA-Public-Key'] = $this->config->rsaPublicKey;
        }
        $this->httpClient = new GuzzleClient([
            'base_uri' => $this->config->baseUrl . '/',
            'timeout' => $this->config->timeout,
            'http_errors' => false,
            'headers' => $headers,
        ]);
    }

    public function getConfig(): Config
    {
        return $this->config;
    }

    /**
     * @param array<string, mixed> $query
     * @throws SlashApiException
     */
    public function get(string $path, array $query = [], bool $requireAuth = true, ?string $absoluteBaseUri = null): array
    {
        $options = ['query' => $query];
        if ($absoluteBaseUri !== null && $absoluteBaseUri !== '') {
            $options['absolute_base_uri'] = $absoluteBaseUri;
            if ($this->config->accessToken !== '') {
                $options['headers'] = [
                    'Authorization' => 'Bearer ' . $this->config->accessToken,
                ];
            }
        }

        return $this->request('GET', $path, $options);
    }

    public function hasRsaPublicKey(): bool
    {
        return $this->config->rsaPublicKey !== '';
    }

    public function getPrivateKeyPem(): string
    {
        return $this->config->privateKey;
    }

    public function vaultBaseUriForCardSecrets(): ?string
    {
        $v = trim($this->config->vaultBaseUrl);
        if ($v === '') {
            return null;
        }

        return rtrim($v, '/');
    }

    /**
     * @throws SlashApiException
     */
    public function post(string $path, array $data = []): array
    {
        return $this->request('POST', $path, ['json' => $data]);
    }

    /**
     * @throws SlashApiException
     */
    public function put(string $path, array $data = []): array
    {
        return $this->request('PUT', $path, ['json' => $data]);
    }

    /**
     * @throws SlashApiException
     */
    public function patch(string $path, array $data = []): array
    {
        return $this->request('PATCH', $path, ['json' => $data]);
    }

    /**
     * @throws SlashApiException
     */
    public function delete(string $path): array
    {
        return $this->request('DELETE', $path);
    }

    /**
     * @param array<string, mixed> $options
     * @throws SlashApiException
     */
    public function request(string $method, string $path, array $options = []): array
    {
        $uri = ltrim($path, '/');
        if (! empty($options['absolute_base_uri'])) {
            $uri = rtrim((string) $options['absolute_base_uri'], '/') . '/' . ltrim($path, '/');
            unset($options['absolute_base_uri']);
        }

        try {
            $response = $this->httpClient->request($method, $uri, $options);
            $status = $response->getStatusCode();
            $data = $this->parseResponse($response);

            if (in_array($status, [401, 403], true)) {
                throw new SlashTokenExpiredException(
                    'API Key invalid or expired',
                    $status,
                    $data
                );
            }

            if ($status < 200 || $status >= 300) {
                throw new SlashApiException(
                    $this->extractErrorMessage($data, 'HTTP error ' . $status),
                    $status,
                    $data
                );
            }

            return $data;
        } catch (SlashApiException $e) {
            throw $e;
        } catch (RequestException $e) {
            throw $this->handleException($e);
        } catch (GuzzleException $e) {
            throw new SlashApiException($e->getMessage(), 0, [], $e);
        }
    }

    protected function handleException(RequestException $e): SlashApiException
    {
        $response = null;
        $responseData = [];

        if ($e->hasResponse()) {
            $response = $e->getResponse();
            $responseData = $this->parseResponse($response);

            if (in_array($response->getStatusCode(), [401, 403], true)) {
                return new SlashTokenExpiredException(
                    'API Key invalid or expired',
                    $response->getStatusCode(),
                    $responseData,
                    $e
                );
            }
        }

        return new SlashApiException(
            $this->extractErrorMessage($responseData, $e->getMessage()),
            $response ? $response->getStatusCode() : 0,
            $responseData,
            $e
        );
    }
}
