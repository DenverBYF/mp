<?php
/**
 * Created by PhpStorm.
 * User: baiyifan
 * Date: 2019-05-22
 * Time: 18:26
 */

namespace App\Providers\Rules;
use App\Providers\GenActResultInterface;
use Illuminate\Support\Facades\Log;


class LotteryRule implements GenActResultInterface
{
    public function getResult($total, $number)
    {
        // 根据彩票结果生成
        $idArray = [];
        for ($i = 0; $i < $total; $i ++) {
            $tmp = '';
            for ($j = 0; $j < 5; $j ++) {
                $tmp .= strval(mt_rand(0, 9));
            }
            $idArray[] = $tmp;
        }
        // 获取抽奖结果
        $url = 'http://apis.juhe.cn/lottery/query';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 5.1) AppleWebKit/537.22 (KHTML, like Gecko) Chrome/25.0.1364.172 Safari/537.22');
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 12);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_URL, $url.'?lottery_id=plw&lottery_no=&key=795bf27afc171696124b377ec7c9a8fc');
        $res = curl_exec($ch);
        Log::info("http request get res $res");
        $res = json_decode($res);
        $lotteryResult = $res->result->lottery_res;
        $lotteryResult = intval(str_replace(",", "", $lotteryResult));

        $resultIndex = [];
        $resultCopy = [];
        for ($i = 0; $i < $total; $i ++) {
            $resultIndex[] = abs($idArray[$i] - $lotteryResult);
            $resultCopy[] = abs($idArray[$i] - $lotteryResult);
        }
        $resultCopy = array_unique($resultCopy);
        $resultIndex = array_unique($resultIndex);

        sort($resultIndex);

        $result = [];
        for ($i = 0; $i < $number; $i ++) {
            $result[] = array_search($resultIndex[$i], $resultCopy);
        }

        return $result;
    }

}