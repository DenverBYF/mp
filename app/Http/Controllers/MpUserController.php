<?php

namespace App\Http\Controllers;

use App\MpUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Overtrue\LaravelWeChat\Facade as EasyWeChat;

class MpUserController extends Controller
{
    /**
     * user Login
     *
     * @param Request $request
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Illuminate\Http\JsonResponse|\Illuminate\Http\Response
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidConfigException
     */
    public function userLogin(Request $request)
    {
        if (!$request->has('code')) {
            Log::error("request no code");
            //不合法请求
            return response("no code", 400);
        }
        //获取请求code
        $code = $request->input('code');
        $mini = EasyWeChat::miniProgram();

        //换取微信用户session_key以及，openid;
        $res = $mini->auth->session($code);

        if (isset($res['errcode'])) {
            Log::error("get session_key and openid fail time:".time()." code: $code");
            //换取失败
            return response()->json([
                'ret_code' => $res['errcode'],
                'ret_msg' => $res['errmsg']
            ]);
        } else {
            //生成平台session
            $sessionKey = $res['session_key'];
            $openId = $res['openid'];
            //查询用户信息，新用户插入
            $user = MpUser::where('openid', $openId)->get(['id']);
            if ($user->isEmpty()) {
                $newUser = new MpUser();
                $newUser->openid = $openId;
                if(!$newUser->save()) {
                    Log::error("save new user fail openid: $openId");
                    return response("server busy", 500);
                }
                Log::info("create a new user");
                $user = MpUser::where('openid', $openId)->get(['id']);
            }
            $request->session()->put('mp_user_id', $user[0]->id);
            $request->session()->put('session_key', $sessionKey);
            $request->session()->put('openid', $openId);
            Log::info("user login ".$user[0]->id);
            return response()->json([
                'ret_code' => 0,
                'ret_msg' => 'success',
            ]);
        }
    }

    /**
     * set personal info
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function setting(Request $request)
    {
        $id = $request->session()->get('mp_user_id');

        if($request->isMethod('GET')) {
            $user = MpUser::find($id);
            return response()->json([
                'ret_code' => 0,
                'ret_msg' => 'succ',
                'data' => $user
            ]);
        }

        // 请求校验
        $validator = \Validator::make($request->all(), [
            'name' => 'required|max:10|bail',
            'phone' => 'required||bail',
            'address' => 'required|max:255|bail',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ret_code' => 1,
                'ret_msg' => '请正确填写个人信息表格',
            ]);
        }

        // 更新信息
        $user = MpUser::find($id);
        $user->phone = $request->get('phone');
        $user->name = $request->get('name');
        $user->address = $request->get('address');
        $user->email = $request->get('email');

        if ($user->save()) {
            Log::info("user setting success id:$id");
            return response()->json([
                'ret_code' => 0,
                'ret_msg' => 'success'
            ]);
        } else {
            Log::error("update user info fail id:$id data:".json_encode($request->all()));
            return response()->json([
                'ret_code' => 1,
                'ret_msg' => '更新失败，请重试'
            ]);
        }
    }
}
