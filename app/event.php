<?php
// 事件定义文件
return [
    'bind' => [
    ],

    'listen' => [
        'AppInit'  => [],
        'HttpRun'  => [],
        'HttpEnd'  => [],
        'LogLevel' => [],
        'LogWrite' => [],
    ],

    'subscribe' => [
        \app\subscribe\SystemSubscribe::class,
        \app\subscribe\GameSubscribe::class,
        \app\subscribe\MemberSubscribe::class,
        \app\subscribe\MerchantSubscribe::class
    ],
];
