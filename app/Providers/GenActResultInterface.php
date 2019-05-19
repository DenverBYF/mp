<?php
/**
 * Created by PhpStorm.
 * User: baiyifan
 * Date: 2019-05-19
 * Time: 11:07
 */

namespace App\Providers;

interface GenActResultInterface {
    // 生成抽奖结果
    public function getResult($total, $number);
}