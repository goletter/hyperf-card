<?php

declare(strict_types=1);

namespace Goletter\Card\Platforms\Youcardx;

use Goletter\Card\Platforms\Youcardx\Exceptions\YoucardxApiException;

/**
 * Webhook 通知验签与解析。商户需在 10 秒内返回 HTTP 200，否则按 1/5/30/60/120 分钟重试。
 */
class Webhook
{
    public const HEADER_MESSAGE_TYPE = 'Webhook-MessageType';

    public const HEADER_TIMESTAMP = 'Webhook-Timestamp';

    public const HEADER_SIGNATURE = 'Webhook-Signature';

    public const TYPE_CARD_BATCH = 'CardBatch';

    public const TYPE_CARD_CREATE = 'CardCreate';

    public const TYPE_CARD_ORDER = 'CardOrder';

    public const TYPE_CARD_STATUS = 'CardStatus';

    public const TYPE_CARD_STATUS_OPERATE = 'CardStatusOperate';

    public const TYPE_CARD_TRANSACTION = 'CardTransaction';

    public const TYPE_CARD_LIMIT_AMOUNT = 'CardLimitAmount';

    public const TYPE_BUDGET_TRANSFER = 'BudgetTransfer';

    public const TYPE_BUDGET_ACTIVATE = 'BudgetActivate';

    public const TYPE_CARDHOLDER_AUDIT = 'CardHolderAudit';

    public function __construct(protected string $secret)
    {
    }

    /**
     * data = "{Webhook-MessageType}.{Webhook-Timestamp}.{rawBody}"
     */
    public function verify(string $messageType, string $timestamp, string $rawBody, string $signature): bool
    {
        if ($signature === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $messageType . '.' . $timestamp . '.' . $rawBody, $this->secret);

        return hash_equals($expected, $signature);
    }

    /**
     * @param array<string, string|string[]> $headers 请求头（大小写不敏感）
     * @return array{type: string, timestamp: string, data: array}
     */
    public function parse(array $headers, string $rawBody): array
    {
        $messageType = $this->header($headers, self::HEADER_MESSAGE_TYPE);
        $timestamp = $this->header($headers, self::HEADER_TIMESTAMP);
        $signature = $this->header($headers, self::HEADER_SIGNATURE);

        if (! $this->verify($messageType, $timestamp, $rawBody, $signature)) {
            throw new YoucardxApiException('YouCard webhook signature verification failed.', 0, [
                'type' => $messageType,
                'raw' => $rawBody,
            ]);
        }

        $data = json_decode($rawBody, true);

        return [
            'type' => $messageType,
            'timestamp' => $timestamp,
            'data' => is_array($data) ? $data : [],
        ];
    }

    protected function header(array $headers, string $name): string
    {
        foreach ($headers as $key => $value) {
            if (strcasecmp((string) $key, $name) === 0) {
                return (string) (is_array($value) ? ($value[0] ?? '') : $value);
            }
        }

        return '';
    }
}
