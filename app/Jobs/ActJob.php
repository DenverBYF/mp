<?php

namespace App\Jobs;

use App\Act;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;

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
        $act = Act::find($this->id);
        // todo:获取第三方开奖结果信息
        // todo:开奖结果入库
        // todo:发送模版消息
    }
}
