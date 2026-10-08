<?php

declare(strict_types=1);

namespace Goletter\Card;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => [
                CardFactory::class => CardFactoryFactory::class,
            ],
            'annotations' => [
                'scan' => [
                    'paths' => [
                        __DIR__,
                    ],
                ],
            ],
            'publish' => [
                [
                    'id' => 'config',
                    'description' => 'The config for card component.',
                    'source' => __DIR__ . '/../publish/card.php',
                    'destination' => BASE_PATH . '/config/autoload/card.php',
                ],
            ],
        ];
    }
}
