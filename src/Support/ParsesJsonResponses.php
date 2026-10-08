<?php

declare(strict_types=1);

namespace Goletter\Card\Support;

use Goletter\Card\Exceptions\CardApiException;
use Psr\Http\Message\ResponseInterface;

trait ParsesJsonResponses
{
    protected function parseResponse(ResponseInterface $response): array
    {
        $content = $response->getBody()->getContents();
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['raw' => $content];
        }

        return is_array($data) ? $data : [];
    }

    /**
     * @param array<string, mixed> $responseData
     */
    protected function extractErrorMessage(array $responseData, string $fallback): string
    {
        if (isset($responseData['message']) && is_string($responseData['message'])) {
            return $responseData['message'];
        }

        if (isset($responseData['msg']) && (is_string($responseData['msg']) || is_numeric($responseData['msg']))) {
            return (string) $responseData['msg'];
        }

        if (isset($responseData['error'])) {
            if (is_string($responseData['error'])) {
                return $responseData['error'];
            }
            if (is_array($responseData['error']) && isset($responseData['error']['message'])) {
                return (string) $responseData['error']['message'];
            }
        }

        if (isset($responseData['raw']) && is_string($responseData['raw']) && $responseData['raw'] !== '') {
            return $responseData['raw'];
        }

        return $fallback;
    }

    /**
     * @param class-string<CardApiException> $exceptionClass
     * @return array<string, mixed>
     */
    protected function decodeOrFail(
        ResponseInterface $response,
        string $exceptionClass,
        bool $allowNonJson = false
    ): array {
        $status = $response->getStatusCode();
        $data = $this->parseResponse($response);

        if (! $allowNonJson && isset($data['raw']) && count($data) === 1) {
            throw new $exceptionClass('Invalid JSON response', $status, $data);
        }

        if ($status < 200 || $status >= 300) {
            throw new $exceptionClass(
                $this->extractErrorMessage($data, 'HTTP error ' . $status),
                $status,
                $data
            );
        }

        return $data;
    }
}
