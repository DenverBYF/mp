<?php

namespace App\Http\Controllers;

use App\MpUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Overtrue\LaravelWeChat\Facade as EasyWeChat;

class MpUserController extends Controller
{
    //
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
            $user = MpUser::where('openid', $openId)->get();
            if ($user->isEmpty()) {
                var_dump('asdasdasd');
                //todo 插入用户
                $newUser = new MpUser();
                $newUser->openid = $openId;
                if(!$newUser->save()) {
                    Log::error("save new user fail openid: $openId");
                    return response("server busy", 500);
                }
                Log::info("create a new user");
            }
            $request->session()->put('session_key', $sessionKey);
            $request->session()->put('openid', $openId);
            return response()->json([
                'ret_code' => 0,
                'ret_msg' => 'success',
            ]);
        }
    }
}
