<?php

namespace App\Http\Controllers;

use App\Act;
use App\Gift;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TestController extends Controller
{
    //
    public function index(Request $request)
    {
        $joinNumbers = DB::table('act_user')->where('act_id', '=', 10)->select('mp_user_id')->get();
        dd($joinNumbers[0]->mp_user_id);
    }
}
