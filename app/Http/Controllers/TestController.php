<?php

namespace App\Http\Controllers;

use App\Act;
use App\Gift;
use Illuminate\Http\Request;

class TestController extends Controller
{
    //
    public function index(Request $request)
    {
        $user = Act::find(1)->User;
        dd($user);
    }
}
