<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 流年 <liu21st@gmail.com>
// +----------------------------------------------------------------------
use \Firebase\JWT\JWT;
use think\Db;
// 应用公共文件

function check($jwt){
    $key = "2f5cdce3b2e1e98d421ab144fa03ad4c0f8d59020a0ec5ec9726a97d277fd23da1909d0475a302818c9bfb98f60dd146da452d9e003ba2746ede8edfbf97288f";
    $info = JWT::decode($jwt,$key,["HS512"]); //解密jwt
    return $info;
}
function show($target_app, $code = "0,0",$msg ="",$errors = [],$data=[] , $httpCode=200) {

    $data = [
        'target_app' => $target_app,
        'code' => $code,
        'msg' => $msg,
        'errors'=>$errors,
        'data'=>$data
    ];

    return json($data, $httpCode);
}
function errorMsg($status, $message,$httpCode=200) {

    $data = [
        'status' => $status,
        'error' => $message,
    ];

    return json($data, $httpCode);
}
function getTree($data, $pId,$folder='')
{
    $tree = [];
    foreach($data as $k => $v)
    {
        if($v['parent_id'] === $pId)
        {        //父亲找到儿子
            $count=Db::table('data_files')->where('folder_id',$v['id'])->count();
            unset($v['parent_id']);
            unset($v['member_id']);
            unset($v['created_at']);
            unset($v['updated_at']);
            unset($v['deleted_at']);
            $v['files_count']=$count;
            $tree[] = $v;
        }
    }
    return $tree;
}
function guid(){
    if (function_exists('com_create_guid')){
        $delete_last = substr(com_create_guid(),0,-1);
        $delete_fist = substr($delete_last,1);
        return strtolower($delete_fist);
    }else{
        mt_srand((double)microtime()*10000);//optional for php 4.2.0 and up.
        $charid = strtolower(md5(uniqid(rand(), true)));
        $hyphen = chr(45);// "-"
        $uuid =substr($charid, 0, 8).$hyphen
            .substr($charid, 8, 4).$hyphen
            .substr($charid,12, 4).$hyphen
            .substr($charid,16, 4).$hyphen
            .substr($charid,20,12);
        return strtolower($uuid);
    }
}


