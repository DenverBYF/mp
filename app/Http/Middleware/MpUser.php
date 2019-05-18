<?php

namespace App\Http\Middleware;

use Closure;

class MpUser
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if ($request->session()->has('openid') and $request->session()->has('mp_user_id')) {
            return $next($request);
        } else {
            // test only!!!!
            // $request->session()->put('mp_user_id', 1);
            // return $next($request);
            //未登陆，返回403
            return response('not login', 403);
        }

    }
}
