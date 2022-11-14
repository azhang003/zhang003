<?php

namespace app\common\model;

use KaadonAdmin\baseCurd\Traits\Model\ModelCurd;

class MerchantShareDay extends TimeModel
{
    use ModelCurd;

    public static $ModelConfig = [
        'modelCache'       => '',
        'modelSchema'      => 'uid',
        'modelDefaultData' => [],
    ];
}