<?php

declare(strict_types=1);

namespace Goletter\Card;

use Goletter\Card\Platforms\Airwallex\Airwallex;
use Goletter\Card\Platforms\Lampay\Lampay;
use Goletter\Card\Platforms\Photonpay\Photonpay;
use Goletter\Card\Platforms\Slash\Slash;
use Goletter\Card\Platforms\Wasabi\Wasabi;
use InvalidArgumentException;

/**
 * 多平台卡片 SDK 工厂（对齐 hyperf-adv）。
 *
 * 静态用法：
 *   $bundle = CardFactory::make('airwallex', ['api_key' => '...', 'client_id' => '...']);
 *   $bundle->card()->cardList([...]);
 *
 * Hyperf DI：
 *   $factory = $container->get(CardFactory::class);
 *   $bundle = $factory->create('slash', $overrides);
 */
class CardFactory
{
    public const AIRWALLEX = 'airwallex';
    public const SLASH = 'slash';
    public const LAMPAY = 'lampay';
    public const PHOTONPAY = 'photonpay';
    public const WASABI = 'wasabi';

    /** @var array<int|string, string> */
    protected static array $defaultAliases = [
        0 => self::AIRWALLEX,
        1 => self::SLASH,
        2 => self::LAMPAY,
        3 => self::PHOTONPAY,
        4 => self::WASABI,
    ];

    /**
     * @var array<string, callable(array): CardPlatformBundle>
     */
    protected static array $creators = [];

    protected static bool $defaultsBooted = false;

    protected static ?self $shared = null;

    /** @var array<string, mixed> */
    protected array $config;

    /** @var array<int|string, string> */
    protected array $aliases;

    /**
     * @param array<string, mixed> $config publish/card.php 结构
     */
    public function __construct(array $config = [])
    {
        self::bootDefaults();
        $this->config = $config;
        $this->aliases = self::$defaultAliases;
        foreach ($config['platforms'] ?? [] as $key => $name) {
            $this->aliases[$key] = (string) $name;
        }
    }

    public static function setShared(self $factory): void
    {
        self::$shared = $factory;
    }

    public static function getShared(): self
    {
        return self::$shared ??= new self();
    }

    /**
     * @param callable(array): CardPlatformBundle $creator
     */
    public static function register(string $platform, callable $creator): void
    {
        self::bootDefaults();
        self::$creators[strtolower(trim($platform))] = $creator;
    }

    public static function has(string $platform): bool
    {
        self::bootDefaults();

        return isset(self::$creators[strtolower(trim($platform))]);
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function make(string|int $platform, array $config = []): CardPlatformBundle
    {
        return self::getShared()->create($platform, $config);
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function airwallex(array $config = []): CardPlatformBundle
    {
        return self::make(self::AIRWALLEX, $config);
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function slash(array $config = []): CardPlatformBundle
    {
        return self::make(self::SLASH, $config);
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function lampay(array $config = []): CardPlatformBundle
    {
        return self::make(self::LAMPAY, $config);
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function photonpay(array $config = []): CardPlatformBundle
    {
        return self::make(self::PHOTONPAY, $config);
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function wasabi(array $config = []): CardPlatformBundle
    {
        return self::make(self::WASABI, $config);
    }

    /**
     * @param array<string, mixed> $config
     */
    public function create(string|int $platform, array $config = []): CardPlatformBundle
    {
        $name = $this->resolvePlatform($platform);
        $creator = self::$creators[$name] ?? null;
        if ($creator === null) {
            throw new InvalidArgumentException("Unsupported card platform: {$platform}");
        }

        $merged = array_replace_recursive($this->platformOptions($name), $config);

        return $creator($merged);
    }

    public static function normalizePlatform(string|int $platform): string
    {
        return self::getShared()->resolvePlatform($platform);
    }

    public function resolvePlatform(string|int $platform): string
    {
        if (is_int($platform) || ctype_digit((string) $platform)) {
            $key = (int) $platform;
            if (! isset($this->aliases[$key])) {
                throw new InvalidArgumentException("Unknown card platform id: {$platform}");
            }

            return strtolower((string) $this->aliases[$key]);
        }

        $name = strtolower(trim((string) $platform));
        if (isset($this->aliases[$name])) {
            return strtolower((string) $this->aliases[$name]);
        }

        if (isset(self::$creators[$name])) {
            return $name;
        }

        throw new InvalidArgumentException("Unknown card platform: {$platform}");
    }

    /**
     * @return array<string, mixed>
     */
    protected function platformOptions(string $platform): array
    {
        $options = $this->config[$platform] ?? [];

        return is_array($options) ? $options : [];
    }

    protected static function bootDefaults(): void
    {
        if (self::$defaultsBooted) {
            return;
        }
        self::$defaultsBooted = true;

        self::$creators[self::AIRWALLEX] = static fn (array $config): CardPlatformBundle => self::buildAirwallex($config);
        self::$creators[self::SLASH] = static fn (array $config): CardPlatformBundle => self::buildSlash($config);
        self::$creators[self::LAMPAY] = static fn (array $config): CardPlatformBundle => self::buildLampay($config);
        self::$creators[self::PHOTONPAY] = static fn (array $config): CardPlatformBundle => self::buildPhotonpay($config);
        self::$creators[self::WASABI] = static fn (array $config): CardPlatformBundle => self::buildWasabi($config);
    }

    /**
     * @param array<string, mixed> $config
     */
    protected static function buildAirwallex(array $config): CardPlatformBundle
    {
        $platform = new Airwallex($config);

        return new CardPlatformBundle($platform, $platform->getClient(), self::AIRWALLEX);
    }

    /**
     * @param array<string, mixed> $config
     */
    protected static function buildSlash(array $config): CardPlatformBundle
    {
        $platform = new Slash($config);

        return new CardPlatformBundle($platform, $platform->getClient(), self::SLASH);
    }

    /**
     * @param array<string, mixed> $config
     */
    protected static function buildLampay(array $config): CardPlatformBundle
    {
        $platform = new Lampay($config);

        return new CardPlatformBundle($platform, $platform->getClient(), self::LAMPAY);
    }

    /**
     * @param array<string, mixed> $config
     */
    protected static function buildPhotonpay(array $config): CardPlatformBundle
    {
        $platform = new Photonpay($config);

        return new CardPlatformBundle($platform, $platform->getClient(), self::PHOTONPAY);
    }

    /**
     * @param array<string, mixed> $config
     */
    protected static function buildWasabi(array $config): CardPlatformBundle
    {
        $platform = new Wasabi($config);

        return new CardPlatformBundle($platform, $platform->getClient(), self::WASABI);
    }
}
