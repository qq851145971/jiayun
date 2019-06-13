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
use app\common\controller\ApiException;
use app\common\controller\Uploads;
// 应用公共文件
/**
 * 解密jwt
 * User: 陈大剩
 * @param $jwt
 * @return object
 */
function check($jwt){
    $key = "2f5cdce3b2e1e98d421ab144fa03ad4c0f8d59020a0ec5ec9726a97d277fd23da1909d0475a302818c9bfb98f60dd146da452d9e003ba2746ede8edfbf97288f";
    $info = JWT::decode($jwt,$key,["HS512"]); //解密jwt
    return $info;
}

/**
 * 统一格式输出
 * User: 陈大剩
 * @param $target_app
 * @param string $code
 * @param string $msg
 * @param array $errors
 * @param array $data
 * @param int $httpCode
 * @return \think\response\Json
 */
function show($target_app, $code = "0,0",$msg ="",$errors = [],$data=[] , $httpCode=200) {

    $tot = [
        'target_app' => $target_app,
        'code' => $code,
        'msg' => $msg,
        'errors'=>$errors,
        'data'=>$data
    ];

    return json($tot, $httpCode);
}

/**
 * 失败格式输出
 * User: 陈大剩
 * @param $status
 * @param $message
 * @param int $httpCode
 * @return \think\response\Json
 */
function errorMsg($status, $message,$httpCode=200) {

    $data = [
        'status' => $status,
        'error' => $message,
    ];

    return json($data, $httpCode);
}

/**
 * 获取十三位时间戳
 * User: 陈大剩
 * @return string
 */
function get13TimeStamp() {
    list($t1, $t2) = explode(' ', microtime());
    return $t2 . ceil($t1 * 1000);
}

/**
 * 获取uuid
 * User: 陈大剩
 * @return string
 */
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

/**
 * 下载远程文件，默认保存在TEMP_PATH下
 * @param  string  $url     网址
 * @param  string  $filename    保存文件名
 * @param  integer $timeout 过期时间
 * @param  bool $repalce 是否覆盖已存在文件
 * @return string 本地文件名
 */
function http_down($url, $filename = "", $timeout = 60) {
    if (empty($filename)) {
        $filename = TEMP_PATH . pathinfo($url, PATHINFO_BASENAME);
    }
    $path = dirname($filename);
    if (!is_dir($path) && !mkdir($path, 0755, true)) {
        return false;
    }
    $url = str_replace(" ", "%20", $url);
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        $temp = curl_exec($ch);
        try {
            if (file_put_contents($filename, $temp) && !curl_error($ch)) {
                return $filename;
            } else {
                return false;
            }
        } catch (Exception  $e) {
            throw new ApiException('token验证失败', 400);
        }

    } else {
        $opts = [
            "http" => [
                "method" => "GET",
                "header" => "",
                "timeout" => $timeout,
            ],
        ];
        $context = stream_context_create($opts);
        if (@copy($url, $filename, $context)) {
            //$http_response_header
            return $filename;
        } else {
            return false;
        }
    }
}
/**
 * 获得header
 * @param  string $url 网址
 * @return string
 */
function get_head($url) {
    $ch = curl_init();
    $header[] = "Content-type: application/x-www-form-urlencoded";
    $user_agent = "Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/33.0.1750.146 Safari/537.36";
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_USERAGENT, $user_agent);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, false);
    $sContent = curl_exec($ch);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $header = substr($sContent, 0, $headerSize);
    curl_close($ch);
    return $header;
}

function get_files($files){
    if(strpos($files, '://') === false){
        $files = Env::get('root_path').'public'.DS.'static'.DS.'index'.DS.'images'.DS.$files;
        if(!is_file($files)){
            $files = false;
        }
    } else {
        $file = new Uploads($files);
        $files = $file->getFileName();
    }
    return $files;
}
function get_oss_custom_host(){
    $host=Config('env.oss_custom_host');
    if ($host==""){
       $str="https://".Config('env.aliyun_oss.Bucket').".".Config('env.aliyun_oss.Endpoint');
        return $str;
    }else{
       return $host;
    }
}

