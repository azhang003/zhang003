<?php

namespace app\common\controller\member;

use app\common\controller\MemberController;
use app\common\model\MemberAccount;
use app\common\model\MemberAddress;
use app\common\model\MemberDash;
use app\common\model\MemberDashboard;
use app\common\model\MemberDashDay;
use app\common\model\MemberIndex;
use app\common\model\MemberProfile;
use app\common\model\MemberShare;
use app\common\model\MemberShareDay;
use app\common\model\MemberTeam;
use app\common\model\MemberWallet;
use app\common\model\MerchantAccount;
use app\common\model\MerchantIndex;
use app\common\model\MerchantProfile;
use app\common\service\Uuids;
use think\facade\Db;
use Usdtcloud\TronService\Credential;

class Account extends MemberController
{
    public function __construct()
    {

    }

    public function add($username, $password, $inviter = null, $analog = 0, $nickname = null, $safeword = null, $ip = null, $ad = null)
    {
        $data = [];
        if (empty($inviter)) {
            $inviter = get_config('site', 'setting', 'inviter');
        }
        $agent = MemberAccount::where('uuid', $inviter)->find();

        if (empty($agent)) {
            $agent = MerchantAccount::where('uuid', $inviter)->find();
            if (empty($agent)) {
                return false;
            } else {
                $floor = 0;
                $agent_line = $agent->agent_line . $agent->id . '|';
            }
        } else {
            $floor = $agent->floor + 1;
            $agent_id     = $agent->id;
            $agent_line   = $agent->agent_line;
            $inviter_line = $agent->inviter_line . $agent->id . '|';
        }
        $data['floor']        = $floor;
        $data['agent_line']   = $agent_line;
        $data['inviter_line'] = empty($inviter_line) ? "0|" : $inviter_line;
        $data['login_ip'] = jwt_realIp()?:'0.0.0.0';
        $data['register_ip'] = $data['login_ip'];
        $data['uuid']         = Uuids::getUserInvite(2);
        $data['analog']       = $analog;
        $data['password']     = password_hash($password, PASSWORD_DEFAULT);
        $data['safeword']     = empty($safeword) ? password_hash(733333, PASSWORD_DEFAULT) : password_hash($safeword, PASSWORD_DEFAULT);
        if ($inviter) {
            $data['inviter'] = $inviter;
        }
        // 启动事务
        Db::startTrans();
        try {
            $agent_line = explode('|', $agent_line);
            //添加账户
            $MemberAccount = new MemberAccount();
            $MemberAccount->save($data);
            //添加钱包
            $MemberWallet = new MemberWallet();
            $wallet       = [
                'mid' => $MemberAccount->id,
            ];
            $MemberWallet->save($wallet);
            //添加资料
            $MemberProfile = new MemberProfile();
            $profile       = [
                'mid'      => $MemberAccount->id,
                'mobile'   => $username,
                'nickname' => empty($nickname) ? $data['uuid'] : $nickname
            ];
            $MemberProfile->save($profile);
            //添加仪表盘
            $MemberDashboard = new MemberDashboard();
            $dashboard       = [
                'mid' => $MemberAccount->id,
            ];
            $MemberDashboard->save($dashboard);
            //添加仪表盘
            $MemberShare = new MemberShare();
            $MemberShareData       = [
                'mid' => $MemberAccount->id,
            ];
            $MemberShare->save($MemberShareData);
            //添加仪表盘
            $MemberDash = new MemberDash();
            $MemberDashData       = [
                'mid' => $MemberAccount->id,
            ];
            $MemberDash->save($MemberDashData);
            //添加仪表盘
            $MemberDashDay = new MemberDashDay();
            $MemberDashDayData       = [
                'mid' => $MemberAccount->id,
            ];
            $MemberDashDay->save($MemberDashDayData);
            //添加仪表盘
            $MemberTeam = new MemberTeam();
            $MemberTeamData       = [
                'mid' => $MemberAccount->id,
            ];
            $MemberTeam->save($MemberTeamData);
            //添加地址
            $Credential             = Credential::create();
            $address['trc_address'] = $Credential->address()->base58();
            $address['trc20_pb']    = $Credential->publicKey();
            $address['trc20_pv']    = $Credential->privateKey();
            $address['mid']         = $MemberAccount->id;
            $address['create_time'] = $address['update_time'] = time();
            MemberAddress::insert($address); // 保存到user表中
            $Index['mid']              = $MemberAccount->id;
            $Index['agent_id']         = empty($agent_id) ? 0 : $agent_id;
            $Index['register_ip']      = $ip ?: 0;
            $Index['register_address'] = $ad ?: 0;
            $Index['agent']            = MerchantProfile::where([['uid', '=', $agent_line[count($agent_line) - 2]]])->value('mobile') ?: 0;
            $Index['allagent']         = MerchantProfile::where([['uid', '=', $agent_line[1]]])->value('mobile') ?: 0;
            $Index['create_time']      = $Index['update_time'] = time();
            MemberIndex::insert($Index); // 保存到user表中
            // 提交事务
            Db::commit();
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            return false;
        }
        event('MemberRegister', $MemberAccount);
        return true;
    }



    /**
     * @param string $cardid
     * @param array $meta
     * @return mixed
     * @throws \Exception
     * 增加团队人数
     *
     */
    public function agent($mid, $type = 1)
    {
        $account = MerchantAccount::where('id', $mid)->find();
        if (!empty($account)) {
//            if ($type < 4){
//                switch ($type){
//                    case 1:
//                        MemberTeam::where([
//                            'mid'=>$mid
//                        ])->inc('first')
//                            ->update();
//                        break;
//                    case 2:
//                        MemberTeam::where([
//                            'mid'=>$mid
//                        ])->inc('second')
//                            ->update();
//                        break;
//                    case 3:
//                        MemberTeam::where([
//                            'mid'=>$mid
//                        ])->inc('third')
//                            ->update();
//                        break;
//                }
//            }
            MerchantIndex::where([
                'uid' => $mid
            ])->inc('user')
                ->update();
            $inviter_line = explode('|', $account->agent_line);
            $count        = count($inviter_line);
            if ($count > 2) {
                $this->inviters($inviter_line[$count - 2], ++$type);
            }
        }
    }

    public function update(string $cardid, array $meta)
    {
        if (empty($cardid)) {
            throw new \Exception('客服ID不能为空');
        }
        if (empty($meta)) {
            throw new \Exception('无更新数据');
        }

        $Customer = MemberAccount::getInfo($cardid);

        if (empty($Customer)) {
            throw new \Exception('客服不存在!');
        }

        $data = [];
        $arr  = ['status', 'status', 'nickname', "cardid", "password"];
        foreach ($arr as $item) {
            if (in_array($item, $meta)) {
                if ($item == "password") {
                    $data[$item] = password_hash($meta[$item], PASSWORD_DEFAULT);
                } else {
                    $data[$item] = $meta[$item];
                }
            }
        }

        $result = CustomerAccount::setUpdate(['cardid' => $cardid], $data);

        return $result;
    }

    /**
     * 设置缓存
     * @param array $account
     * @param array $profile
     * @return bool
     */
    public static function setMemberCache(array $account, array $profile): bool
    {
        if (array_key_exists("password",$account)){
            unset($account['password']);
        }
        if (array_key_exists("safeword",$account)){
            unset($account['safeword']);
        }
        $member = [
            'profile' => $profile,
            'account' => $account
        ];
        return redisCacheSet('profile:' . $profile['mid'], $member);
    }

    /**
     *获取用户信息并缓存
     * @param $mid
     * @return array|false|mixed|string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function MemberCache($mid = null): array
    {
        if (empty($mid)) {
            return [];
        }
        $member = redisCacheGet('profile:' . $mid);
        if (empty($member)) {
            $MP = MemberProfile::where('mid', $mid)->find();
            if (empty($MP)) {
                return [];
            }
            $member = [
                'profile' => $MP->toArray(),
                'account' => $MP->account->toArray()
            ];
            unset($member['account']['password']);
            unset($member['account']['safeword']);
            redisCacheSet('profile:' . $mid, $member);
        }
        return $member;
    }

    /**
     * 获取会员信息
     * @param $mid
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function member_account($mid = null): array
    {
        $MemberCache = self::MemberCache($mid);
        if (empty($MemberCache)) {
            return [];
        }
        $data                 = $MemberCache['account'];
        $data['inviter_line'] = agent_line_array($data['inviter_line']);
        $data['agent_line']   = agent_line_array($data['agent_line']);
        $data['is_super']     = $data['level'] == 4 ? true : false;
        return $data;
    }

    /**
     * @param $mid
     * @return array|false|mixed|string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function inviter_line($mid = null): array
    {
        $MemberCache = self::MemberCache($mid);
        if (empty($MemberCache)) {
            return [];
        }
        return agent_line_array($MemberCache['account']['inviter_line']);
    }

    /**
     * @param $mid
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function agent_line($mid = null): array
    {
        $MemberCache = self::MemberCache($mid);
        if (empty($MemberCache)) {
            return [];
        }
        return agent_line_array($MemberCache['account']['agent_line']);
    }

    /**
     * 删除缓存
     * @param $mid
     * @return array|false
     */
    public static function delMemberCache($mid = null): bool
    {
        if (empty($mid)) {
            return false;
        }
        return redisCacheDel('profile:' . $mid);
    }

    /**
     * 计算留存率
     * @param $type 'one,three,seven,fourteen,moon'
     * @return void
     */
//    public static function keep($create_time)
//    {
//        $keepArr = [
//            "one",
//            "three",
//            "seven",
//            "fourteen",
//            "moon"
//        ];
//    }
}