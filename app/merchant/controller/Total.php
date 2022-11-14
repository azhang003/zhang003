<?php

namespace app\merchant\controller;

use app\common\model\MemberAccount;
use app\merchant\BaseMerchant;
use app\merchant\middleware\jwtVerification;
use think\facade\Db;
use think\response\Json;

class Total extends BaseMerchant
{
    protected $middleware
        = [
            jwtVerification::class => [
                'except' => []
            ]
        ];


    /**
     * 分享总统计
     * @return Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function shareTotal(): Json
    {
        $mid   = $this->request->param('mid', null);
        $page  = $this->request->param('page', 1);
        $limit = $this->request->param('limit', 10);
        $type  = $this->request->param('type', 1);
        $query = Db::table('ea_member_account')
            ->alias('a')
            ->join('ea_member_share s', 'a.id = s.mid')
            ->join('ea_member_profile p', 'a.id = p.mid')
            ->join('ea_member_dash d', 'a.id = d.mid')
            ->join('ea_member_team t', 'a.id = t.mid')
            ->join('ea_member_wallet w', 'a.id = w.mid');
        if (empty($mid)) {
            $where = [['a.agent_line', 'like', '%|' . $this->request->merchant->id . '|%'], ['a.floor', '=', 0]];
        } else {
            $account = member_account($mid);
            if (empty($account)) {
                return success([
                    "page"  => $page,
                    "limit" => $limit,
                    "count" => 0,
                    'list'  => []
                ]);
            }
            if (empty($type) || !in_array($type, [1, 2, 3])) {
                $type = 1;
            }
            $where = [['a.inviter_line', 'like', '%|' . $mid . '|%'], ['a.floor', '=', $account['floor'] + $type]];
        }
        $query->where($where);
        $count   = $query->count();
        $members = $query
            ->Field('d.award AS personal_award,d.award_amount AS personal_award_amount,d.bet AS personal_bet,d.bet_amount AS personal_bet_amount,d.recharge_amount AS personal_recharge_amount,d.withdraw_amount AS personal_withdraw_amount,t.all_share AS personal_share,w.cny,p.mobile,p.nickname,s.mid,s.bet,s.bet_amount,s.recharge,s.withdraw,s.withdraw_amount,s.recharge_amount,s.award,s.award_amount,s.people,s.share,s.one,s.two,s.three,s.one_quantity,s.two_quantity,s.three_quantity')
            ->page($page)
            ->limit($limit)
            ->order('s.bet ASC')
            ->select();
        return success([
            "pages" => ceil($count / $limit),
            "page"  => $page,
            "limit" => $limit,
            "count" => $count,
            'list'  => $members
        ]);
    }


    /**
     * 每日分享统计
     * @return Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function shareDay(): Json
    {
        $mid   = $this->request->param('mid', null);
        $page  = $this->request->param('page', 1);
        $limit = $this->request->param('limit', 10);
        $type  = $this->request->param('type', 1);
        $query = Db::table('ea_member_account')
            ->alias('a')
            ->join('ea_member_share_day s', 'a.id = s.mid');
        if (empty($mid)) {
            $where = [['a.agent_line', 'like', '%' . $this->request->merchant->id . '%']];
        } else {
            $account = member_account($mid);
            if (empty($account)) {
                return success([
                    "page"  => $page,
                    "limit" => $limit,
                    "count" => 0,
                    'list'  => []
                ]);
            }
            if (empty($type) || !in_array($type, [1, 2, 3])) {
                $type = 1;
            }
            $where = [['a.inviter_line', 'like', '%' . $mid . '%'], ['a.floor', '=', $account['floor'] + $type]];
        }
        /** 时间 **/
        $where[] = ['s.date', '=', date('Y-m-d')];

        $query->where($where);
        $count   = $query->count();
        $members = $query
            ->Field('s.mid,s.people,s.date,s.share,s.one,s.two,s.three,s.one_quantity,s.two_quantity,s.three_quantity')
            ->page($page)
            ->limit($limit)
            ->order('s.bet ASC')
            ->select();
        return success([
            "pages" => ceil($count / $limit),
            "page"  => $page,
            "limit" => $limit,
            "count" => $count,
            'list'  => $members
        ]);
    }
}