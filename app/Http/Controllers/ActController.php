<?php

namespace App\Http\Controllers;

use EasyWeChat\Kernel\Support\Str;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Qcloud\Cos\Client;

class ActController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
        var_dump('asdasdasdasd');
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
            'rule_id' => 'required|integer'
        ]);

        if ($validator->fails()) {
            var_dump($validator->errors());
            Log::error("invalid input mpUserId: $mpUserId");
            return response()->json([
                'ret_code' => 400,
                'ret_msg' => '参数错误'
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
            DB::transaction(function () use ($request, $mpUserId, $msg) {
                // 礼品信息
                $giftId = DB::table('gifts')->insertGetId([
                    'name' => $request->gift_name,
                    'desc' => $request->gift_desc,
                    'url' => $msg,
                ]);

                // 活动信息
                DB::table('acts')->insert([
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
            }, 5);
        } catch (QueryException $e) {
            Log::error("insert database fail user: $mpUserId, error: $e");
            return response()->json([
                'ret_code' => 500,
                'ret_msg' => '创建失败'
            ]);
        }
        Log::info("create act success user: $mpUserId");

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
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
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

    }

    /**
     * get history acts data
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function history(Request $request)
    {

    }
}
