<?php

namespace App\Http\Controllers;

use App\MpUser;
use Illuminate\Http\Request;

class TestController extends Controller
{
    //
    public function index(Request $request)
    {
        $user = MpUser::find(2);
        return response()->json([
            'ret_code' => 0,
            'ret_msg' => 'succ',
            'data' => $user
        ]);
    }
}
