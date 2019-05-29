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

Route::get('think', function () {
    return 'hello,ThinkPHP5!';
});

Route::group('files', function () {
    Route::get(':id', 'index/index/filesInfo');
    Route::get('', 'index/index/files');
    Route::post('', 'index/index/upload');
})->pattern(['id' => '\\w{8}(-\\w{4}){3}-\\w{12}?']);
Route::get('me', 'index/index/me');
Route::post('batch_delete', 'index/index/batch_delete');
Route::post('move_folder', 'index/index/moveFolder');
Route::post('batch_update', 'index/index/batch_update');
Route::post('share', 'index/index/share');
Route::post('cancel_share', 'index/index/cancel_share');
Route::get('sharings', 'index/index/sharings');
Route::get('s/:name', 'index/Download/index');
Route::rule('download/:name', 'index/Download/download');
return [

];
