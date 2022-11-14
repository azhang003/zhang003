<?php
declare (strict_types=1);

namespace app\subscribe;

use app\job\queueGame;
use think\facade\Log;

class GameSubscribe
{
    public function onEventListOpen($list)
    {
        Log::info("time<" . date('Y-m-d') . ">来自<GameSubscribe>:<onEventListOpen>" . json_encode($list));
    }

    public function onPushBet($data)
    {

    }

    public function onPushAward($data)
    {
        $taskQueue = [
            'task' => 'pushBet',
            'data' => [
                "type" =>  'award',
                "agent_line" => agent_line($data['mid']),
                "mid"        => $data['mid'],
                "money"      => $data['money'],
            ]
        ];
        queue(queueGame::class,$taskQueue,0,'pushBet');
        Log::info("time<" . date('Y-m-d') . ">来自<GameSubscribe>:<onPushAward>" . json_encode($data));

    }

}
