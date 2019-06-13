<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
use think\facade\Route;
use think\facade\Request;
$version = Request::header('Accept');
if (empty($version)){
    $v='v1';
}else {
    $versionAty=explode(";",$version);
    if (empty($versionAty[1])){
        $v='v1';
    }else{
        $ary=explode("=",$versionAty[1]);
        if (count($ary)<2){
            $v='v1';
        }else{
            if (empty((int)$ary[1])){
                $v='v1';
            }else{
                if (is_numeric((int)$ary[1])){
                    $v='v'.$ary[1];
                }else{
                    $v='v1';
                }
            }
        }

    }
}

/**
 * 路由开始
 */
Route::get('/', 'index/MyError/kong')->allowCrossDomain();
Route::get('api/files', 'index/'.$v.'.index/files')->allowCrossDomain();
Route::get('api/files/:id', 'index/'.$v.'.index/filesInfo')->pattern(['id' => '\\w{8}(-\\w{4}){3}-\\w{12}?'])->allowCrossDomain();
Route::get('api/members/me', 'index/'.$v.'.index/me')->allowCrossDomain();
Route::post('api/files/batch_delete', 'index/'.$v.'.index/batch_delete')->allowCrossDomain();
Route::post('api/files/move_folder', 'index/'.$v.'.index/moveFolder')->allowCrossDomain();
Route::post('api/files/batch_update', 'index/'.$v.'.index/batch_update')->allowCrossDomain();
Route::post('api/files/share', 'index/'.$v.'.index/share')->allowCrossDomain();
Route::post('api/files/cancel_share', 'index/'.$v.'.index/cancel_share')->allowCrossDomain();
Route::get('api/sharings', 'index/'.$v.'.index/sharings')->allowCrossDomain();
Route::get('s/:name', 'index/'.$v.'.Download/index')->allowCrossDomain();
Route::rule('download/:name', 'index/'.$v.'.Download/download')->allowCrossDomain();
Route::post('oss_qianming', 'index/'.$v.'.index/oss_qianming')->allowCrossDomain();
Route::post('api/files/post_callback', 'index/Aliyun/index')->allowCrossDomain();
Route::post('api/files', 'index/'.$v.'.index/upload')->allowCrossDomain();
return [
];
