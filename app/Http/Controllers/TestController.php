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
        $i = 0;
        $info = DB::table('act_user')->where('act_id', '=', 3)->select('mp_user_id')->get()->toArray();
        $t = new \stdClass();
        $t->mp_user_id = 2;
        var_dump($t);
        var_dump(array_search($t, $info));
    }
}
class Test {
    public $mp_user_id;
    public function __construct($id)
    {
        $this->mp_user_id = $id;
    }
}
