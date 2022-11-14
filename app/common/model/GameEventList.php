<?php

namespace app\common\model;

use KaadonAdmin\baseCurd\Traits\Model\ModelCurd;

class GameEventList extends TimeModel
{
    use ModelCurd;



    public static $ModelConfig = [
        'modelCache'       => '',
        'modelSchema'      => 'id',
        'modelDefaultData' => [],
    ];

    // 设置当前模型的数据库连接

    public function gameBet()
    {
        return $this->belongsTo(GameEventBet::class, 'id', 'list_id');
    }

    public function gameCurrery($cid)
    {
        return GameEventCurrency::CurreryAll()[$cid];
    }

}