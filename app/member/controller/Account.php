<?php
declare (strict_types=1);

namespace app\member\controller;

use app\common\controller\member\Wallet;
use app\common\model\ActivityComplete;
use app\common\model\ActivityList;
use app\common\model\GameEventBet;
use app\common\model\GameEventBetOld;
use app\common\model\GameEventList;
use app\common\model\MemberAccount;
use app\common\model\MemberDashDay;
use app\common\model\MemberDay;
use app\common\model\MemberPayOrder;
use app\common\model\MemberProfile;
use app\common\model\MemberRecord;
use app\common\model\MemberShare;
use app\common\model\MemberTeam;
use app\common\model\MemberWallet;
use app\common\model\MerchantDashboard;
use app\common\service\RedisLock;
use app\member\BaseCustomer;
use app\member\middleware\jwtVerification;
use think\App;
use think\facade\Db;
use think\facade\Lang;

class Account extends BaseCustomer
{
    protected $middleware
        = [
            jwtVerification::class => [
                'except' => ['as']
            ]
        ];

    /**
     * 赛事交易列表
     */
    public function user_bet()
    {
        $where = [
            'mid'  => $this->request->customer->mid,
            'type' => $this->request->param('type/d', 0),
        ];
        $data  = GameEventBet::getList($where,
            $this->request->param('page/d', 1), $this->request->param('limit/d', 20),
            '*', 'id desc'
        );
        return success($data);
    }

    /**
     * 历史列表
     */
    public function history_list()
    {
        $cid   = $this->request->param('cid', 1);
        $where = [
            ['type', '=', $this->request->param('type', '1m')],
            ['cid', '=', $cid],
            ['end_time', '<', time()],
        ];
        $page  = $this->request->param('page/d', 1);
        $limit = $this->request->param('limit/d', 20);
        if ($limit > 30) {
            $limit = 30;
        }
        $data = GameEventList::getList($where,
            $page,
            $limit,
            '*', 'end_time desc'
        );

        return success($data);
    }

    /**
     * 盈亏统计
     */
    public function day_list()
    {
        $where = [
            ['mid', '=', $this->request->customer->mid],
            ['date', '<=', date('Y-m-d')],
        ];
        $data  = MemberDashDay::getList($where,
            $this->request->param('page/d', 1), $this->request->param('limit/d', 20),
            'date,award,award_amount,bet,bet_amount', 'id desc'
        );
        return success($data);
    }

    /**
     * 实名信息
     */
    public function authen()
    {
        return success(MemberProfile::where('mid', $this->request->customer->mid)->find());
    }

    /**
     * 实名信息
     */
    public function share()
    {
        $agent = MemberAccount::where([['id', '=', $this->request->customer->mid]])->find();
        if (empty($agent)) {
            return error();
        }
        foreach (explode('|', $agent->agent_line) as $item) {
            MerchantDashboard::where([
                ['uid', '=', $item]
            ])->inc('share')->update();
        }
        return success();
    }

    /**
     * @return \think\response\Json
     * 个人流水
     */
    public function record()
    {
        $where = ['mid' => $this->request->customer->mid, 'currency' => $this->request->param('currency', 1)];
        if (!empty($this->request->param('business'))) {
            $where['business'] = $this->request->param('business');
        }
        $data = MemberRecord::getList($where,
            $this->request->param('page/d', 1), $this->request->param('limit/d', 20),
            '*', 'id desc'
        );
        return success($data);
    }

    /**
     * 团队
     */
    public function team()
    {
        $share       = explode("|", get_config('site', 'setting', 'share'));
        $super       = get_config('site', 'setting', 'super');
        $MemberTeam  = MemberTeam::where([['mid', '=', $this->request->customer->mid]])->find();
        $MemberShare = MemberShare::where([['mid', '=', $this->request->customer->mid]])->field('one AS first,two AS second,three AS third')->find();
        if (empty($MemberTeam) || empty($MemberShare)) {
            return success([
                'first_proportion'  => $share[0],
                'second_proportion' => $share[1],
                'third_proportion'  => $share[2],
                'vip_proportion'    => $super,
                'first_share'       => 0,
                'second_share'      => 0,
                'third_share'       => 0,
                'vip'               => 0,
                'first_receive'     => 0,
                'second_receive'    => 0,
                'third_receive'     => 0,
                'vip_receive'       => 0,
                'all_receive'       => 0,
                'all_share'         => 0,
            ]);
        };
        $first_receive  = $MemberTeam->first_share - $MemberTeam->first_receive;
        $second_receive = $MemberTeam->second_share - $MemberTeam->second_receive;
        $third_receive  = $MemberTeam->third_share - $MemberTeam->third_receive;
        $vip_receive    = $MemberTeam->vip - $MemberTeam->vip_receive;
        $data           = [
            'first_proportion'  => $share[0],
            'second_proportion' => $share[1],
            'third_proportion'  => $share[2],
            'vip_proportion'    => $super,
            'first_share'       => $MemberTeam->first_share ?: 0,
            'second_share'      => $MemberTeam->second_share ?: 0,
            'third_share'       => $MemberTeam->third_share ?: 0,
            'vip'               => $MemberTeam->vip ?: 0,
            'first_receive'     => $first_receive > 0 ? $first_receive : 0,
            'second_receive'    => $second_receive > 0 ? $second_receive : 0,
            'third_receive'     => $third_receive > 0 ? $third_receive : 0,
            'vip_receive'       => $vip_receive > 0 ? $vip_receive : 0,
            'all_receive'       => $MemberTeam->all_receive,
            'all_share'         => $MemberTeam->all_share,
        ];
        $data           = array_merge($data, $MemberShare->toArray());
        return success($data);
    }

    /**
     * 领取分享收益
     */
    public function receive()
    {
        /** 枷锁 **/
        if (!(new RedisLock('receive:' . $this->request->customer->mid, 5))->lock()) {
            return error(lang::Get('bv'));
        }
        $reciveType    = $this->request->param('type', 1);
        $MemberAccount = MemberAccount::find($this->request->customer->mid);
        $MemberTeam    = MemberTeam::where([['mid', '=', $this->request->customer->mid]])->find();
        switch ($reciveType) {
            case 1:
                $receive = $MemberTeam->first_share - $MemberTeam->first_receive;
                break;
            case 2:
                $receive = $MemberTeam->second_share - $MemberTeam->second_receive;
                break;
            case 3:
                $receive = $MemberTeam->third_share - $MemberTeam->third_receive;
                break;
            case 0:
                $receive = $MemberTeam->vip - $MemberTeam->vip_receive;
                break;
        }
        $money = money_format_bet($receive);//处理php数据精度问题!
        if ($money > 0) {
            if ($MemberAccount->wallet->eth < $money) {
                return error(lang('av'));
            }
            Db::startTrans();
            try {
                $MemberTeam     = MemberTeam::where([['mid', '=', $this->request->customer->mid]]);
                $MemberTeamData = [];
                switch ($reciveType) {
                    case 1:
                        $MemberTeamData['first_receive'] = Db::raw("first_receive+" . $money);
                        break;
                    case 2:
                        $MemberTeamData['second_receive'] = Db::raw("second_receive+" . $money);
                        break;
                    case 3:
                        $MemberTeamData['third_receive'] = Db::raw("third_receive+" . $money);
                        break;
                    case 0:
                        $MemberTeamData['vip_receive'] = Db::raw("vip_receive+" . $money);
                        break;
                }
                $MemberTeamData['all_receive'] = Db::raw("all_receive+" . $money);
                $bool                          = $MemberTeam->update($MemberTeamData);
                if (empty($bool)) {
                    return error(lang::Get('Commission collection failed'));
                }
                (new Wallet())->change($this->request->customer->mid, 13, [
                    1 => [$MemberAccount->wallet->cny, $money, $MemberAccount->wallet->cny + $money],
                    4 => [$MemberAccount->wallet->eth, -$money, $MemberAccount->wallet->eth - $money]
                ], '', $reciveType);

                Db::commit();
            } catch (\Exception $e) {
                Db::rollback();
                return error($e->getMessage(), 201, 200, $e->getTraceAsString());
            }
            event('MemberReceive', ['mid' => $this->request->customer->mid, 'type' => $reciveType, 'money' => $money]);
        } else {
            return error(lang::Get('av'));
        }
        return success(lang::Get('au'));
    }

    /**
     * 返回用户状态
     */
    public function bet_type()
    {
        $data = MemberAccount::where(['id' => $this->request->customer->mid])->field('signal,bet_type,bet_money,error_password')->find();
        return success($data);
    }

    /**
     * 个人余额
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function wallet()
    {
        $data                 = MemberWallet::where(['mid' => $this->request->customer->mid])->field('cny,usd,usdt,eth,btc')->find();
        $account              = MemberAccount::where(['id' => $this->request->customer->mid])->find();
        $data->share          = abs(MemberRecord::where([['mid', '=', $this->request->customer->mid], ['business', '=', 13], ['currency', '=', 1]])->sum('now'));
        $data->mining         = abs(MemberRecord::where([['mid', '=', $this->request->customer->mid], ['business', '=', 14], ['currency', '=', 1]])->sum('now'));
        $rand                 = rand(0, 100);
        $probability          = get_config('wallet', 'wallet', 'withdraw_sms_probability') > $rand ? 1 : 0;
        $account->probability = $probability;
        $account->save();
        $is_first = MemberRecord::where([['mid', '=', $this->request->customer->mid], ['business', '=', 2]])->find() ? 1 : 0;
        if ($account->level < 2 || $is_first == 0 || $account->authen != 1 || $probability == "1") {
            $probability = 1;
        }
        $data->withdrawal = [
            'is_first'                 => $is_first,
            'is_updatesafeword'        => password_verify('733333', $account->safeword) ? 0 : 1,
            'address'                  => $account->dashboard->withdraw_address,
            'withdraw_sms_probability' => $probability,
        ];
        return success($data);
    }

    /**
     * 查询对方账户
     */
    public function find_user()
    {
        return success(MemberProfile::where([['mid', '=', $this->request->param('mid/s')]])->find());
    }

    /**
     * 挖矿
     */
    public function mining()
    {
        $MemberAccount = MemberAccount::find($this->request->customer->mid);
        /** 枷锁 **/
        if (!(new RedisLock('mining_mining:' . $this->request->customer->mid, 5))->lock()) {
            return error(lang::Get('bv'));
        }
        Db::startTrans();
        try {
            if (get_config('sizzler', 'sizzler', 'mining') > $MemberAccount->wallet->usd) {
                return error(lang::Get('at') . get_config('sizzler', 'sizzler', 'mining'));
            }
            $mix    = (int)(get_config('sizzler', 'sizzler', 'mining_min') * 10000);
            $max    = (int)(get_config('sizzler', 'sizzler', 'mining_max') * 10000);
            $amount = rand($mix, $max) / 10000;
            (new Wallet())->change($this->request->customer->mid, 12, [
                3 => [$MemberAccount->wallet->usdt, $amount, $MemberAccount->wallet->usdt + $amount],
            ]);
            (new Wallet())->change($this->request->customer->mid, 12, [
                2 => [$MemberAccount->wallet->usd, -get_config('sizzler', 'sizzler', 'mining'), ($MemberAccount->wallet->usd - get_config('sizzler', 'sizzler', 'mining'))],
            ]);
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            return error($e->getMessage(), 201, 200, $e->getTrace());
        }
        return success($amount, lang::Get('aw'));
    }

    /**
     * 领取挖矿收益
     */
    public function mining_receive()
    {
        /** 枷锁 **/
        if (!(new RedisLock('mining_receive:' . $this->request->customer->mid, 5))->lock()) {
            return error(lang::Get('bv'));
        }
        $MemberAccount = MemberAccount::find($this->request->customer->mid);
        Db::startTrans();
        try {
            if ($MemberAccount->wallet->usdt <= "0") {
                return error(lang::Get('ay'));
            }
            $amount = $MemberAccount->wallet->usdt;
            (new Wallet())->change($this->request->customer->mid, 14, [
                1 => [$MemberAccount->wallet->cny, $amount, $MemberAccount->wallet->cny + $amount],
            ]);
            (new Wallet())->change($this->request->customer->mid, 14, [
                3 => [$MemberAccount->wallet->usdt, -$amount, $MemberAccount->wallet->usdt - $amount],
            ]);
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            return error($e->getMessage(), 201, 200, $e->getTrace());
        }
        return success(lang::Get('ax'));
    }

    /**
     *活动列表
     */
    public function activity_list()
    {
        $activity_list = ActivityList::where(1)->select()->toArray();
        foreach ($activity_list as &$value) {
            $value['activity'] = 2;
            switch ($value['type']) {
                case 0:
                    $pay_count      = MemberPayOrder::where([['mid', '=', $this->request->customer->mid]])->sum('number');
                    $activity_lists = ActivityComplete::where([['list_id', '=', $value['id']], ['mid', '=', $this->request->customer->mid], ['create_time', '>', strtotime(date('Y-m-d'))]])->count();
                    if ($pay_count >= $value['must'] && ($value['frequency'] == "0" || $activity_lists < $value['frequency'])) {
                        $value['activity'] = 1;
                    } elseif ($activity_lists == "0") {
                        $value['activity'] = 0;
                    }
                    break;
                case 1:
//                    $activity_lists = ActivityComplete::where([['list_id', '=', $value['id']], ['mid', '=', $this->request->customer->mid], ['create_time', '>', strtotime(date('Y-m-d'))]])->find();
//                    if (empty($activity_lists)) {
//                        $value['activity'] = 1;
//                    } elseif ($activity_lists == "0") {
//                        $value['activity'] = 0;
//                    }
                    $activity_lists = ActivityComplete::where([['list_id', '=', $value['id']], ['mid', '=', $this->request->customer->mid], ['create_time', '>', time() - 60 * 60]])->find();
                    if (empty($activity_lists)) {
                        $value['activity'] = 1;
                    } elseif ($activity_lists == "0") {
                        $value['activity'] = 0;
                    }
                    break;
                case 2:
                    $bet_count      = GameEventBet::where([['type', '=', 0], ['mid', '=', $this->request->customer->mid]])->count();
                    $activity_lists = ActivityComplete::where([['list_id', '=', $value['id']], ['mid', '=', $this->request->customer->mid], ['create_time', '>', strtotime(date('Y-m-d'))]])->count();
                    if ($bet_count > $value['must'] && ($value['frequency'] == "0" || $activity_lists < $value['frequency'])) {
                        $value['activity'] = 1;
                    } elseif ($activity_lists == "0") {
                        $value['activity'] = 0;
                    }
                    break;
                case 3:
                    $activity_lists = ActivityComplete::where([['list_id', '=', $value['id']], ['mid', '=', $this->request->customer->mid], ['create_time', '>', strtotime(date('Y-m-d'))]])->count();
                    $activity_aa    = true;
                    for ($i = 1; $i < $value['must']; $i++) {
                        $start_time       = strtotime(date('Y-m-d', (time() - $i * 86400)));
                        $end_time         = strtotime(date('Y-m-d', (time() - $i * 86400)) . ' 24:00:00');
                        $activity_one[$i] = GameEventBetOld::where([['type', '=', 0], ['mid', '=', $this->request->customer->mid], ['create_time', '>', $start_time], ['create_time', '<', $end_time]])->find();
                        if (empty($activity_one[$i])) {
                            $activity_aa = false;
                        }
                    }
                    $frequency = ($value['frequency'] == "0" || $activity_lists < $value['frequency']);
                    if ($activity_aa != false && $frequency) {
                        $value['activity'] = 1;
                    } elseif ($activity_lists == "0") {
                        $value['activity'] = 0;
                    }
                    break;
                case 4:
                    $bet_count      = GameEventBet::where([['type', '=', 1], ['mid', '=', $this->request->customer->mid]])->count();
                    $activity_lists = ActivityComplete::where([['list_id', '=', $value['id']], ['mid', '=', $this->request->customer->mid], ['create_time', '>', strtotime(date('Y-m-d'))]])->count();
                    if ($bet_count > $value['must'] && ($value['frequency'] == "0" || $activity_lists < $value['frequency'])) {
                        $value['activity'] = 1;
                    } elseif ($activity_lists == "0") {
                        $value['activity'] = 0;
                    }
                    break;
                case 5:
                    $activity_lists = ActivityComplete::where([['list_id', '=', $value['id']], ['mid', '=', $this->request->customer->mid], ['create_time', '>', strtotime(date('Y-m-d'))]])->count();
                    $activity_aa    = true;
                    for ($i = 0; $i < $value['must']; $i++) {
                        $activity_one = GameEventBet::where([['type', '=', 1], ['mid', '=', $this->request->customer->mid]], ['create_time', '>', strtotime(date('Y-m-d', (time() - $i * 86400)))], ['create_time', '<', strtotime(date('Y-m-d', (time() - $i * 86400)) . ' 24:00:00')])->find();
                        if (empty($activity_one)) {
                            $activity_aa = false;
                        }
                    }
                    if ($activity_aa != false && ($value['frequency'] == "0" || $activity_lists < $value['frequency'])) {
                        $value['activity'] = 1;
                    } elseif ($activity_lists == "0") {
                        $value['activity'] = 0;
                    }
                    break;
                case 6:
                    $activity_lists = ActivityComplete::where([['list_id', '=', $value['id']], ['mid', '=', $this->request->customer->mid]])->count();
                    $account_count  = MemberAccount::where([
                        ['inviter_line', 'like', "%|" . $this->request->customer->mid . "|%"],
                        ['authen', '=', 1],
                    ])->count();
                    if (($account_count / 2 - $activity_lists) > 1) {
                        $value['activity'] = 1;
                    } else {
                        $value['activity'] = 0;
                    }
                    break;
            }
        }
        return success($activity_list);
    }

    /**
     * 活动领取
     */
    public function activity()
    {
        /** 枷锁 **/
        if (!(new RedisLock('activity:' . $this->request->customer->mid, 5))->lock()) {
            return error(lang::Get('bv'));
        }
        $MemberAccount = MemberAccount::find($this->request->customer->mid);
        $activity      = false;
        $activity_list = ActivityList::where([['id', '=', $this->request->param('activity/d')]])->find();
        switch ($activity_list->type) {
            case 0:
                $pay_count      = MemberPayOrder::where([['mid', '=', $this->request->customer->mid], ['create_time', '>', strtotime(date('Y-m-d'))]])->sum('number');
                $activity_lists = ActivityComplete::where([['list_id', '=', $activity_list->id], ['mid', '=', $this->request->customer->mid], ['create_time', '>', strtotime(date('Y-m-d'))]])->count();
                if ($pay_count >= $activity_list->must && ($activity_list->frequency == "0" || $activity_lists < $activity_list->frequency)) {
                    $activity = true;
                }
                break;
            case 1:
                /**
                 * 每日签到
                 */
//                $activity_lists = ActivityComplete::where([['list_id', '=', $activity_list->id], ['mid', '=', $this->request->customer->mid], ['create_time', '>', strtotime(date('Y-m-d'))]])->find();
//                if (empty($activity_lists)) {
//                    $activity = true;
//                }
//                break;
                /**
                 * 每小时签到
                 */
                $activity_lists = ActivityComplete::where([['list_id', '=', $activity_list->id], ['mid', '=', $this->request->customer->mid], ['create_time', '>', time() - 60 * 60]])->find();
                if (empty($activity_lists)) {
                    $activity = true;
                }
                break;
            case 2:
                $bet_count      = GameEventBet::where([['type', '=', 0], ['mid', '=', $this->request->customer->mid]])->count();
                $activity_lists = ActivityComplete::where([['list_id', '=', $activity_list->id], ['mid', '=', $this->request->customer->mid], ['create_time', '>', strtotime(date('Y-m-d'))]])->count();
                if ($bet_count > $activity_list->must && ($activity_list->frequency == "0" || $activity_lists < $activity_list->frequency)) {
                    $activity = true;
                }
                break;
            case 3:
                $activity_lists = ActivityComplete::where([['list_id', '=', $activity_list->id], ['mid', '=', $this->request->customer->mid], ['create_time', '>', strtotime(date('Y-m-d'))]])->count();
                $activity_aa    = true;
                for ($i = 0; $i < $activity_list->must; $i++) {
                    $activity_one = GameEventBet::where([['type', '=', 0], ['mid', '=', $this->request->customer->mid]], ['create_time', '>', strtotime(date('Y-m-d', (time() - $i * 86400)))], ['create_time', '<', strtotime(date('Y-m-d', (time() - $i * 86400)) . ' 24:00:00')])->find();
                    if (empty($activity_one)) {
                        $activity_aa = false;
                    }
                }
                if ($activity_aa != false && ($activity_list->frequency == "0" || $activity_lists < $activity_list->frequency)) {
                    $activity = true;
                }
                break;
            case 4:
                $bet_count      = GameEventBet::where([['type', '=', 1], ['mid', '=', $this->request->customer->mid]])->count();
                $activity_lists = ActivityComplete::where([['list_id', '=', $activity_list->id], ['mid', '=', $this->request->customer->mid], ['create_time', '>', strtotime(date('Y-m-d'))]])->count();
                if ($bet_count > $activity_list->must && ($activity_list->frequency == "0" || $activity_lists < $activity_list->frequency)) {
                    $activity = true;
                }
                break;
            case 5:
                $activity_lists = ActivityComplete::where([['list_id', '=', $activity_list->id], ['mid', '=', $this->request->customer->mid], ['create_time', '>', strtotime(date('Y-m-d'))]])->count();
                $activity_aa    = true;
                for ($i = 0; $i < $activity_list->must; $i++) {
                    $activity_one = GameEventBet::where([['type', '=', 1], ['mid', '=', $this->request->customer->mid]], ['create_time', '>', strtotime(date('Y-m-d', (time() - $i * 86400)))], ['create_time', '<', strtotime(date('Y-m-d', (time() - $i * 86400)) . ' 24:00:00')])->find();
                    if (empty($activity_one)) {
                        $activity_aa = false;
                    }
                }
                if ($activity_aa != false && ($activity_list->frequency == "0" || $activity_lists < $activity_list->frequency)) {
                    $activity = true;
                }
                break;
            case 6:
                $activity_lists = ActivityComplete::where([['list_id', '=', $activity_list->id], ['mid', '=', $this->request->customer->mid]])->count();
                $account_count  = MemberAccount::where([
                    ['inviter_line', 'like', "%|" . $this->request->customer->mid . "|%"],
                    ['authen', '=', 1],
                ])->count();
                if (($account_count / 2 - $activity_lists) > 1) {
                    $activity = true;
                } else {
                    $activity = false;
                }
                break;
        }
        if ($activity == true) {
            Db::startTrans();
            try {
                ActivityComplete::setAdd([
                    'mid'     => $this->request->customer->mid,
                    'money'   => $activity_list->number,
                    'list_id' => $this->request->param('activity/d'),
                ]);
                (new Wallet())->change($this->request->customer->mid, 2, [
                    2 => [$MemberAccount->wallet->usd, $activity_list->number, $MemberAccount->wallet->usd + $activity_list->number],
                ]);
                Db::commit();
            } catch (\Exception $e) {
                Db::rollback();
                return error($e->getMessage(), 201, 200, $e->getTrace());
            }
            return success(lang::Get('ax'));
        } else {
            return error(lang::Get('ay'));
        }
    }

    public function as()
    {
        $lock = new RedisLock('as');
        if ($lock->Lock()) {
            $lock->unLock();
            return success(555);
        } else {
            return error('太快!');
        }
    }
}
