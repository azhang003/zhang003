<?php

namespace app\job;

use app\common\controller\member\Account;
use app\common\controller\member\QueueError;
use app\common\model\GameEventBet;
use app\common\model\MemberDash;
use app\common\model\MemberDashDay;
use app\common\model\MemberIndex;
use app\common\model\MemberLogin;
use app\common\model\MemberProfile;
use app\common\model\MemberShare;
use app\common\model\MemberShareDay;
use think\facade\Db;
use think\facade\Log;
use think\queue\Job;

class queueMember
{
    public $error = '';
    public $teamType
                  = [
            1 => "one",
            2 => "two",
            3 => "three"
        ];


    public function fire(Job $job, array $data)
    {
        if (!array_key_exists("task", $data)) {
            echo "task 不存在,删除任务" . $job->attempts() . '\n';
            $job->delete();
        }
        if ($this->doJOb($data)) {
            var_dump(111);
            $job->delete();
            echo "删除任务" . $job->attempts() . '\n';
        } else {
            if ($job->attempts() > 2) {
                $job->delete();
                echo "执行失败-->删除任务" . $this->error . '\n';
                QueueError::setError([
                    'title'      => $data['queue'],
                    'controller' => self::class,
                    'context'    => json_encode($data),
                    'remark'     => $this->error,
                ]);
            }
        }
    }


    private function doJOb(array $data)
    {
        try {
            if (array_key_exists('task', $data) && array_key_exists('data', $data) && is_array($data['data'])) {
                $task = $data['task'];
                return $this->$task($data['data']);
            } else {
                return false;
            }
        } catch (\Exception $exception) {
            $this->error = $exception->getMessage();
            var_dump("错误>>:" . $exception->getMessage());
            return false;
        }
    }

    /** 更正佣金 **/

    public function checkIp(array $data)
    {
        try {
            $MemberProfile = MemberProfile::where('mid', $data['mid'])->find();
            $MemberProfile->account->save([
                'login_ip'   => $data['ip'],
                'login_time' => $data['time']
            ]);
            Account::delMemberCache($data['mid']);
            $address         = get_ip_address($data['ip']);
            $login           = [
                'mid' => $data['mid'],
                'ip'  => $data['ip'],
            ];
            $MemberIndexData = [
                'login_ip' => $data['ip'],
            ];
            /**更新IP库**/
            if ($address) {
                $login['address']                 = $address;
                $MemberIndexData['login_address'] = $address;
            }
            $MemberLogin = new MemberLogin();
            $MemberLogin->save($login);
            $MemberIndex = (new MemberIndex())->where('mid', $data['mid'])->update($MemberIndexData);
            var_dump($MemberIndex);
        } catch (\Exception $exception) {
            var_dump($exception->getTraceAsString());
            return false;
        }
        return true;
    }

    public function updateShare(array $dataArr)
    {
        if (!array_key_exists('mid', $dataArr)) {
            var_dump('mid不存在!');
            $this->error = 'mid不存在!';
            return false;
        }
        $mid     = $dataArr['mid'];
        $account = member_account($mid);
        if (empty($account)) {
            var_dump('用户不存在!');
            $this->error = '用户不存在!';
            return false;
        }
        if (!array_key_exists('data', $dataArr) || !is_array($dataArr['data'])) {
            var_dump('数据不存在!');
            $this->error = '数据不存在!';
            return false;
        }
        $data = $dataArr['data'];
        if (!array_key_exists('type', $data)) {
            var_dump('数据格式不存在!');
            $this->error = '数据格式不存在!';
            return false;
        }
        try {
            $MS = MemberShare::where('mid', $mid)->find();
            if (empty($MS)) {
                MemberShare::create([
                    'mid' => $mid
                ]);
                $MS = MemberShare::where('mid', $mid)->find();
            }
            $MSD = MemberShareDay::where([
                ['mid', '=', $mid],
                ['date', '=', date('Y-m-d')]
            ])->find();
            if (empty($MSD)) {
                MemberShareDay::create([
                    "mid"  => $mid,
                    "date" => date('Y-m-d')
                ]);
                $MSD = MemberShareDay::where([
                    ['mid', '=', $mid],
                    ['date', '=', date('Y-m-d')]
                ])->find();
            }
        } catch (\Exception $exception) {
            $this->error = '会员分享表创建失败!' . $exception->getMessage();
            Log::error('来自' . self::class . '会员分享表创建失败!' . $exception->getMessage());
            return false;
        }
        // 启动事务
        Db::startTrans();
        try {
            $MDATA    = [];
            $MSD_DATA = [];
            switch ($data['type']) {
                case "register":
                    $MDATA['people_total']    = Db::raw('people_total+1');
                    $MSD_DATA['people_total'] = Db::raw('people_total+1');
                    if (array_key_exists('team', $data) && array_key_exists($data['team'], $this->teamType)) {
                        $team               = $this->teamType[$data['team']];
                        $MDATA['people']    = Db::raw('people+1');
                        $MDATA[$team]       = Db::raw($team . '+1');
                        $MSD_DATA['people'] = Db::raw('people+1');
                        $MSD_DATA[$team]    = Db::raw($team . '+1');
                    }
                    break;
                case "bet":
                    if (array_key_exists('team', $data) && array_key_exists($data['team'], $this->teamType)) {
                        $teamQuantity = $this->teamType[$data['team']] . '_' . 'quantity';

                        $MDATA[$teamQuantity] = Db::raw($teamQuantity . '+' . $data['money']);
                        $MDATA['bet']         = Db::raw('bet+1');
                        $MDATA['bet_amount']  = Db::raw('bet_amount+' . $data['bet_money']);
                        $MDATA['share']       = Db::raw('share+' . $data['money']);

                        $MSD_DATA[$teamQuantity] = Db::raw($teamQuantity . '+' . $data['money']);
                        $MSD_DATA['bet']         = Db::raw('bet+1');
                        $MSD_DATA['bet_amount']  = Db::raw('bet_amount+' . $data['bet_money']);
                        $MSD_DATA['share']       = Db::raw('share+' . $data['money']);
                    }
                    break;
                case "award":
                    $MDATA['award']           = Db::raw('award+1');
                    $MDATA['award_amount']    = Db::raw('award_amount+' . $data['money']);
                    $MSD_DATA['award']        = Db::raw('award+1');
                    $MSD_DATA['award_amount'] = Db::raw('award_amount+' . $data['money']);
                    break;
                case "recharge":
                    $MDATA['recharge']        = Db::raw('recharge+1');
                    $MDATA['recharge_amount'] = Db::raw('recharge_amount+' . $data['money']);

                    $MSD_DATA['recharge']        = Db::raw('recharge+1');
                    $MSD_DATA['recharge_amount'] = Db::raw('recharge_amount+' . $data['money']);
                    break;
                case "withdraw":
                    $MDATA['withdraw']           = Db::raw('withdraw+1');
                    $MDATA['withdraw_amount']    = Db::raw('withdraw_amount+' . $data['money']);
                    $MSD_DATA['withdraw']        = Db::raw('withdraw+1');
                    $MSD_DATA['withdraw_amount'] = Db::raw('withdraw_amount+' . $data['money']);
                    break;
                case "share":
                    break;
                default:
                    break;
            }
            if (!empty($MDATA)) {
                $MS->save($MDATA);
            }
            if (!empty($MSD_DATA)) {
                $MSD->save($MSD_DATA);
            }
            // 提交事务
            Db::commit();
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            $this->error = $e->getMessage();
            return false;
        }
        return true;
    }

    public function updateDash(array $dataArr)
    {
        try {
            if (!array_key_exists('mid', $dataArr)) {
                var_dump('mid不存在!');
                $this->error = 'mid不存在!';
                return false;
            }
            $mid     = $dataArr['mid'];
            $account = member_account($mid);
            if (empty($account)) {
                var_dump('用户不存在!');
                $this->error = '用户不存在!';
                return false;
            }
            if (!array_key_exists('data', $dataArr) || !is_array($dataArr['data'])) {
                var_dump('数据不存在!');
                return false;
            }
            $data = $dataArr['data'];
            if (!array_key_exists('type', $data)) {
                var_dump('数据格式不存在!');
                $this->error = '数据不存在!';
                return false;
            }
            $MD = MemberDash::where('mid', $mid)->find();
            if (empty($MD)) {
                MemberDash::insert([
                    "mid" => $mid
                ]);
            }
            $MDD = MemberDashDay::where([
                ['mid', '=', $mid],
                ['date', '=', date('Y-m-d')]
            ])->find();
            if (empty($MDD)) {
                MemberDashDay::insert([
                    'mid'=> $mid,
                    'date'=> date('Y-m-d')
                ]);
                $MDD = MemberDashDay::where([
                    ['mid', '=', $mid],
                    ['date', '=', date('Y-m-d')]
                ])->find();
            }
        } catch (\Exception $exception) {
            $this->error = '会员仪表盘创建失败!' . $exception->getMessage();
            Log::error('来自' . self::class . '会员仪表盘创建失败!' . $exception->getMessage());
            return false;
        }
        var_dump(4545);
        $MDATA  = [];
        $MDDATA = [];
        try {
            switch ($data['type']) {
                case "bet":
                    if ($data['real'] == 0) {
                        $MDATA['bet']        = Db::raw('bet+1');
                        $MDATA['bet_amount'] = Db::raw('bet_amount+' . $data['money']);

                        $MDDATA['bet']        = Db::raw('bet+1');
                        $MDDATA['bet_amount'] = Db::raw('bet_amount+' . $data['money']);
                        if ($data['bet_number'] == 1) {
                            $MDATA['bet_day'] = Db::raw('bet_day+1');
                        }
                    } else {
                        if ($data['bet_number'] == 1) {
                            $MDATA['simulation_day'] = Db::raw('simulation_day+' . $data['bet_money']);
                        }
                    }
                    break;
                case "award":
                    $MDATA['award']        = Db::raw('award+1');
                    $MDATA['award_amount'] = Db::raw('award_amount+' . $data['money']);

                    $MDDATA['award']        = Db::raw('award+1');
                    $MDDATA['award_amount'] = Db::raw('award_amount+' . $data['money']);
                    break;
                case "recharge":
                    $MDATA['recharge']        = Db::raw('recharge+1');
                    $MDATA['recharge_amount'] = Db::raw('recharge_amount+' . $data['money']);

                    $MDDATA['recharge']        = Db::raw('recharge+1');
                    $MDDATA['recharge_amount'] = Db::raw('recharge_amount+' . $data['money']);
                    break;
                case "withdraw":
                    $MDATA['withdraw']        = Db::raw('withdraw+1');
                    $MDATA['withdraw_free']   = Db::raw('withdraw_free+' . $data['free']);
                    $MDATA['withdraw_amount'] = Db::raw('withdraw_amount+' . $data['money']);

                    $MDDATA['withdraw']        = Db::raw('withdraw+1');
                    $MDDATA['withdraw_free']   = Db::raw('withdraw_free+' . $data['free']);
                    $MDDATA['withdraw_amount'] = Db::raw('withdraw_amount+' . $data['money']);
                    break;
                case "transfer_into":
                    $MDATA['transfer_into']         = Db::raw("transfer_into+1");
                    $MDATA['transfer_into_amount']  = Db::raw("transfer_into_amount+" . $data['money']);
                    $MDDATA['transfer_into']        = Db::raw("transfer_into+1");
                    $MDDATA['transfer_into_amount'] = Db::raw("transfer_into_amount+" . $data['money']);

                    break;
                case "transfer_out":
                    $MDATA['transfer_out']         = Db::raw("transfer_out+1");
                    $MDATA['transfer_out_amount']  = Db::raw("transfer_out_amount+" . $data['money']);
                    $MDDATA['transfer_out']        = Db::raw("transfer_out+1");
                    $MDDATA['transfer_out_amount'] = Db::raw("transfer_out_amount+" . $data['money']);
                    break;
                case "merchant_transfer_into":
                    $MDATA['merchant_transfer_into']         = Db::raw("merchant_transfer_into+1");
                    $MDDATA['merchant_transfer_into']        = Db::raw("merchant_transfer_into+1");
                    $MDATA['merchant_transfer_into_amount']  = Db::raw("merchant_transfer_into_amount+" . $data['money']);
                    $MDDATA['merchant_transfer_into_amount'] = Db::raw("merchant_transfer_into_amount+" . $data['money']);
                    break;
                case "internal_recharge":
                    $MDATA['internal_recharge']         = Db::raw("internal_recharge+1");
                    $MDDATA['internal_recharge']        = Db::raw("internal_recharge+1");
                    $MDATA['internal_recharge_amount']  = Db::raw("internal_recharge_amount+" . $data['money']);
                    $MDDATA['internal_recharge_amount'] = Db::raw("internal_recharge_amount+" . $data['money']);
                    break;
                default:
                    break;
            }
        } catch (\Exception $exception) {
            $this->error = $exception->getMessage();
            return false;
        }
        var_dump(2312423);
        var_dump($MDATA);
        var_dump($MDDATA);

        // 启动事务
        if (!empty($MDATA) || !empty($MDDATA)) {
            Db::startTrans();
            try {
                if (!empty($MDATA)) {
                    $MD->save($MDATA);
                }
                if ($MDDATA) {
                    $MDD->save($MDDATA);
                }
                // 提交事务
                Db::commit();
            } catch (\Exception $e) {
                // 回滚事务
                Db::rollback();
                $this->error = $e->getMessage();
                return false;
            }
        }
        return true;
    }

    private function isBetDay($mid, $time, $type)
    {
        $betDay = MemberDashDay::where('mid', $mid)->find();
        if (empty($betDay) || empty($betDay->bet)) {
            $bet = GameEventBet::where([
                ['mid', '=', $mid],
                ['type', '=', $type],
                ['create_time', '<', $time],
            ])->count();
            if (empty($bet)) {
                return true;
            } else {
                return false;
            }
        }
        return true;
    }

}