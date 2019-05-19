<?php

namespace App\Jobs;

use App\Act;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Providers\GenActResult;
use App\Providers\GenActResultInterface;
use App\Providers\Rules;

class ActJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $id;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($id)
    {
        // 活动主键id
        $this->id = $id;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // 开奖处理
        Log::info('laravel worker to open gift id: '.$this->id);
        // 获取活动信息
        $act = Act::find($this->id);
        $ruleId = $act->rule_id;
        $nowNumber = $act->now_number;
        $giftNumber = $act->gift_number;
        // 根据规则id获取中间结果生成器
        switch ($ruleId) {
            case 1:
                // 系统默认随机生成
                $genResultHandle = new GenActResult(new Rules\SystemRule());
                break;
            default:
                $genResultHandle = new GenActResult(new Rules\SystemRule());
                break;
        }
        // 获取参与用户信息
        $joinNumbers = DB::table('act_user')->where('act_id', '=', $this->id)->select('mp_user_id')->get();
        // 生成中奖结果
        $result = $genResultHandle->handle($nowNumber, $giftNumber);
        // 结果入库，模版消息推送
        foreach ($result as $item) {
            Log::info("act $this->id open result get $item");
            DB::table('act_user')->where('act_id', '=', $this->id)->where('mp_user_id', '=', $joinNumbers[$item])->update(['status' => 1]);
            // todo 发送模版消息
        }
    }
}
