<?php

namespace app\common\model;

use KaadonAdmin\baseCurd\Traits\Model\ModelCurd;

class MemberRecord extends TimeModel
{
    use ModelCurd;
    public static $ModelConfig = [
        'modelCache'       => '',
        'modelSchema'      => 'id',
        'modelDefaultData' => [],
    ];

    public function getBusinessTextAttr($value,$data)
    {
        $business = lang('business.' . $data['business']);
        return $business;
    }
    public function account()
    {
        return $this->belongsTo(MemberAccount::class, 'mid','id');
    }
    public function profile()
    {
        return $this->belongsTo(MemberProfile::class, 'mid','mid');
    }
    public function dashboard()
    {
        return $this->belongsTo(MemberDashboard::class, 'id', 'mid');
    }

    public static function getList($where = [], $page = 1, $limit = 10, $field = '', $order = [], $whereOr = false, $relation = null)
    {

        if (empty($order)) {
            $order = ['create_time' => 'desc'];
        }

        if ($whereOr) {
            $count = self::whereOr($where)
                ->count();

            $query = self::field($field)
                ->whereOr($where)
                ->page($page)
                ->limit($limit)
                ->order($order);
        } else {
            $count = self::where($where)
                ->count();
            $query = self::field($field)
                ->where($where)
                ->page($page)
                ->limit($limit)
                ->order($order);
        }

        $originalList = $query->select();
        $list         = $originalList->toArray();
        $business = lang('business');
        foreach ($list as &$item) {
            $item['business_text'] = $business[$item['business']];
        }
        if (!empty($relation)){
            foreach ($originalList as $key => $item) {
                if (is_string($relation)) {
                    $list[$key][$relation] = $item->$relation;
                }
                if (is_array($relation)) {
                    foreach ($relation as $vo) {
                        $list[$key][$vo] = $item->$vo;
                    }
                }
            }
        }

        $pages = ceil($count / $limit);


        $data['count'] = $count;
        $data['pages'] = $pages;
        $data['page']  = $page;
        $data['limit'] = $limit;
        $data['list']  = $list;

        return $data;
    }

}