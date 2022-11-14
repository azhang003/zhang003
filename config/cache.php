<?php
return [
    // 默认缓存驱动
    'default' => 'redis',
    // 缓存连接方式配置
    'stores'  => [
        'redis' => [
            'host' => env('redis.hostname','127.0.0.1'),
            'password' => env('redis.password','123456'),
            'select' => env('redis.select',1),
            'port' => env('redis.port',6379),
            'prefix' => env('redis.prefix','cache:') . 'jwt:',
            'timeout'    => 0,
            'expire'     => 3600,
            'persistent' => false,
            'tag_prefix' => '',
        ]
        // 更多的缓存连接
    ],
];
