<?php
/**
 * Created by PhpStorm.
 * User: baiyifan
 * Date: 2019-05-19
 * Time: 11:17
 */

namespace App\Providers\Rules;
use App\Providers\GenActResultInterface;


class SystemRule implements GenActResultInterface
{
    public function getResult($total, $number)
    {
        // 系统生成中奖结果
        $ret = [];
        for ($i = 0; $i < $number; $i ++) {
            $tmpValue = mt_rand(0, $total);
            while (in_array($tmpValue, $ret)) {
                $tmpValue = mt_rand(0, $total);
            }
            $ret[] = $tmpValue;
        }

        return $ret;
    }
}