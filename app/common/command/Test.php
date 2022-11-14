<?php

namespace app\common\command;


use app\admin\controller\merchant\Merchant;
use app\common\controller\member\Wallet;
use app\common\model\MemberAccount;
use app\common\model\MemberDash;
use app\common\model\MemberDashDay;
use app\common\model\MemberRecord;
use app\common\model\MemberShare;
use app\common\model\MemberShareDay;
use app\common\model\MemberWallet;
use app\common\model\MerchantAccount;
use app\common\model\MerchantDash;
use app\common\model\MerchantDashDay;
use Exception;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;


class Test extends Command
{
    protected function configure()
    {
        $this->setName('red')->setDescription("计划任务 每日复原虚拟账户");
    }

    public static $teamType
        = [
            1 => "one",
            2 => "two",
            3 => "three"
        ];

    //调用SendMessage 这个类时,会自动运行execute方法
    protected function execute(Input $input, Output $output)
    {
        $output->writeln(date('Y-m-d h:i:s') . '任务开始!');
        redisCacheAuto("WithDraw:" . date("Ymd") . ":" . 98569);
        event('MemberWithDraw',['mid' => '98569','money' => '3','free' => '0.18']);
//            MemberWallet::where('mid','98603')->update([
//                'cny_back' => Db::raw("(cny+1)/btc")
//            ]);
        /*** 这里写计划任务列表集 START ***/
//        $account = MemberAccount::select();
        // 启动事务
//        Db::startTrans();
//        try {
//            foreach ($account as $item) {
//                if (empty(MemberShare::where(['mid' => $item->id])->find())){
//                    MemberShare::create(['mid' => $item->id]);
//                }
//                if (empty(MemberShareDay::where(['mid' => $item->id,'date' => date('Y-m-d')])->find())){
//                    MemberShareDay::create(['mid' => $item->id,'date' => date('Y-m-d')]);
//                }
//                if (empty(MemberDash::where(['mid' => $item->id])->find())){
//                    $bool= MemberDash::create(['mid' => $item->id]);
//                }
//                if (empty(MemberDashDay::where(['mid' => $item->id,'date' => date('Y-m-d')])->find())){
//                    MemberDashDay::create(['mid' => $item->id,'date' => date('Y-m-d')]);
//                }
//            }
//            $Merchant = MerchantAccount::select();
//            foreach ($Merchant as $item) {
//                if (empty(MerchantDash::where(['uid' => $item->id])->find())){
//                    MerchantDash::create(['uid' => $item->id]);
//                }
//                if (empty(MerchantDashDay::where(['uid' => $item->id,'date' => date('Y-m-d')])->find())){
//                    MerchantDashDay::create(['uid' => $item->id,'date' => date('Y-m-d')]);
//                }
//            }
//            // 提交事务
//            Db::commit();
//        } catch (\Exception $e) {
//            // 回滚事务
//            Db::rollback();
//            var_dump($e->getMessage());
//        }

        /*** 这里写计划任务列表集 END ***/
        $output->writeln(date('Y-m-d h:i:s') . '任务结束!');
    }
}