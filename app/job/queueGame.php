<?php

namespace app\job;

use app\common\controller\member\QueueError;
use app\common\controller\member\Wallet;
use app\common\model\MemberTeam;
use app\common\model\MemberWallet;
use think\Exception;
use think\facade\Db;
use think\queue\Job;

class queueGame
{
    public $error = '';
    public function fire(Job $job, array $data)
    {
        if (!array_key_exists("task", $data)) {
            echo "task 不存在,删除任务" . $job->attempts() . '\n';
            $job->delete();
        }
        if ($this->doJOb($data)) {
            $job->delete();
            echo "删除任务" . $job->attempts() . '\n';
        } else {
            if ($job->attempts() > 2) {
                $job->delete();
                echo "执行失败-->删除任务" . $this->error . '\n';
                QueueError::setError([
                    'title'      => $data['task'],
                    'controller' => self::class,
                    'context'    => json_encode($data),
                    'remark'      => $this->error,
                ]);
            }
        }
    }

    private function doJOb(array $data)
    {
        var_dump(json_encode($data));
        try {
            if (array_key_exists('task', $data) && array_key_exists('data', $data) && is_array($data['data'])) {
                $task = $data['task'];
                $bool = $this->$task($data['data']);
                return $bool;
            } else {
                return false;
            }
        } catch (\Exception $exception) {
            $this->error = $exception->getMessage();
            return false;
        }
    }

    /**
     * 投注分红
     * @param array $data
     * @return void
     */
    private  function pushBonus(array $data)
    {
        if (array_key_exists('mid', $data)) {
            $mid = $data['mid'];
        } else {
            $this->error = 'money 不存在!';
            var_dump($this->error);
            return false;
        }
        if (array_key_exists('money', $data)) {
            $mooney = $data['money'];
        } else {
            $this->error = 'money 不存在!';
            var_dump($this->error);
            return false;
        }
        if (array_key_exists('inviter', $data)) {
            $inviter = $data['inviter'];
        } else {
            $this->error = 'inviter 不存在!';
            var_dump($this->error);
            return false;
        }
        $share   = explode("|", get_config('site', 'setting', 'share'));
        $super   = get_config('site', 'setting', 'super');
        if (empty($inviter)) {
            var_dump("没有邀请人!");
            return true;
        }
        $cdata = [];
        foreach ($inviter as $key => $item) {
            if ($key < 3) {
                $teammoney    = $share[$key] * $mooney;
                $cdata[$item] = [
                    'money' => money_format_bet($teammoney),
                    'team'  => $key + 1,
                ];
            } else {
                $account = member_account($item);
                if (!empty($account) && $account['is_super']) {
                    $teammoney    = $super * $mooney;
                    $cdata[$item] = [
                        'money' => money_format_bet($teammoney),
                        'team'  => 0,
                    ];
                }
            }
        }
        // 启动事务
        Db::startTrans();
        try {
            var_dump("");
            //订阅MemberBetShare事件Data
            $MemberBetSharedatas = [];
            foreach ($cdata as $key => $cdatum) {
                $MW = MemberWallet::where('mid', $key)->find();
                if (empty($MW)) {
                    continue;
                }
                $MemberTeam = MemberTeam::where([['mid', '=', $key]]);
                $MemberTeamData = [];
                switch ($cdatum['team']) {
                    case 1:
                        $MemberTeamData['first_share'] = Db::raw("first_share+" . $cdatum['money']);
                        break;
                    case 2:
                        $MemberTeamData['second_share'] = Db::raw("second_share+" . $cdatum['money']);
                        break;
                    case 3:
                        $MemberTeamData['third_share'] = Db::raw("third_share+" . $cdatum['money']);
                        break;
                    case 0:
                        $MemberTeamData['vip'] = Db::raw("vip+" . $cdatum['money']);
                        break;
                }
                $MemberTeamData['all_share'] = Db::raw("all_share+" . $cdatum['money']);
                $bool = $MemberTeam->update($MemberTeamData);
                if(empty($bool)){
                    throw new Exception('佣金更新失败!');
                }
                (new Wallet())->change($key, 9, [
                    4 => [$MW->eth, $cdatum['money'], $MW->eth + $cdatum['money']],
                ], $mid, $cdatum['team']);
                if ($cdatum['team'] != 0) {
                    $MemberBetSharedatas[] = [
                        'mid'  => $key,
                        'data' => [
                            'type'      => 'bet',
                            'team'      => $cdatum['team'],
                            'money'     => $cdatum['money'],
                        ]
                    ];
                }
            }
            // 提交事务
            Db::commit();
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            $this->error = $e->getMessage();
            throw new Exception('执行失败!' . $e->getMessage());
            return false;
        }
        //订阅MemberBetShare事件Data
        event('MemberBetShare',[
            'mid' => $mid,
            'money'=> $mooney,
            'data' => $MemberBetSharedatas
        ]);
        return true;
    }
}