<?php

namespace app\merchant\controller;

use app\common\model\MemberAccount;
use app\merchant\BaseMerchant;
use app\merchant\middleware\jwtVerification;
use think\facade\Db;
use think\response\Json;

class Dash extends BaseMerchant
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
    public function TotalDayStatistics(): Json
    {
        $agent   = $this->request->param('agent', 0);
        $page  = $this->request->param('page', 1);
        $limit = $this->request->param('limit', 10);
        $type  = $this->request->param('type', 1);
        $query = Db::table('ea_merchant_account')
            ->alias('a')
            ->join('ea_merchant_dash_day d', 'a.id = d.uid')
            ->join('ea_merchant_profile p', 'a.id = p.uid');
        if (empty($agent) || $agent == 1) {
            $where = [['a.id', '=', $this->request->merchant->id]];
        } else {
            $where = [['a.agent_line', 'like', '%|' . $this->request->merchant->id . '|%'], ['a.agent', '=', $agent - 1]];
        }
        $query->where($where);
        $count   = $query->count();
        $members = $query
            ->Field('d.*,p.mobile AS mobile,a.agent, a.uuid,a.agent_line')
            ->page($page)
            ->limit($limit)
//            ->order('s.date DESC')
                ->order('d.date DESC, a.id ASC')
            ->select();
        return success([
            "pages" => ceil($count / $limit),
            "page"  => $page,
            "limit" => $limit,
            "count" => $count,
            'list'  => $members
        ]);
    }

    public function TotalStatistics(): Json
    {
        $page  = $this->request->param('page', 1);
        $limit = $this->request->param('limit', 10);
        $query = Db::table('ea_merchant_account')
            ->alias('a')
            ->join('ea_merchant_dash d', 'a.id = d.uid')
            ->join('ea_merchant_profile p', 'a.id = p.uid');
        $where = [['a.id', '=', $this->request->merchant->id]];
        $whereOr = [['a.agent_line', 'like', '%|' . $this->request->merchant->id . '|%']];
        $query->where($where);
        $query->whereOr($whereOr);
        $count   = $query->count();
        $members = $query
            ->Field('d.*,p.mobile AS mobile,p.nickname,a.agent, a.uuid,a.agent_line,a.agent')
            ->page($page)
            ->limit($limit)
//            ->order('s.date DESC')
            ->order('a.agent ASC')
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