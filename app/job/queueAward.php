<?php

namespace app\job;

use app\common\controller\member\QueueError;
use app\common\model\GameEventBet;
use think\facade\Log;
use think\queue\Job;

class queueAward
{
    public $error = '';
    /**
     * @param Job $job
     * @param $data
     * @return void
     */
    public function fire(Job $job, $data)
    {
        $job->delete();
        if ($this->doHelloJob($data['data'])) {
            echo "执行成功删除任务" . $job->attempts() . '\n';
        } else {
            $job->delete();
            echo "执行失败-->删除任务" . $this->error . '\n';
            QueueError::setError([
                'title'      => $data['task'],
                'controller' => self::class,
                'context'    => json_encode($data),
                'remark'      => $this->error,
            ]);
            echo "执行失败删除任务" . $job->attempts() . '\n';
        }
    }

    /**
     * @param array $data
     * @return bool|void
     */
    private function doHelloJob(array $data)
    {
        var_dump(json_encode($data));
        /**  当期未开奖 **/
        GameEventBet::where([['list_id', '=', $data['id']], ['is_ok', '=', 0], ['bet', '<>', $data['open']]])->save([
            'is_ok'     => 2,
            'open_time' => time(),
            'remark'    => $data['remark'],
        ]);
        try {
            $GameEventBetItems = GameEventBet::where([['list_id', '=', $data['id']], ['is_ok', '=', 0]])->order('id asc')->select();
            if ($GameEventBetItems->isEmpty()){
                var_dump('没有会员中奖' . $GameEventBetItems->isEmpty());
                return true;
            }
            foreach ($GameEventBetItems as $key => $gameEventBetItem) {
                var_dump($gameEventBetItem->bet == $data['open']);
                if ($gameEventBetItem->bet == $data['open']) {
                    $queueData = [
                        'task' => 'queueAwardMember', //标识 暂时不使用
                        'data' => [
                            'mid'       => $gameEventBetItem->mid,//会员ID
                            'bet_id'    => $gameEventBetItem->id,//投注ID,
                            'open'      => $data['open'],//当期开奖,
                            'cycle'      => $gameEventBetItem->cycle,//周期,
                            'bet'       => $gameEventBetItem->bet,//当期投注涨跌,
                            'remark'    => $data['remark'],//当期开奖价格,
                            'type'      => $gameEventBetItem->type,//当期真是投注还是模拟投注,
                            'odds'      => $gameEventBetItem->odds,//当期赔率,
                            'money'     => $gameEventBetItem->money,
                            'time'     => $gameEventBetItem->create_time,
                        ]
                    ];
                    var_dump("推送开始:>>" . $key . ">>" . json_encode($queueData));
                    queue(queueAwardMember::class,$queueData,0,$queueData['task']);
                }
            }
        } catch (\Exception $exception) {
            $this->error = $exception->getMessage();
            Log::info("queueAward::>>time<" . date('Y-m-d H:i:s') . $exception->getMessage());
            return false;
        }
        return true;
    }
}