<?php
namespace app\common\command;


use app\common\service\TransactionBlock;
use Exception;
use Swoole\Event;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use Swoole\Timer;
use think\facade\Db;

class NumCe extends Command
{
    protected function configure()
    {
        $this->setName('NumCe')->setDescription("计划任务 扫描区块");
    }

    //调用SendMessage 这个类时,会自动运行execute方法
    protected function execute(Input $input, Output $output)
    {
        $output->writeln(date('Y-m-d h:i:s') . '任务开始!');
        /*** 这里写计划任务列表集 START ***/
        Timer::tick(1 * 1000, function () {
            var_dump(date('Y-m-d h:i:s'));
            $this->checkBlock();
        });
        /*** 这里写计划任务列表集sd END ***/
        $output->writeln(date('Y-m-d h:i:s') . '任务结束!');
        Event::wait();

    }

    public function checkBlock()
    {
        var_dump("轮训执行扫描项目:" . date('Y-m-d H:i:s'));
        //开启事务操作
        Db::startTrans();
        try {
            /*执行主体*/
            $TransactionBlock = new TransactionBlock();
            $TransactionBlock->setBlocks()->checkBlock();
            /*提交事务*/
            Db::commit();
        } catch (Exception $e) {
            /*回滚事务操作*/
            Db::rollback();
            var_dump('---------------------------------------------');
            var_dump($e->getMessage());
            var_dump($e->getTrace());
            var_dump('---------------------------------------------');

        }
    }


}
