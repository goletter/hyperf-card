<?php

declare(strict_types=1);

namespace Goletter\Card;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Guzzle\ClientFactory;
use Psr\Container\ContainerInterface;

/**
 * Hyperf DI 工厂：注入 config/autoload/card.php，并设为 CardFactory 共享实例。
 */
class CardFactoryFactory
{
    public function __invoke(ContainerInterface $container): CardFactory
    {
        $config = [];
        if ($container->has(ConfigInterface::class)) {
            $config = (array) $container->get(ConfigInterface::class)->get('card', []);
        }

        $clientFactory = $container->has(ClientFactory::class)
            ? $container->get(ClientFactory::class)
            : null;

        $factory = new CardFactory($config, $clientFactory);
        CardFactory::setShared($factory);

        return $factory;
    }
}
