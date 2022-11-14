<?php

namespace app\job;

use app\common\controller\member\QueueError;
use app\common\model\MerchantDash;
use app\common\model\MerchantDashDay;
use think\facade\Db;
use think\facade\Log;
use think\queue\Job;

class queueMerchant
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
            return false;
        }
    }


    private function updateDash(array $dataArr)
    {
        if (!array_key_exists('agent_line', $dataArr) && !is_array($dataArr['agent_line'])) {
            $this->error = '代理不存在!';
            return false;
        }
        try {
            $MerchantDashTable    = [];
            $MerchantDashDayTable = [];
            foreach ($dataArr['agent_line'] as $item) {
                $MD = MerchantDash::where('uid', $item)->find();
                if (empty($MD)) {
                    $MerchantDashTable[] = [
                        'uid' => $item
                    ];
                }
                $MDD = MerchantDashDay::where([
                    ['uid', '=', $item],
                    ['date', '=', date('Y-m-d')]
                ])->find();
                if (empty($MDD)) {
                    $MerchantDashDayTable[] = [
                        'uid'  => $item,
                        'date' => date('Y-m-d')
                    ];
                }
            }
            if (!empty($MerchantDashTable)) {
                (new MerchantDash)->saveAll($MerchantDashTable);
            }
            if (empty($MerchantDashDayTable)) {
                (new MerchantDashDay)->saveAll($MerchantDashDayTable);
            }
        } catch (\Exception $exception) {
            $this->error = '代理表创建失败!' . $exception->getMessage();
            Log::error('来自' . self::class . '代理表创建失败!' . $exception->getMessage());
            return false;
        }

        if (!array_key_exists('data', $dataArr) || !is_array($dataArr['data'])) {
            $this->error = '数据不存在!';
            return false;
        }
        $data = $dataArr['data'];
        if (!array_key_exists('type', $data)) {
            $this->error = '数据格式不存在!';
            return false;
        }
        try {
            $updateDashQuery        = MerchantDash::whereIn('uid', $dataArr['agent_line']);
            $updateDashDayQuery     = MerchantDashDay::where([
                ['uid', 'in', $dataArr['agent_line']],
                ['date', '=', date('Y-m-d')]
            ]);
            $updateDashQueryData    = [];
            $updateDashDayQueryData = [];
            switch ($data['type']) {
                case "athen":
                    $updateDashDayQueryData['people_valid'] = Db::raw("people_valid+1");
                    $updateDashQueryData['people_valid']    = Db::raw("people_valid+1");
                    break;
                case "share":
                    $updateDashDayQueryData['share'] = Db::raw("share+1");
                    $updateDashQueryData['share']    = Db::raw("share+1");
                    break;
                case "register":
                    $updateDashDayQueryData['people'] = Db::raw("people+1");
                    $updateDashQueryData['people']    = Db::raw("people+1");
                    break;
                case "bet":
                    if (!array_key_exists('mid', $dataArr)) {
                        var_dump('用户不存在!');
                        return false;
                    }
                    $updateDashDayQueryData['bet'] = Db::raw('bet+1');
                    $updateDashQueryData['bet']    = Db::raw('bet+1');

                    $updateDashDayQueryData['bet_amount'] = Db::raw('bet_amount+' . $data['money']);
                    $updateDashQueryData['bet_amount']    = Db::raw('bet_amount+' . $data['money']);

                    if ($data['bet_number'] == 1) {//判断条件  //time
                        $updateDashDayQueryData['bet_people'] = Db::raw("bet_people+1");
                        $updateDashDayQueryData['day_valid']  = Db::raw("day_valid+1");
                    }
                    /** 1/3/5 投注5次以上 **/
                    if ($data['bet_number'] == 5) {//判断条件  //
                        $updateDashDayQueryData['bet_people_five'] = Db::raw("bet_people_five+1");
                    }
                    /** 1/3/5 投注金额 **/
                    switch ($data['cycle']) {
                        case "1m":
                            $updateDashDayQueryData['bet_amount_one'] = Db::raw("bet_amount_one+" . $data['money']);
                            $updateDashQueryData['bet_amount_one']    = Db::raw("bet_amount_one+" . $data['money']);
                            break;
                        case "3m":
                            $updateDashDayQueryData['bet_amount_three'] = Db::raw("bet_amount_three+" . $data['money']);
                            $updateDashQueryData['bet_amount_three']    = Db::raw("bet_amount_three+" . $data['money']);
                            break;
                        case "5m":
                            $updateDashDayQueryData['bet_amount_five'] = Db::raw("bet_amount_five+" . $data['money']);
                            $updateDashQueryData['bet_amount_five']    = Db::raw("bet_amount_five+" . $data['money']);
                            break;
                        default:
                            break;
                    }
                    break;
                case "award":
                    $updateDashDayQueryData['award']        = Db::raw('award+1');
                    $updateDashQueryData['award']           = Db::raw('award+1');
                    $updateDashDayQueryData['award_amount'] = Db::raw('award_amount+' . $data['money']);
                    $updateDashQueryData['award_amount']    = Db::raw('award_amount+' . $data['money']);
                    /** 1/3/5 投注金额 **/
                    switch ($data['cycle']) {
                        case "1m":
                            $updateDashDayQueryData['award_amount_one'] = Db::raw("award_amount_one+" . $data['money']);
                            $updateDashQueryData['award_amount_one']    = Db::raw("award_amount_one+" . $data['money']);
                            break;
                        case "3m":
                            $updateDashDayQueryData['award_amount_three'] = Db::raw("award_amount_three+" . $data['money']);
                            $updateDashQueryData['award_amount_three']    = Db::raw("award_amount_three+" . $data['money']);
                            break;
                        case "5m":
                            $updateDashDayQueryData['award_amount_five'] = Db::raw("award_amount_five+" . $data['money']);
                            $updateDashQueryData['award_amount_five']    = Db::raw("award_amount_five+" . $data['money']);
                            break;
                        default:
                            break;
                    }
                    break;
                case "recharge":
                    if ($data['recharge_num'] == 1) {//判断条件
                        $updateDashDayQueryData['recharge']       = Db::raw("recharge+1");
                        $updateDashDayQueryData['recharge_ratio'] = Db::raw("recharge/people_valid");
                    }
                    $updateDashQueryData['recharge'] = Db::raw("recharge+1");

                    if ($data['recharge_first']) {//判断条件
                        $updateDashDayQueryData['recharge_first']        = Db::raw("recharge_first+1");
                        $updateDashDayQueryData['recharge_first_amount'] = Db::raw("recharge_first_amount+" . $data['money']);
                    }

                    if ($data['today'] && $data['recharge_num'] == 1) {
                        $updateDashDayQueryData['recharge_first_people'] = Db::raw("recharge_first_people+1");
                        $updateDashDayQueryData['recharge_first_ratio']  = Db::raw("recharge_first_people/people");
                    }
                    $updateDashDayQueryData['recharge_amount'] = Db::raw("recharge_amount+" . $data['money']);
                    $updateDashQueryData['recharge_amount']    = Db::raw("recharge_amount+" . $data['money']);
                    break;
                case "withdraw":
                    if ($data['withdraw_num'] == 1) {//判断条件
                        $updateDashDayQueryData['withdraw'] = Db::raw("withdraw+1");
                    }
                    $updateDashQueryData['withdraw']           = Db::raw("withdraw+1");
                    $updateDashDayQueryData['withdraw_amount'] = Db::raw("withdraw_amount+" . $data['money']);
                    $updateDashDayQueryData['withdraw_free']   = Db::raw("withdraw_free+" . $data['free']);

                    $updateDashQueryData['withdraw_amount'] = Db::raw("withdraw_amount+" . $data['money']);
                    $updateDashQueryData['withdraw_free']   = Db::raw("withdraw_free+" . $data['free']);
                    break;
                case "login":
                    $keep = 'moon';
                    if (true) {
                        $updateDashDayQueryData['keep_' . $keep] = Db::raw('keep_' . $keep . "+ 1");
                    }
                    break;
                case "transfer_into":
                    $updateDashDayQueryData['transfer_into']        = Db::raw("transfer_into+1");
                    $updateDashQueryData['transfer_into']           = Db::raw("transfer_into+1");
                    $updateDashDayQueryData['transfer_into_amount'] = Db::raw("transfer_into_amount+" . $data['money']);
                    $updateDashQueryData['transfer_into_amount']    = Db::raw("transfer_into_amount+" . $data['money']);
                    break;
                case "transfer_out":
                    $updateDashDayQueryData['transfer_out']        = Db::raw("transfer_out+1");
                    $updateDashQueryData['transfer_out']           = Db::raw("transfer_out+1");
                    $updateDashDayQueryData['transfer_out_amount'] = Db::raw("transfer_out_amount+" . $data['money']);
                    $updateDashQueryData['transfer_out_amount']    = Db::raw("transfer_out_amount+" . $data['money']);
                    break;
                case "merchant_transfer_out":
                    $updateDashDayQueryData['merchant_transfer_out']        = Db::raw("merchant_transfer_out+1");
                    $updateDashQueryData['merchant_transfer_out']           = Db::raw("merchant_transfer_out+1");
                    $updateDashDayQueryData['merchant_transfer_out_amount'] = Db::raw("merchant_transfer_out_amount+" . $data['money']);
                    $updateDashQueryData['merchant_transfer_out_amount']    = Db::raw("merchant_transfer_out_amount+" . $data['money']);
                    break;
                case "merchant_transfer_into":
                    if (true) {//判断条件
                        $updateDashDayQueryData['merchant_transfer_into'] = Db::raw("merchant_transfer_into+1");
                    }
                    if (true) {//判断条件
                        $updateDashQueryData['merchant_transfer_into'] = Db::raw("merchant_transfer_into+1");
                    }
                    $updateDashDayQueryData['merchant_transfer_into_amount'] = Db::raw("merchant_transfer_into_amount+" . $data['money']);
                    $updateDashQueryData['merchant_transfer_into_amount']    = Db::raw("merchant_transfer_into_amount+" . $data['money']);
                    break;
                case "shareAmount":
                    $updateDashQueryData['share_amount'] = Db::raw("share_amount+" . $data['money']);
                    break;
                case "receiveAmount":
                    $updateDashQueryData['receive_amount'] = Db::raw("receive_amount+" . $data['money']);
                    break;
                case "internal_recharge":
                    $updateDashDayQueryData['internal_recharge']        = Db::raw("internal_recharge+1");
                    $updateDashQueryData['internal_recharge']           = Db::raw("internal_recharge+1");
                    $updateDashDayQueryData['internal_recharge_amount'] = Db::raw("internal_recharge_amount+" . $data['money']);
                    $updateDashQueryData['internal_recharge_amount']    = Db::raw("internal_recharge_amount+" . $data['money']);
                    break;
                default:
                    break;
            }
        } catch (\Exception $exception) {
            $this->error = '数据整理失败!';
            return false;
        }
        if (!empty($updateDashDayQueryData) || !empty($updateDashQueryData)) {
            // 启动事务
            Db::startTrans();
            try {
                if (!empty($updateDashDayQueryData)) {
                    $updateDashDayQuery->update($updateDashDayQueryData);
                }
                if (!empty($updateDashQueryData)) {
                    $updateDashQuery->update($updateDashQueryData);
                }
                // 提交事务
                Db::commit();
            } catch (\Exception $e) {
                // 回滚事务
                Db::rollback();
                $this->error = $e->getMessage();
                var_dump(json_encode($e->getMessage()));
                var_dump(json_encode($e->getTraceAsString()));
                return false;
            }
        }
        return true;
    }
}