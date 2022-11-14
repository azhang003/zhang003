<?php

namespace app\common\command;


use app\common\controller\member\Wallet;
use app\common\model\MemberAccount;
use app\common\model\MemberRecord;
use app\common\model\MemberShare;
use app\common\model\MemberWallet;
use Exception;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;


class SystemTest extends Command
{
    protected function configure()
    {
        $this->setName('SystemTest')->setDescription("计划任务 每日复原虚拟账户");
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
        /*** 这里写计划任务列表集 START ***/
            var_dump(date("Y-m-d H:i:s"));
        /*** 这里写计划任务列表集 END ***/
        $output->writeln(date('Y-m-d h:i:s') . '任务结束!');
    }
}