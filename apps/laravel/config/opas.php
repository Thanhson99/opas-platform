<?php

return [
    'brand' => [
        'name' => 'OPAS',
        'tagline' => 'operations platform',
        'mark' => 'OP',
    ],

    'navigation' => [
        [
            'label' => 'Workspace',
            'items' => [
                [
                    'label' => 'Dashboard',
                    'route' => 'dashboard',
                    'icon' => 'fas fa-compass',
                ],
            ],
        ],
        [
            'label' => 'Coin Manager',
            'items' => [
                [
                    'label' => 'Coins',
                    'route' => 'coins.index',
                    'icon' => 'fas fa-coins',
                ],
                [
                    'label' => 'Price Alerts',
                    'route' => 'coins.price-alert-settings.index',
                    'icon' => 'fas fa-bell',
                ],
                [
                    'label' => 'Content Keywords',
                    'route' => 'coins.feed-keywords.index',
                    'icon' => 'fas fa-tags',
                ],
            ],
        ],
        [
            'label' => 'Stock Manager',
            'items' => [
                [
                    'label' => 'Stocks',
                    'route' => 'stocks.index',
                    'icon' => 'fas fa-chart-line',
                ],
            ],
        ],
        [
            'label' => 'Video Automation',
            'items' => [
                [
                    'label' => 'Trending Videos',
                    'route' => 'video-automation.trending.index',
                    'icon' => 'fas fa-video',
                ],
            ],
        ],
    ],
];
