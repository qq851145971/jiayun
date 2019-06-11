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

Route::get('api/files', 'index/'.$v.'.index/files');
Route::get('api/files/:id', 'index/'.$v.'.index/filesInfo')->pattern(['id' => '\\w{8}(-\\w{4}){3}-\\w{12}?']);
Route::get('api/members/me', 'index/'.$v.'.index/me');
Route::post('api/files/batch_delete', 'index/'.$v.'.index/batch_delete');
Route::post('api/files/move_folder', 'index/'.$v.'.index/moveFolder');
Route::post('api/files/batch_update', 'index/'.$v.'.index/batch_update');
Route::post('api/files/share', 'index/'.$v.'.index/share');
Route::post('api/files/cancel_share', 'index/'.$v.'.index/cancel_share');
Route::get('api/sharings', 'index/'.$v.'.index/sharings');
Route::get('s/:name', 'index/'.$v.'.Download/index');
Route::rule('download/:name', 'index/'.$v.'.Download/download');
Route::post('oss_qianming', 'index/'.$v.'.index/oss_qianming');
Route::post('aliyun', 'index/Aliyun/index');
Route::post('api/files', 'index/'.$v.'.index/upload');
return [
];
