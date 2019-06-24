<?php
/**
 * Created by PhpStorm.
 * User: 陈大剩
 * Date: 2019/6/10
 * Time: 11:42
 */

namespace app\index\controller;

use think\Controller;
use think\Db;
use app\common\controller\Mime;

class Aliyun extends Controller
{
    public function index()
    {

        $authorizationBase64 = "";
        $pubKeyUrlBase64 = "";
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $authorizationBase64 = $_SERVER['HTTP_AUTHORIZATION'];
        }
        if (isset($_SERVER['HTTP_X_OSS_PUB_KEY_URL'])) {
            $pubKeyUrlBase64 = $_SERVER['HTTP_X_OSS_PUB_KEY_URL'];
        }

        if ($authorizationBase64 == '' || $pubKeyUrlBase64 == '') {
            header("http/1.1 403 Forbidden");
            exit();
        }

        $authorization = base64_decode($authorizationBase64);


        $pubKeyUrl = base64_decode($pubKeyUrlBase64);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $pubKeyUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        $pubKey = curl_exec($ch);
        if ($pubKey == "") {
            //header("http/1.1 403 Forbidden");
            exit();
        }
        $body = file_get_contents('php://input');
        $authStr = '';
        $path = $_SERVER['REQUEST_URI'];
        $pos = strpos($path, '?');
        if ($pos === false) {
            $authStr = urldecode($path) . "\n" . $body;
        } else {
            $authStr = urldecode(substr($path, 0, $pos)) . substr($path, $pos, strlen($path) - $pos) . "\n" . $body;
        }
        $ok = openssl_verify($authStr, $authorization, $pubKey, OPENSSL_ALGO_MD5);
        if ($ok == 1) {
            header("Content-Type: application/json");
            $params = input('param.');
            $keyAry = explode('/', $params['filename']);
            $uuid = end($keyAry);
            try {
                $files = Db::table('data_files')->where('id', $uuid)->whereNull('deleted_at')->find();
            } catch (\Exception $e) {
                try {
                    $ossClient = new \OSS\OssClient(Config('env.aliyun_oss.KeyId'), Config('env.aliyun_oss.KeySecret'), Config('env.aliyun_oss.Endpoint'), false);
                    $ossClient->deleteObject(Config('env.aliyun_oss.Bucket'), $params['filename']);
                } catch (\Exception $e) {
                    $errors = [
                        'type' => '300,9',
                        'msg' => "DataFile Cannot Be Find, ID:" . $uuid . ",object:" . $params['filename']
                    ];
                    $data = [
                        'target_app' => null,
                        'code' => $errors['type'],
                        'msg' => $errors['msg'],
                        'errors' => $errors,
                        'data' => []
                    ];
                    echo json_encode($data);
                    return ;
                }
                $errors = [
                    'type' => '300,0',
                    'msg' => "DataFile Cannot Be Find, ID:" . $uuid . ",object:" . $params['filename']
                ];
                $data = [
                    'target_app' => null,
                    'code' => $errors['type'],
                    'msg' => $errors['msg'],
                    'errors' => $errors,
                    'data' => []
                ];
                echo json_encode($data);
                return ;
            }
            if (!empty($files)) {
                $Mime = new Mime();
                if ($params['mimeType']=="application/octet-stream"){
                    $define=$Mime->get_mimetype($files['filename']);
                }else{
                    $define=$params['mimeType'];
                }
                $options = array(
                    'headers' => array(
                        'Content-Disposition' => 'attachment; filename="' . $files['filename'] . '"',
                        'x-oss-meta-self-define-title' => $define,
                    ));
                try {
                    $ossClient = new \OSS\OssClient(Config('env.aliyun_oss.KeyId'), Config('env.aliyun_oss.KeySecret'), Config('env.aliyun_oss.Endpoint'), false);
                    $res = $ossClient->copyObject(Config('env.aliyun_oss.Bucket'), $params['filename'], Config('env.aliyun_oss.Bucket'), $params['filename'], $options);
                    $signedUrl = $ossClient->signUrl(Config('env.aliyun_oss.Bucket'), $params['filename'], 315360000);
                    $res['signedUrl'] = htmlspecialchars_decode($signedUrl);
                    list($download_head, $download_url) = explode("?", $res['signedUrl']);
                } catch (\Exception $e) {
                    $errors = [
                        'type' => '300,2',
                        'msg' => "fail to upload "
                    ];
                    $data = [
                        'target_app' => null,
                        'code' => $errors['type'],
                        'msg' => $errors['msg'],
                        'errors' => $errors,
                        'data' => []
                    ];
                    echo json_encode($data);
                    return ;
                }
                $ext = strtolower($files['filename']);
                $extAry=explode(".",$ext);
                if (count($extAry)==1){
                    $extstr=$extAry[0];
                }else{
                    $extstr=$extAry[1];
                }
                $resInfo = Db::table('data_files')->where('id', $uuid)->whereNull('deleted_at')->update(['etag' => $params['etag'], 'size' => $params['size'] / 1024, 'content_type' => $params['mimeType'],'download_url' => $download_url,'suffix'=>$extstr]);
                if ($resInfo) {
                    $count = Db::table('data_files')->whereNotNull('size')->whereNull('deleted_at')->where('client_id', $files['client_id'])->where('member_id',  $files['member_id'])->sum('size');
                    Db::table('members')->where('id', $files['member_id'])->data(['used_space' => $count])->update();
                    $access_type = $files['access_type'] == 0 ? 'private' : 'public';
                    $tot = [
                        'id' => $uuid,
                        'access_type' => $access_type,
                        'filename' => $files['filename'],
                        'size' => $params['size'] / 1024,
                        'download_link' => get_oss_custom_host() . "/" . $params['filename'] . "?" . $download_url,
                        'thumbnail' => "",
                        'content_type' => $params['mimeType'],
                        'folder' => $files['folder'],
                        'created_at' => strtotime($files['updated_at']),
                        'updated_at' => strtotime($files['updated_at']),
                        'last_modified_time' => $files['last_modified_time'],
                        'is_deleted' => empty($files['deleted_at']) ? false: true
                    ];
                    $data = [
                        'target_app' => null,
                        'code' => '0,0',
                        'msg' => '',
                        'errors' => [],
                        'data' => $tot
                    ];
                    echo json_encode($data);
                    return ;
                } else {
                    $errors = [
                        'type' => '300,3',
                        'msg' => "Insert the failure"
                    ];
                    $data = [
                        'target_app' => null,
                        'code' => $errors['type'],
                        'msg' => $errors['msg'],
                        'errors' => $errors,
                        'data' => []
                    ];
                    echo json_encode($data);
                    return ;
                }
            } else {
                $errors = [
                    'type' => '300,4',
                    'msg' => "The file is empty"
                ];
                $data = [
                    'target_app' => null,
                    'code' => $errors['type'],
                    'msg' => $errors['msg'],
                    'errors' => $errors,
                    'data' => []
                ];
                echo json_encode($data);
                return ;
            }

        } else {
            //header("http/1.1 403 Forbidden");
            exit();
        }
    }
}