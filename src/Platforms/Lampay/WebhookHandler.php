<?php

declare(strict_types=1);

namespace Goletter\Card\Platforms\Lampay;

use Goletter\Card\Platforms\Lampay\Exceptions\LampayApiException;

class WebhookHandler
{
    protected Signer $signer;

    protected Crypto $crypto;

    public function __construct(protected Config $config)
    {
        $this->signer = new Signer($config);
        $this->crypto = new Crypto($config);
    }

    /**
     * Verify, decrypt and parse a webhook delivery.
     *
     * @param array<string, string|string[]> $headers
     * @return array{event:array,delivery_biz_id:string,event_type:string,raw_plaintext:string}
     * @throws LampayApiException
     */
    public function handle(array $headers, string $rawBody): array
    {
        $normalized = $this->normalizeHeaders($headers);

        $deliveryId = $normalized['x-slaunchx-delivery-id'] ?? '';
        $eventType = $normalized['x-slaunchx-event-type'] ?? '';
        $timestamp = $normalized['x-slaunchx-timestamp'] ?? '';
        $keyId = $normalized['x-slaunchx-key-id'] ?? '';
        $signature = $normalized['x-slaunchx-signature'] ?? '';

        if ($deliveryId === '' || $eventType === '' || $timestamp === '' || $signature === '') {
            throw new LampayApiException('Missing required webhook headers');
        }

        if ($this->config->webhookReceiverKeyId !== '' && $keyId !== $this->config->webhookReceiverKeyId) {
            throw new LampayApiException('Webhook receiver key id mismatch');
        }

        $this->signer->verifyWebhook($deliveryId, $eventType, $timestamp, $rawBody, $signature);

        $envelope = json_decode($rawBody, true);
        if (!is_array($envelope)) {
            throw new LampayApiException('Invalid webhook JSON body');
        }

        $plaintext = $this->crypto->decryptWebhookEnvelope($envelope, $deliveryId, $timestamp);
        $event = json_decode($plaintext, true, 512, JSON_THROW_ON_ERROR);

        if (($event['deliveryBizId'] ?? null) !== $deliveryId) {
            throw new LampayApiException('Webhook deliveryBizId mismatch');
        }
        if (($event['eventType'] ?? null) !== $eventType) {
            throw new LampayApiException('Webhook eventType mismatch');
        }

        return [
            'event' => $event,
            'delivery_biz_id' => $deliveryId,
            'event_type' => $eventType,
            'raw_plaintext' => $plaintext,
        ];
    }

    /**
     * @param array<string, string|string[]> $headers
     * @return array<string, string>
     */
    protected function normalizeHeaders(array $headers): array
    {
        $out = [];
        foreach ($headers as $key => $value) {
            $name = strtolower((string) $key);
            if (is_array($value)) {
                $value = $value[0] ?? '';
            }
            $out[$name] = (string) $value;
        }

        return $out;
    }
}
