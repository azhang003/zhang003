<?php

namespace app\common\controller\merchant;

use app\common\controller\MerchantController;
use app\common\model\MerchantAccount;
use app\common\model\MerchantDash;
use app\common\model\MerchantDashboard;
use app\common\model\MerchantDashDay;
use app\common\model\MerchantIndex;
use app\common\model\MerchantProfile;
use app\common\model\MerchantWallet;
use app\common\service\Uuids;
use think\Exception;
use think\facade\Db;

class Account extends MerchantController
{
    public function __construct()
    {
        
    }

    public function create($mobile,$password,$merchant = 0)
    {
        // 启动事务
        Db::startTrans();
        try {
            //添加账户
            $MerchantAccount = new MerchantAccount();
            $account = [
                'uuid' => Uuids::getUserInvite(3,9),
                'password' => password_hash($password,PASSWORD_DEFAULT),
                'safeword' => password_hash($password,PASSWORD_DEFAULT),
                'agent' => 0,
                'agent_line' => '0|'
            ];
            if ($merchant != 0){
               $Accountmerchant = MerchantAccount::find($merchant);
                $account['agent_line'] = $Accountmerchant->agent_line . $Accountmerchant->id . '|';
                $account['agent'] = count(explode('|',$account['agent_line'])) - 2;
                MerchantDash::whereIn('uid',agent_line_array($account['agent_line']))->update([
                    'agent_people' =>  Db::raw('agent_people+1')
                ]);
            }

            $MerchantAccount->save($account);

            //添加钱包
            $MerchantWallet = new MerchantWallet();
            $wallet = [
                'uid' => $MerchantAccount->id,
            ];
            $MerchantWallet->save($wallet);
            //添加资料
            $MerchantProfile = new MerchantProfile();
            $profile = [
                'uid' => $MerchantAccount->id,
                'mobile' => $mobile
            ];
            $MerchantProfile->save($profile);
            //添加仪表盘
            $MerchantDashboard = new MerchantDashboard();
            $dashboard = [
                'uid' => $MerchantAccount->id,
            ];
            $MerchantDashboard->save($dashboard);

            //添加MerchantDashDay
            $MerchantDashDay = new MerchantDashDay();
            $MerchantDashDayData = [
                'uid' => $MerchantAccount->id,
                'date' => date("Y-m-d"),
            ];
            $MerchantDashDay->save($MerchantDashDayData);
            //添加仪表盘DASH
            $MerchantDash = new MerchantDash();
            $MerchantDashData = [
                'uid' => $MerchantAccount->id,
            ];
            $MerchantDash->save($MerchantDashData);

            //添加统计
            $MerchantIndex = new MerchantIndex();
            $merchantindexData = [
                'uid' => $MerchantAccount->id,
            ];
            $MerchantIndex->save($merchantindexData);


            // 提交事务
            Db::commit();
        } catch (\Exception $e) {
            // 回滚事务
            var_dump($e);
            Db::rollback();
            return false;
        }
        return true;
    }
}