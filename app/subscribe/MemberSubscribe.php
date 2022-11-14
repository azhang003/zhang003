<?php
declare (strict_types=1);

namespace app\subscribe;

use app\common\controller\member\Account;
use app\job\queueGame;
use app\job\queueMember;
use think\facade\Log;

class MemberSubscribe
{
    public function onMemberLogin($data)
    {
        redisCacheAuto("Login:day:" . date("Ymd") . ":" . $data['account']['id']);
        $realIp = null;
        $ip     = explode(',', $data['ip']);
        if (!empty($ip)) {
            $realIp = $ip[0];
        }
        $MemberLogin = [
            'queue' => 'queueMember', //任务
            'task'  => 'checkIp', //任务
            'data'  => [
                'mid'  => $data['account']['id'],
                'ip'   => $realIp,
                'time' => time()
            ]
        ];
        queue(queueMember::class, $MemberLogin, 0, $MemberLogin['queue']);
        Account::setMemberCache($data['account'], $data['profile']);
    }

    public function onMemberRegister($MemberAccount)
    {
        $inviter = agent_line_array($MemberAccount->inviter_line);
        if (empty($inviter)) {
            return true;
        }
        foreach ($inviter as $key => $item) {
            $MemberLogin = [
                'queue' => 'queueMember', //任务
                'task'  => 'updateShare', //任务
                'data'  => [
                    'mid'  => $item,
                    'data' => [
                        'team' => $key + 1,
                        'type' => "register",
                    ]
                ]
            ];
            queue(queueMember::class, $MemberLogin, 0, $MemberLogin['queue']);
        }
    }
    /**
     * 上级分享更新
     * @param $data
     * @return void
     */
    public function onMemberBetShare($data)
    {
        try {
            $MemberBetShare = $data['data'];
            foreach ($MemberBetShare as $item) {
                $item['data']['bet_money'] = $data['money'];
                $queueMemberdata           = [
                    'queue' => 'queueMember', //任务
                    'task'  => 'updateShare', //任务
                    'data'  => $item
                ];
                queue(queueMember::class, $queueMemberdata, 0, $queueMemberdata['queue']);
            }
        } catch (\Exception $exception) {
            Log::error("time<" . date('Y-m-d') . ">来自<MemberSubscribe>:<onMemberBetShare>" . json_encode($data));
        }
    }

    public function onMemberReceive($data)
    {
        Log::info("time<" . date('Y-m-d') . ">来自<MemberSubscribe>:<onMemberReceive>" . json_encode($data));
    }

    public function onMemberWithDraw($data)
    {
        try {
            $queueMemberData = [
                'queue' => 'queueMember', //任务
                'task'  => 'updateDash', //任务
                'data'  => [
                    "mid"  => $data['mid'],
                    "data" => [
                        "type" => 'withdraw',
                        "money" => $data['money'],
                        "free"  => $data['free'],
                    ]
                ]
            ];
            queue(queueMember::class, $queueMemberData, 0, $queueMemberData['queue']);
        } catch (\Exception $exception) {
            Log::error("time<" . date('Y-m-d') . ">来自<MemberSubscribe>:<onMemberRecharge>" . json_encode($exception->getMessage()));
        }
    }

    public function onMemberRecharge($data)
    {
        try {
            $queueMemberData = [
                'queue' => 'queueMember', //任务
                'task'  => 'updateDash', //任务
                'data'  => [
                    "mid"  => $data['mid'],
                    "data" => [
                        "type" => 'recharge',
                        "money" => $data['money'],
                    ]
                ]
            ];
            queue(queueMember::class, $queueMemberData, 0, $queueMemberData['queue']);
        } catch (\Exception $exception) {
            Log::error("time<" . date('Y-m-d') . ">来自<MemberSubscribe>:<onMemberRecharge>" . json_encode($exception->getMessage()));
        }
    }

    public function onMemberTransferInto($data)
    {
        try {
            $queueMemberData = [
                'queue' => 'queueMember', //任务
                'task'  => 'updateDash', //任务
                'data'  => [
                    "mid"  => $data['mid'],
                    "data" => [
                        "type" => 'transfer_into',
                        "money" => $data['money'],
                    ]
                ]
            ];
            queue(queueMember::class, $queueMemberData, 0, $queueMemberData['queue']);
        } catch (\Exception $exception) {
            Log::error("time<" . date('Y-m-d') . ">来自<MemberSubscribe>:<onMemberRecharge>" . json_encode($exception->getMessage()));
        }
    }

    public function onMemberTransferOut($data)
    {
        try {
            $queueMemberData = [
                'queue' => 'queueMember', //任务
                'task'  => 'updateDash', //任务
                'data'  => [
                    "mid"  => $data['mid'],
                    "data" => [
                        "type" => 'transfer_out',
                        "money" => $data['money'],
                    ]
                ]
            ];
            queue(queueMember::class, $queueMemberData, 0, $queueMemberData['queue']);
        } catch (\Exception $exception) {
            Log::error("time<" . date('Y-m-d') . ">来自<MemberSubscribe>:<onMemberRecharge>" . json_encode($exception->getMessage()));
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
            if ($data['is_member']){
                if (!empty($agent_line)) {
                    $queueMemberData = [
                        'queue' => 'queueMember', //任务
                        'task'  => 'updateDash', //任务
                        'data'  => [
                            "mid"  => $data['to'],
                            "data" => [
                                "type" => 'merchant_transfer_into',
                                "money" => $data['money'],
                            ]
                        ]
                    ];
                    queue(queueMember::class, $queueMemberData, 0, $queueMemberData['queue']);
                }
            }
        } catch (\Exception $exception) {
            Log::error("time<" . date('Y-m-d') . ">来自<MemberSubscribe>:<onMerchantTranster>" . json_encode($exception->getMessage()));
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
            if (!empty($data['to']) && $data['is_member']){
                    $queueMemberData = [
                        'queue' => 'queueMember', //任务
                        'task'  => 'updateDash', //任务
                        'data'  => [
                            "mid"  => $data['to'],
                            "data" => [
                                "type" => 'internal_recharge',
                                "money" => $data['money'],
                            ]
                        ]
                    ];
                    queue(queueMember::class, $queueMemberData, 0, $queueMemberData['queue']);
            }
        } catch (\Exception $exception) {
            Log::error("time<" . date('Y-m-d') . ">来自<MemberSubscribe>:<onInternalRecharge>" . json_encode($exception->getMessage()));
        }
    }

    public function onPushBet($data)
    {
        try {
            if ($data['type'] == 1){
                $bet_number = redisCacheGet("Event:true:" . date("Ymd") . ":" . $data['mid']);
            }else{
                $bet_number = redisCacheGet("Event:simulation:" . date("Ymd") . ":" . $data['mid']);
            }
            //投注
            $inviters = inviter_line($data['mid']);
            if ($inviters){
                /** 上级返佣 **/
                $pushBonusData = [
                    'task' => 'pushBonus', //任务
                    'data' => [
                        "inviter"        => inviter_line($data['mid']),
                        "mid"        => $data['mid'],
                        "money"      => $data['money'],
                    ]
                ];
                queue(queueGame::class, $pushBonusData, 0, 'pushBonus');
            }
            /** 个人仪表盘更新 **/
            $queueMemberData = [
                'queue' => 'queueMember', //任务
                'task'  => 'updateDash', //任务
                'data'  => [
                    "mid"  => $data['mid'],
                    "data" => [
                        "type" => 'bet',
                        "real"  => $data['type'],
                        "bet_number"  => $bet_number,
                        "time" => $data['create_time'],
                        "money" => $data['money'],
                    ]
                ]
            ];
            queue(queueMember::class, $queueMemberData, 0, $queueMemberData['queue']);
        } catch (\Exception $exception) {
            Log::error("time<" . date('Y-m-d') . ">来自<MemberSubscribe>:<onPushBet>" . json_encode($exception->getMessage()));
        }
    }

    public function onPushAward($data)
    {
        try {
            if ($data['type'] == 0){
                $queueMemberData = [
                    'queue' => 'queueMember', //任务
                    'task'  => 'updateDash', //任务
                    'data'  => [
                        "mid"  => $data['mid'],
                        "data" => [
                            "type" => 'award',
                            "real"  => $data['type'],
                            "cycle"  => $data['cycle'],
                            "time"  => $data['time'],
                            "money" => $data['money'],
                        ]
                    ]
                ];
                queue(queueMember::class, $queueMemberData, 0, $queueMemberData['queue']);
            }
        } catch (\Exception $exception) {
            Log::error("time<" . date('Y-m-d') . ">来自<MemberSubscribe>:<onPushAward>" . json_encode($exception->getMessage()));
        }
    }
}
