<?php

namespace app\common\model;

use KaadonAdmin\baseCurd\Traits\Model\ModelCurd;

class MerchantDashDay extends TimeModel
{
    use ModelCurd;

    public static $ModelConfig = [
        'modelCache'       => '',
        'modelSchema'      => 'id',
        'modelDefaultData' => [],
    ];

    public function account()
    {
        return $this->belongsTo(MerchantAccount::class, 'uid', 'id');
    }
}