<?php
declare (strict_types=1);

namespace app\subscribe;

use app\common\model\MemberRecord;
use app\job\queueMerchant;
use think\facade\Log;

class MerchantSubscribe
{
    public function onMemberLogin($account)
    {
        Log::info("time<" . date('Y-m-d') . ">来自<MerchantSubscribe>:<onMemberLogin>" . json_encode($account));
    }

    public function onMemberAuthen($data)
    {
        try {
            if (is_array($data['mid'])) {
                foreach ($data['mid'] as $datum) {
                    $agent_line = agent_line($datum);
                    if (!empty($agent_line)) {
                        $queueMerchant = [
                            'queue' => 'queueMerchant', //任务
                            'task'  => 'updateDash', //任务
                            'data'  => [
                                "agent_line" => $agent_line,
                                "mid"        => $datum,
                                "data"       => [
                                    "type" => 'athen',
                                ]
                            ]
                        ];
                        queue(queueMerchant::class, $queueMerchant, 0, $queueMerchant['queue']);
                    }
                }
            } else {
                $agent_line = agent_line($data['mid']);
                if (!empty($agent_line)) {
                    $queueMerchant = [
                        'queue' => 'queueMerchant', //任务
                        'task'  => 'updateDash', //任务
                        'data'  => [
                            "agent_line" => $agent_line,
                            "mid"        => $data['mid'],
                            "data"       => [
                                "type" => 'athen',
                            ]
                        ]
                    ];
                    queue(queueMerchant::class, $queueMerchant, 0, $queueMerchant['queue']);
                }
            }
        } catch (\Exception $exception) {
            Log::info("time<" . date('Y-m-d H:i:s') . ">来自<MerchantSubscribe>:<onMemberLogin>" . $exception->getMessage());
        }
    }

    /** 分享次数 统计**/
    public function onMemberOpenShare($data)
    {
        try {
            $agent_line = agent_line_array($data['agent_line']);
            if (!empty($agent_line)) {
                $queueMerchant = [
                    'queue' => 'queueMerchant', //任务
                    'task'  => 'updateDash', //任务
                    'data'  => [
                        "agent_line" => $agent_line,
                        "mid"        => $data['id'],
                        "data"       => [
                            "type" => 'share',
                        ]
                    ]
                ];
                queue(queueMerchant::class, $queueMerchant, 0, $queueMerchant['queue']);
            }
        } catch (\Exception $exception) {
            Log::info("time<" . date('Y-m-d H:i:s') . ">来自<MerchantSubscribe>:<onMemberOpenShare>" . $exception->getMessage());
        }
    }

    public function onMemberTransfer($data)
    {
        try {
            $from_agent_line = agent_line($data['from']);
            if (!empty($from_agent_line)) {
                $queueMerchant = [
                    'queue' => 'queueMerchant', //任务
                    'task'  => 'updateDash', //任务
                    'data'  => [
                        "agent_line" => $from_agent_line,
                        "mid"        => $data['from'],
                        "data"       => [
                            "type"  => 'transfer_out',
                            "money" => $data['money'],
                        ]
                    ]
                ];
                queue(queueMerchant::class, $queueMerchant, 0, $queueMerchant['queue']);
            }
            $to_agent_line = agent_line($data['to']);
            if (!empty($from_agent_line)) {
                $queueMerchant = [
                    'queue' => 'queueMerchant', //任务
                    'task'  => 'updateDash', //任务
                    'data'  => [
                        "agent_line" => $to_agent_line,
                        "mid"        => $data['to'],
                        "data"       => [
                            "type"  => 'transfer_into',
                            "money" => $data['money'],
                        ]
                    ]
                ];
                queue(queueMerchant::class, $queueMerchant, 0, $queueMerchant['queue']);
            }
        } catch (\Exception $exception) {
            Log::info("time<" . date('Y-m-d H:i:s') . "来自<MerchantSubscribe>:<onMemberTransfer>" . $exception->getMessage());
        }
    }

    public function onPushBet($data)
    {
        $agent_line = agent_line($data['mid']);
        if (count($agent_line) > 0 && $data['type'] == 0) {
            try {
                $bet_number = redisCacheGet("Event:true:" . date("Ymd") . ":" . $data['mid']) ?: 0;
                /** 代理仪表盘更新 **/
                $queueMerchant = [
                    'queue' => 'queueMerchant', //任务
                    'task'  => 'updateDash', //任务
                    'data'  => [
                        "agent_line" => $agent_line,
                        "mid"        => $data['mid'],
                        "data"       => [
                            "type"       => 'bet',
                            "real"       => $data['type'],
                            "cycle"      => $data['cycle'],
                            "time"       => $data['create_time'],
                            "money"      => $data['money'],
                            "bet_number" => $bet_number,
                        ]
                    ]
                ];
                queue(queueMerchant::class, $queueMerchant, 0, $queueMerchant['queue']);
            } catch (\Exception $exception) {
                Log::error("time<" . date('Y-m-d H:i:s') . ">来自<MerchantSubscribe>:<onPushBet>" . $exception->getMessage());
            }
        }
    }

    public function onPushAward($data)
    {
        $agent_line = agent_line($data['mid']);
        try {
            $queueMerchantData = [
                'queue' => 'queueMerchant', //任务
                'task'  => 'updateDash', //任务
                'data'  => [
                    "agent_line" => $agent_line,
                    "mid"        => $data['mid'],
                    "data"       => [
                        "type"  => 'award',
                        "cycle" => $data['cycle'],
                        "money" => $data['money'],
                    ]
                ]
            ];
            queue(queueMerchant::class, $queueMerchantData, 0, $queueMerchantData['queue']);
        } catch (\Exception $exception) {
            Log::error("time<" . date('Y-m-d') . ">来自<MemberSubscribe>:<onPushAward>" . json_encode($exception->getMessage()));
        }
    }

    public function onMemberRegister($MemberAccount)
    {
        try {
            $agent_line = agent_line_array($MemberAccount->agent_line);
            if (empty($agent_line)) {
                return true;
            }
            $queueMerchantData = [
                'queue' => 'queueMerchant', //任务
                'task'  => 'updateDash', //任务
                'data'  => [
                    "agent_line" => $agent_line,
                    "mid"        => $MemberAccount->id,
                    'data'       => [
                        'type' => "register",
                    ]
                ]
            ];
            queue(queueMerchant::class, $queueMerchantData, 0, $queueMerchantData['queue']);
        } catch (\Exception $exception) {
            Log::error("time<" . date('Y-m-d') . ">来自<MerchantSubscribe>:<onMemberRegister>" . json_encode($exception->getMessage()));
        }
    }

    public function onMemberRecharge($data)
    {
        $member_account = member_account($data['mid']);
        if (empty($member_account) || empty($member_account['agent_line'])) {
            return true;
        }
        $agent_line  = $member_account['agent_line'];
        $create_time = $member_account['create_time'];
        try {
            $recharge_num   = redisCacheGet("Recharge:" . date("Ymd") . ":" . $data['mid']);
            $recharge_first = false;
            if ($recharge_num == 1) {
                $record = MemberRecord::where([
                    ['mid', '=', $data['mid']],
                    ['currency', '=', 1],
                    ['business', '=', 1],
                ])->count();
                if ($record == 1) {
                    $recharge_first = true;
                }
            }
            if (!empty($agent_line)) {
                $queueMerchant = [
                    'queue' => 'queueMerchant', //任务
                    'task'  => 'updateDash', //任务
                    'data'  => [
                        "agent_line" => $agent_line,
                        "mid"        => $data['mid'],
                        "data"       => [
                            "type"           => 'recharge',
                            "money"          => $data['money'],
                            "recharge_num"   => $recharge_num ?: 0,
                            "today"          => (strtotime($create_time) > strtotime(date('Y-m-d'))) ?: false,
                            "recharge_first" => $recharge_first,
                        ]
                    ]
                ];
                queue(queueMerchant::class, $queueMerchant, 0, $queueMerchant['queue']);
            }
        } catch (\Exception $exception) {
            Log::error("time<" . date('Y-m-d') . ">来自<MemberSubscribe>:<onMemberRecharge>" . json_encode($exception->getMessage()));
        }
    }

    /** 提现更新 **/
    public function onMemberWithDraw($data)
    {
        $agent_line = agent_line($data['mid']);
        try {
            if (!empty($agent_line)) {
                $withdraw_num  = redisCacheGet("WithDraw:" . date("Ymd") . ":" . $data['mid']);
                $queueMerchant = [
                    'queue' => 'queueMerchant', //任务
                    'task'  => 'updateDash', //任务
                    'data'  => [
                        "agent_line" => $agent_line,
                        "mid"        => $data['mid'],
                        "data"       => [
                            "type"         => 'withdraw',
                            "money"        => $data['money'],
                            "free"         => $data['free'],//待完善
                            "withdraw_num" => $withdraw_num ?: 0,//待完善
                        ]
                    ]
                ];
                queue(queueMerchant::class, $queueMerchant, 0, $queueMerchant['queue']);
            }
        } catch (\Exception $exception) {
            Log::error("time<" . date('Y-m-d') . ">来自<MemberSubscribe>:<onMemberRecharge>" . json_encode($exception->getMessage()));
        }
    }

    /** 分享收益代理更新 **/
    public function onMemberBetShare($data)
    {
        $agent_line = agent_line($data['mid']);
        $money      = "0";
        try {
            if (!empty($agent_line)) {
                $MemberBetShare = $data['data'];
                foreach ($MemberBetShare as $item) {
                    $money = bcadd($money, $item['data']['money'], 12);
                }
                $queueMerchant = [
                    'queue' => 'queueMerchant', //任务
                    'task'  => 'updateDash', //任务
                    'data'  => [
                        "agent_line" => $agent_line,
                        "mid"        => $data['mid'],
                        "data"       => [
                            "type"  => 'shareAmount',
                            "money" => $money,
                        ]
                    ]
                ];
                queue(queueMerchant::class, $queueMerchant, 0, $queueMerchant['queue']);
            }
        } catch (\Exception $exception) {
            Log::info("time<" . date('Y-m-d') . ">来自<MerchantSubscribe>:<onMemberBetShare>" . $exception->getMessage());
        }
    }

    /** 领取分享收益代理更新 **/
    public function onMemberReceive($data)
    {
        $agent_line = agent_line($data['mid']);
        try {
            if (!empty($agent_line)) {
                $queueMerchant = [
                    'queue' => 'queueMerchant', //任务
                    'task'  => 'updateDash', //任务
                    'data'  => [
                        "agent_line" => $agent_line,
                        "mid"        => $data['mid'],
                        "data"       => [
                            "type"  => 'receiveAmount',
                            "money" => $data['money'],
                        ]
                    ]
                ];
                queue(queueMerchant::class, $queueMerchant, 0, $queueMerchant['queue']);
            }
        } catch (\Exception $exception) {
            Log::info("time<" . date('Y-m-d H:i:s') . $exception->getMessage());
        }
    }

    /** 提现更新 **/
    public function onMerchantTranster($data)
    {
//        [
//            'from' => '',
//            'to' => '',
//            'is_member' => true;
//            'money' => ''
//        ]
        try {
            if (!empty($data['from'])) {
                $queueMerchant = [
                    'queue' => 'queueMerchant', //任务
                    'task'  => 'updateDash', //任务
                    'data'  => [
                        "agent_line" => [$data['from']],
                        "mid"        => $data['mid'],
                        "data"       => [
                            "type"                      => 'merchant_transfer_out',
                            "money"                     => $data['money'],
                            "merchant_transfer_out_num" => 0,
                        ]
                    ]
                ];
                queue(queueMerchant::class, $queueMerchant, 0, $queueMerchant['queue']);
            }
            if (!$data['is_member']) {
                $agent_line = [$data['to']];
                if (!empty($agent_line)) {
                    $queueMerchant = [
                        'queue' => 'queueMerchant', //任务
                        'task'  => 'updateDash', //任务
                        'data'  => [
                            "agent_line" => $agent_line,
                            "mid"        => $data['mid'],
                            "data"       => [
                                "type"                       => 'merchant_transfer_into',
                                "money"                      => $data['money'],
                                "merchant_transfer_into_num" => 0,
                            ]
                        ]
                    ];
                    queue(queueMerchant::class, $queueMerchant, 0, $queueMerchant['queue']);
                }
            }
        } catch (\Exception $exception) {
            Log::error("time<" . date('Y-m-d') . ">来自<MemberSubscribe>:<onMemberRecharge>" . json_encode($exception->getMessage()));
        }
    }

    /** 提现更新 **/
    public function onInternalRecharge($data)
    {
//        [
//            'to'        => '',
//            'is_member' => true,
//            'money'     => ''
//        ]
        try {
            if (!empty($data['to']) && !$data['is_member']) {
                $queueMerchant = [
                    'queue' => 'queueMerchant', //任务
                    'task'  => 'updateDash', //任务
                    'data'  => [
                        "agent_line" => [$data['to']],
                        "data"       => [
                            "type"  => 'internal_recharge',
                            "money" => $data['money'],
                        ]
                    ]
                ];
                queue(queueMerchant::class, $queueMerchant, 0, $queueMerchant['queue']);
            }
        } catch (\Exception $exception) {
            Log::error("time<" . date('Y-m-d') . ">来自<MemberSubscribe>:<onInternalRecharge>" . json_encode($exception->getMessage()));
        }
    }
}
