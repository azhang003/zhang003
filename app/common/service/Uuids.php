<?php

namespace app\common\service;



use app\common\model\SystemUuid;

class Uuids extends \Kaadon\Uuid\Uuids
{
    public static function getUuid(int $type = 1){
        $prefix = chr(mt_rand(65, 90));
        do {
            $number = mt_rand(100000000, 999999999);
            $uid    = $prefix . $number;
        } while (!empty(SystemUuid::where('uuid', '=', $uid)->find()));
        $bool = SystemUuid::create([
            'type' => $type,
            'uuid' => $uid,
        ]);
        if (empty($bool)) {
            throw new \Exception("Sorry, the account number generation failed!");
        }
        return $uid;
    }
    public static function getUuids(int $type = 1){
        $prefix = chr(mt_rand(65, 90));
        do {
            $number = mt_rand(10000, 99999);
            $uid    = $prefix . $number;
        } while (!empty(SystemUuid::where('uuid', '=', $uid)->find()));
        $bool = SystemUuid::create([
            'type' => $type,
            'uuid' => $uid,
        ]);
        if (empty($bool)) {
            throw new \Exception("Sorry, the account number generation failed!");
        }
        return $uid;
    }

    public static function createRandomStr($length){

        $str = array_merge(range(0,9),range('a','z'),range('A','Z'));

        shuffle($str);

        $str = implode('',array_slice($str,0,$length));

        return $str;

    }
    public static function getUserInvite(int $type = 2,$num = 10){
        $prefix = chr(mt_rand(65, 90));
        do {
            $uid    = $prefix . self::createRandomStr($num - 1);
        } while (!empty(SystemUuid::where([['uuid', '=', $uid],['type','=',$type]])->find()));
        $bool = SystemUuid::create([
            'type' => $type,
            'uuid' => $uid,
        ]);
        if (empty($bool)) {
            throw new \Exception("Sorry, the account number generation failed");
        }
        return $uid;
    }
}