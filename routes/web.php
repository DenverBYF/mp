<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Auth::routes();

Route::get('/home', 'HomeController@index')->name('home');

//用户登录请求
Route::get('/mplogin', 'MpUserController@userLogin');


//微信相关路由
Route::any('/wechat', 'WeChatController@serve');

Route::middleware(['mp'])->prefix('mp')->group(function () {
    Route::match(['post', 'get'], 'setting', 'MpUserController@setting');
    Route::get('myacts', 'ActController@acts');
    Route::get('history', 'ActController@history');
    Route::post('join', 'ActController@join');
    Route::resource('act', 'ActController');
});

//开发测试路由及控制器
Route::any('/test', 'TestController@index');
