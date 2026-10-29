<?php

declare(strict_types=1);

namespace Goletter\Card\Support;

/**
 * HTTP 请求耗时统计与调用日志（对齐 hyperf-akm）。
 *
 * - log_request=false：失败时尝试写 call_logs（需 hyperf-utils）
 * - log_request=true：成功/失败都写 call_logs
 * - CallLog::setHandler：有 handler 时每次请求回调（含 duration_ms），与开关独立
 */
trait LogsHttpRequests
{
    use SafeLogs;

    protected bool $logRequest = false;

    protected function elapsedMs(float $startedAt): int
    {
        return (int) max(0, round((microtime(true) - $startedAt) * 1000));
    }

    protected function normalizeApiInterface(string $method, string $path): string
    {
        $uri = $path;
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            $parsed = parse_url($path);
            $uri = $parsed['path'] ?? $path;
        }

        $normalized = '/' . ltrim($uri, '/');
        $normalized = preg_replace(
            '#[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}#i',
            '{id}',
            $normalized
        ) ?: $normalized;
        $normalized = preg_replace('#/\d+(?=/|$)#', '/{id}', $normalized) ?: $normalized;

        return strtoupper($method) . ':' . $normalized;
    }

    protected function pushCallLog(
        string $platform,
        string $method,
        string $path,
        bool $success,
        string $error = '',
        int $durationMs = 0,
    ): void {
        $apiInterface = $this->normalizeApiInterface($method, $path);

        $payload = [
            'message' => $error,
            'url' => $path,
            'platform' => $platform,
            'api_interface' => $apiInterface,
            'success' => $success,
            'duration_ms' => $durationMs,
        ];

        // 失败始终尝试写 call_logs；开启 log_request 时成功请求也写
        if ($this->logRequest || $error !== '' || ! $success) {
            $this->safeLog($payload, '接口日志', 'call_logs');
        }

        $handler = CallLog::handler();
        if (! is_callable($handler)) {
            return;
        }

        $handler([
            'platform' => $platform,
            'api_interface' => $apiInterface,
            'is_success' => $success ? 1 : 0,
            'error' => $error,
            'duration_ms' => $durationMs,
            'call_hour' => date('Y-m-d H:00:00'),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
