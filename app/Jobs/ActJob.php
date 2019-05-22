<?php

namespace App\Jobs;

use App\Act;
use App\Gift;
use App\MpUser;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Providers\GenActResult;
use App\Providers\Rules;
use Overtrue\LaravelWeChat\Facade as EasyWeChat;

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
        // 奖品信息
        $gift = Gift::find($act->gift_id);
        $ruleId = $act->rule_id;
        $nowNumber = $act->now_number;
        $giftNumber = $act->gift_number;
        // 根据规则id获取中间结果生成器
        switch ($ruleId) {
            case 1:
                // 系统默认随机生成
                $genResultHandle = new GenActResult(new Rules\SystemRule());
                break;
            case 2:
                $genResultHandle = new GenActResult(new Rules\LotteryRule());
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
        $mini = EasyWeChat::miniProgram();
        foreach ($result as $item) {
            Log::info("act $this->id open result get id is $item user id is  {$joinNumbers[$item]->mp_user_id}");
            DB::table('act_user')->where('act_id', '=', $this->id)->where('mp_user_id', '=', $joinNumbers[$item]->mp_user_id)->update(['status' => 1]);
            $formId = DB::table('act_user')->where('act_id', '=', $this->id)->where('mp_user_id', '=', $joinNumbers[$item]->mp_user_id)->select('form_id')->get();
            $userInfo = MpUser::find($joinNumbers[$item]->mp_user_id);
            Log::info("form id {$formId[0]->form_id}");
            // 发送模版消息
            $mini->template_message->send([
                'touser' => $userInfo->openid,
                'template_id' => 'AnqUFpOa1wITPKjiEiU2oGTEtXECgOzeuWz4nKhlBpA',
                'page' => 'pages/my/index',
                'form_id' => $formId[0]->form_id,
                'data' => [
                    'keyword1' => '恭喜你中奖啦',
                    'keyword2' => $gift->name,
                    'keyword3' => date('Y-m-d', time()),
                    'keyword4' => $act->name,
                    'keyword5' => $userInfo->name,
                    'keyword6' => $userInfo->address
                ]
            ]);
        }
        // 未中奖用户
        $restNumber = DB::table('act_user')->where('act_id', '=', $this->id)->where('status', '=', 0)->select('mp_user_id')->get();
        foreach ($restNumber as $eachUser) {
            $formId = DB::table('act_user')->where('act_id', '=', $this->id)->where('mp_user_id', '=', $eachUser->mp_user_id)->select('form_id')->get();
            $userInfo = MpUser::find($eachUser->mp_user_id);
            $mini->template_message->send([
                'touser' => $userInfo->openid,
                'template_id' => 'AnqUFpOa1wITPKjiEiU2oEuhmZuk7tBEaK2d867wXUY',
                'page' => 'pages/act/index',
                'form_id' => $formId[0]->form_id,
                'data' => [
                    'keyword1' => '很遗憾你没能中奖，祝你下次好运！',
                    'keyword2' => $act->name,
                    'keyword3' => $userInfo->name
                ]
            ]);
        }
        // 修改活动状态为已开奖
        $act->status = 3;
        $act->save();
    }
}
