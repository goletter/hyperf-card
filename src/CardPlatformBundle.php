<?php

declare(strict_types=1);

namespace Goletter\Card;

use Goletter\Card\Contract\PlatformClientInterface;
use Goletter\Card\Platforms\Airwallex\Adapter\CardAdapter as AirwallexAdapter;
use Goletter\Card\Platforms\Airwallex\Airwallex;
use Goletter\Card\Platforms\Airwallex\Api\AuthenticationApi as AirwallexAuth;
use Goletter\Card\Platforms\Airwallex\Api\CardApi as AirwallexCardApi;
use Goletter\Card\Platforms\Lampay\Lampay;
use Goletter\Card\Platforms\Lampay\WebhookHandler;
use Goletter\Card\Platforms\Photonpay\Adapter\CardAdapter as PhotonpayAdapter;
use Goletter\Card\Platforms\Photonpay\Api\AuthenticationApi as PhotonpayAuth;
use Goletter\Card\Platforms\Photonpay\Api\CardApi as PhotonpayCardApi;
use Goletter\Card\Platforms\Photonpay\Photonpay;
use Goletter\Card\Platforms\Slash\Adapter\CardAdapter as SlashAdapter;
use Goletter\Card\Platforms\Slash\Api\CardApi as SlashCardApi;
use Goletter\Card\Platforms\Slash\Slash;
use Goletter\Card\Platforms\Wasabi\Adapter\CardAdapter as WasabiAdapter;
use Goletter\Card\Platforms\Wasabi\Api\CardApi as WasabiCardApi;
use Goletter\Card\Platforms\Wasabi\Wasabi;
use Goletter\Card\Platforms\Youcardx\Api\AccountApi as YoucardxAccountApi;
use Goletter\Card\Platforms\Youcardx\Api\BudgetApi as YoucardxBudgetApi;
use Goletter\Card\Platforms\Youcardx\Api\CardApi as YoucardxCardApi;
use Goletter\Card\Platforms\Youcardx\Api\CardholderApi as YoucardxCardholderApi;
use Goletter\Card\Platforms\Youcardx\Api\FileApi as YoucardxFileApi;
use Goletter\Card\Platforms\Youcardx\Webhook as YoucardxWebhook;
use Goletter\Card\Platforms\Youcardx\Youcardx;
use LogicException;

/**
 * 统一平台入口。
 *
 * - card() / api() → 薄 SDK（官方路径，抛异常）
 * - adapter() → 统一 Result 适配层（{code,msg,data}），Lampay / Youcardx 为 null
 *
 * @template TPlatform of Airwallex|Slash|Lampay|Photonpay|Wasabi|Youcardx
 * @template TClient of PlatformClientInterface
 *
 * @property-read TPlatform $platform
 * @property-read TClient $client
 */
final class CardPlatformBundle
{
    /**
     * @param TPlatform $platform
     * @param TClient $client
     */
    public function __construct(
        public readonly Airwallex|Slash|Lampay|Photonpay|Wasabi|Youcardx $platform,
        public readonly PlatformClientInterface $client,
        public readonly string $name,
    ) {
    }

    /**
     * Thin vendor SDK.
     *
     * @return AirwallexCardApi|SlashCardApi|PhotonpayCardApi|WasabiCardApi|YoucardxCardApi|\Goletter\Card\Platforms\Lampay\Api\CardApi
     */
    public function card(): object
    {
        return $this->platform->card();
    }

    /**
     * Alias of {@see card()}.
     */
    public function api(): object
    {
        return $this->platform->api();
    }

    /**
     * Unified Result adapter, or null when the platform has none (Lampay / Youcardx).
     *
     * @return AirwallexAdapter|SlashAdapter|PhotonpayAdapter|WasabiAdapter|null
     */
    public function adapter(): ?object
    {
        return $this->platform->adapter();
    }

    public function authentication(): AirwallexAuth|PhotonpayAuth|null
    {
        if ($this->platform instanceof Airwallex || $this->platform instanceof Photonpay) {
            return $this->platform->authentication();
        }

        return null;
    }

    public function account(): ?YoucardxAccountApi
    {
        if ($this->platform instanceof Youcardx) {
            return $this->platform->account();
        }

        return null;
    }

    public function budget(): ?YoucardxBudgetApi
    {
        if ($this->platform instanceof Youcardx) {
            return $this->platform->budget();
        }

        return null;
    }

    public function cardholder(): ?YoucardxCardholderApi
    {
        if ($this->platform instanceof Youcardx) {
            return $this->platform->cardholder();
        }

        return null;
    }

    public function file(): ?YoucardxFileApi
    {
        if ($this->platform instanceof Youcardx) {
            return $this->platform->file();
        }

        return null;
    }

    public function webhook(): WebhookHandler|YoucardxWebhook|null
    {
        if ($this->platform instanceof Lampay || $this->platform instanceof Youcardx) {
            return $this->platform->webhook();
        }

        return null;
    }

    public function setAccessToken(string $token, ?int $expiresAt = null): void
    {
        if ($this->platform instanceof Airwallex || $this->platform instanceof Photonpay || $this->platform instanceof Youcardx) {
            $this->platform->setAccessToken($token, $expiresAt);

            return;
        }

        throw new LogicException("Platform [{$this->name}] does not support access tokens.");
    }

    public function is(string $platform): bool
    {
        return strtolower($platform) === strtolower($this->name);
    }
}
