<?php

namespace App\Http\Controllers;

use App\Act;
use App\Gift;
use App\Jobs\ActJob;
use EasyWeChat\Kernel\Support\Str;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Qcloud\Cos\Client as Client;

class ActController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        // 用户id
        $mpUserId = $request->session()->get('mp_user_id');
        Log::info('act index begin id: '.$mpUserId);

        $resp = [];
        $acts = Act::take(20)->skip($request->get('offset') * 10)->orderby('open_time')->get();

        $retData = [];

        foreach ($acts as $act) {
            $resp['status'] = $act->status;
            if ($act->status !== 3) {
                $detailInfo = DB::table('act_user')->where('act_id', '=', $act->id)->where('mp_user_id', '=', $mpUserId)->select('id')->get();
                if ($detailInfo->isNotEmpty()) {
                    $resp['status'] = 2;
                }
            } else {
                $resultStatus = DB::table('act_user')->where('act_id', '=', $act->id)->where('mp_user_id', '=', $mpUserId)->select('status')->get();
                if ($resultStatus[0]->status === 1) {
                    $resp['status'] = 4;
                } else {
                    $resp['status'] = 5;
                }
            }
            $resp['title'] = $act->name;
            $resp['id'] = $act->id;
            $resp['date'] = $act->open_time;
            $resp['people'] = [
                'max' => $act->max_number,
                'gift' => $act->gift_number,
                'now' => $act->now_number
            ];
            $gift = Gift::find($act->gift_id);
            $resp['gift'] = [
                'id' => $gift->id,
                'url' => $gift->url,
                'name' => $gift->name
            ];

            array_push($retData, $resp);
        }
        return response()->json([
           'ret_code' => 0,
           'ret_data' => $retData
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // 用户id
        $mpUserId = $request->session()->get('mp_user_id');

        Log::info('act store begin mpUserId'. $mpUserId);

        // 参数检验
        $validator = Validator::make($request->all(), [
            'title' => 'required|max:50',
            'desc' => 'required|max:150',
            'max_number' => 'required|integer',
            'gift_number' => 'required|integer',
            'open_time' => 'required|integer',
            'pay_value' => 'required|integer',
            'gift' => 'required|file',
            'gift_name' => 'required|max:50',
            'gift_desc' => 'required|max:100',
            'rule_id' => 'required|integer',
            'form_id' => 'required'
        ]);

        if ($validator->fails()) {
            Log::error("invalid input mpUserId: $mpUserId");
            return response()->json([
                'ret_code' => 1,
                'ret_msg' => '参数错误'
            ]);
        }
        if ($request->open_time < time()) {
            return response()->json([
                'ret_code' => 1,
                'ret_msg' => '开奖时间必须晚于当前时间'
            ]);
        }

        $msg = '';   // 错误信息

        // 礼品图片处理,成功时返回0,msg成功时为图片url失败时为错误信息
        if ($this->imageParse($request->gift, $mpUserId, $msg)) {
            return response()->json([
                'ret_code' => 400,
                'ret_msg' => $msg
            ]);
        }

        // 事务处理入库
        try {
            Log::info('save act info to db '.$mpUserId);
            $actId = 0;
            DB::transaction(function () use ($request, $mpUserId, $msg, &$actId) {
                // 礼品信息
                $giftId = DB::table('gifts')->insertGetId([
                    'name' => $request->gift_name,
                    'desc' => $request->gift_desc,
                    'url' => $msg,
                ]);

                // 活动信息
                $actId = DB::table('acts')->insertGetId([
                    'name' => $request->title,
                    'desc' => $request->desc,
                    'max_number' => $request->max_number,
                    'mp_user_id' => $mpUserId,
                    'gift_number' => $request->gift_number,
                    'open_time' => date('Y-m-d H:i:s', $request->open_time),
                    'pay_value' => $request->pay_value,
                    'gift_id' => $giftId,
                    'rule_id' => $request->rule_id,
                ]);
                DB::table('act_user')->insert([
                    'act_id'=> $actId,
                    'mp_user_id' => $mpUserId,
                    'form_id' => $request->form_id
                ]);
            }, 5);
        } catch (QueryException $e) {
            Log::error("insert database fail user: $mpUserId, error: $e");
            return response()->json([
                'ret_code' => 500,
                'ret_msg' => '创建失败'
            ]);
        }
        Log::info("create act success user: $mpUserId; open_time: $request->open_time");

        // 入队
        ActJob::dispatch($actId)->delay(now()->addSecond($request->open_time - time()));

        return response()->json([
            'ret_code' => 0,
            'ret_msg' => '创建成功'
        ]);

    }

    /**
     * 礼品图片处理,安全校验,上传腾讯云
     * @param $image $mpUserId &$errMsg
     * @return integer
     */
    private function imageParse($image, $mpUserId, &$errMsg)
    {
        // 类型校验
        $extension = $image->extension();
        $extensionArray = ['jpg', 'jpeg', 'png', 'gif'];
        if (!in_array($extension, $extensionArray)) {
            Log::error("file type error userId:$mpUserId type:$extension");
            $errMsg = '图片类型错误(只可上传jpg,png.gif格式图片)';
            return 1;
        }

        // 重命名 sha1(时间戳+用户id+10位随机字符串)
        $fileName = sha1(time().$mpUserId.Str::random(10));
        $fileName = $fileName.'.'.$extension;

        // 腾讯云COS初始化
        $cosClient = new Client(array(
            'region' => env('COS_REGION'),
            'credentials' => array(
                'appId' => env('COS_APP'),
                'secretId' => env('COS_ID'),
                'secretKey' => env('COS_KEY')
            )
        ));

        // 上传图片
        try {
            $res = $cosClient->putObject(array(
                'Bucket' => env('COS_BUCKET'),
                'Key' => $fileName,
                'Body' => fopen($image->path(), 'rb'),
            ));
            $errMsg = $res['ObjectURL'];
        } catch (\Exception $e) {
            Log::error('upload pic to ten cloud fail mpUserId:'.$mpUserId);
            $errMsg = '图片上传失败，请稍后重试';
            return 1;
        }

        return 0;
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
        $resp = [];
        $act = Act::find($id);
        $gift = Act::find($id)->Gift;
        $user = Act::find($id)->User;
        $resp['title'] = $act->name;
        $resp['status'] = $act->status;
        $resp['desc'] = $act->desc;
        $resp['people'] = [
            'max' => $act->max_number,
            'gift' => $act->gift_number,
            'now' => $act->now_number
        ];
        $resp['gift'] = [
            'id' => $gift->id,
            'name' => $gift->name,
            'url' => $gift->url,
            'desc' => $gift->desc
        ];
        $resp['user'] = [
            'id' => $user->id,
            'name' => $user->name
        ];
        return response()->json([
            'ret_code' => 0,
            'ret_data' => $resp
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }

    /**
     * get acts created by user
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function acts(Request $request)
    {
        // 用户id
        $mpUserId = $request->session()->get('mp_user_id');

        $resp = [];

        $retData = [];

        $acts = Act::where('mp_user_id', $mpUserId)->take(10)->skip($request->get('offset')*10)->get();

        foreach ($acts as $act) {
            $resp['status'] = $act->status;
            if ($act->status !== 3) {
                $detailInfo = DB::table('act_user')->where('act_id', '=', $act->id)->where('mp_user_id', '=', $mpUserId)->select('id')->get();
                if ($detailInfo->isNotEmpty()) {
                    $resp['status'] = 2;
                }
            } else {
                $resultStatus = DB::table('act_user')->where('act_id', '=', $act->id)->where('mp_user_id', '=', $mpUserId)->select('status')->get();
                if ($resultStatus[0]->status === 1) {
                    $resp['status'] = 4;
                } else {
                    $resp['status'] = 5;
                }
            }
            $resp['title'] = $act->name;
            $resp['id'] = $act->id;
            $resp['date'] = $act->open_time;
            $resp['people'] = [
                'max' => $act->max_number,
                'gift' => $act->gift_number,
                'now' => $act->now_number
            ];
            $gift = Gift::find($act->gift_id);
            $resp['gift'] = [
                'id' => $gift->id,
                'url' => $gift->url,
                'name' => $gift->name
            ];

            array_push($retData, $resp);
        }

        return response()->json([
            'ret_code' => 0,
            'ret_data' => $retData
        ]);
    }

    /**
     * get history acts data
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function history(Request $request)
    {
        $mpUserId = $request->session()->get('mp_user_id');

        $resp = [];

        $retData = [];

        $acts = DB::table('act_user')->join('acts', 'act_user.act_id', '=', 'acts.id')->where('act_user.mp_user_id', '=', $mpUserId)->get();
        // var_dump($acts);
        foreach ($acts as $act) {
            $resp['status'] = $act->status;
            if ($act->status !== 3) {
                $detailInfo = DB::table('act_user')->where('act_id', '=', $act->id)->where('mp_user_id', '=', $mpUserId)->select('id')->get();
                if ($detailInfo->isNotEmpty()) {
                    $resp['status'] = 2;
                }
            } else {
                $resultStatus = DB::table('act_user')->where('act_id', '=', $act->id)->where('mp_user_id', '=', $mpUserId)->select('status')->get();
                if ($resultStatus[0]->status === 1) {
                    $resp['status'] = 4;
                } else {
                    $resp['status'] = 5;
                }
            }
            $resp['title'] = $act->name;
            $resp['id'] = $act->id;
            $resp['date'] = $act->open_time;
            $resp['people'] = [
                'max' => $act->max_number,
                'gift' => $act->gift_number,
                'now' => $act->now_number
            ];
            $gift = Gift::find($act->gift_id);
            $resp['gift'] = [
                'id' => $gift->id,
                'url' => $gift->url,
                'name' => $gift->name
            ];

            array_push($retData, $resp);
        }

        return response()->json([
            'ret_code' => 0,
            'ret_data' => $retData
        ]);
    }

    /**
     * join the act
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function join(Request $request)
    {
        $mpUserId = $request->session()->get('mp_user_id');
        $actId = $request->act;

        // 入库数据
        try {
            DB::transaction(function () use($mpUserId, $actId, $request) {
                $detailInfo = DB::table('act_user')->where('act_id', '=', $actId)->where('mp_user_id', '=', $mpUserId)->select('id')->get();
                if ($detailInfo->isNotEmpty()) {
                    Log::info('people in this act user:'.$mpUserId.' act:'.$actId);
                    throw (new \Exception('in'));
                }
                // 获取当前参与人数 使用悲观锁，保证人数不会被并发乱序更新
                $actInfo = DB::table('acts')->where('id', '=', $actId)->select('max_number', 'now_number', 'open_time')->lockForUpdate()->get();
                if ($actInfo[0]->max_number <= $actInfo[0]->now_number) {
                    Log::info('act people is full user: '.$mpUserId.' act: '.$actId);
                    throw (new \Exception('full'));
                }
                if (strtotime($actInfo[0]->open_time) < time()) {
                    throw (new \Exception('over'));
                }
                // 加入详情表
                DB::table('act_user')->insert([
                    'act_id' => $actId,
                    'mp_user_id' => $mpUserId,
                    'form_id' => $request->form_id
                ]);
                // 更新当前人数
                DB::table('acts')->where('id', '=', $actId)->update([
                    'now_number' => $actInfo[0]->now_number + 1
                ]);
                // 人数满时，修改状态
                if ($actInfo[0]->max_number === $actInfo[0]->now_number + 1) {
                    DB::table('acts')->where('id', '=', $actId)->update([
                        'status' => 1
                    ]);
                }
            }, 5);
        } catch (QueryException $e) {
            Log::error('join act fail user: '.$mpUserId.' act: '.$actId.' error: '.$e);
            return response()->json([
                'ret_code' => 1,
                'ret_msg' => '系统繁忙 请稍后再试'
            ]);
        } catch (\Exception $e) {
            if ($e->getMessage() === 'full') {
                return response()->json([
                    'ret_code' => 1,
                    'ret_msg' => '当前活动人数已满'
                ]);
            }
            if ($e->getMessage() === 'in') {
                return response()->json([
                    'ret_code' => 1,
                    'ret_msg' => '您已经参加了此次活动'
                ]);
            }
            if ($e->getMessage() === 'over') {
                return response()->json([
                    'ret_code' => 1,
                    'ret_msg' => '该活动已过期'
                ]);
            }
        }
        return response()->json([
            'ret_code' => 0,
            'ret_msg' => '参与成功'
        ]);
    }
}
