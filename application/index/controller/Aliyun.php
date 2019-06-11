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
            $keyAry=explode('/',$params['filename']);
            $uuid=end($keyAry);
            try{
                $files=Db::table('data_files')->where('id',$uuid)->whereNull('deleted_at')->find();
            }catch (\Exception $e){
                $errors = [
                    'type' => '300,0',
                    'msg' => "DataFile Cannot Be Find, ID:". $uuid .",object:". $params['filename']
                ];
                $data = [
                    'target_app' => null,
                    'code' => $errors['type'],
                    'msg' => $errors['msg'],
                    'errors'=>$errors,
                    'data'=>[]
                ];
                echo json_encode($data);
            }
            if (!empty($files)){
                $options = array(
                    'headers'=> array(
                        'Content-Disposition' => 'attachment; filename="'.$files['filename'].'"',
                        'x-oss-meta-self-define-title' => 'user define meta info',
                    ));
                try{
                    $content = file_get_contents(__FILE__);
                    $ossClient = $this->new_oss();
                    $res = $ossClient->putObject(Config('env.aliyun_oss.KeyId'), $params['filename'], $content, $options);
                    $signedUrl = $ossClient->signUrl(Config('env.aliyun_oss.KeyId'), $params['filename'], 3153600000);
                    $res['signedUrl'] = htmlspecialchars_decode($signedUrl);
                    list($download_head, $download_url) = explode("?", $res['signedUrl']);
                } catch(\Exception $e) {
                    return errorMsg('101', $e->getMessage(), 400);
                }
                $resInfo=Db::table('data_files')->where('id',$uuid)->whereNull('deleted_at')->update(['etag'=>$params['etag'],'size'=>$params['size']/1024,'content_type'=>$res['oss-requestheaders']['Content-Type'],'download_url'=>$download_url]);
                if ($resInfo){
                    $access_type = $files['access_type'] == 0 ? 'private' : 'public';
                    $tot=[
                        'id'=>$uuid,
                        'access_type'=>$access_type,
                        'filename'=>$files['filename'],
                        'size'=>$params['size']/1024,
                        'download_link'=>Config('env.oss_custom_host') . "/" . $access_type . "/" . $this->member_id . "/" . $this->client_name . "/" . $data['id'] . "?" . $data['download_url'],
                        'thumbnail'=>"",
                        'content_type'=>$res['oss-requestheaders']['Content-Type'],
                        'folder'=>$files['folder'],
                        'created_at'=>strtotime($data['updated_at']),
                        'updated_at'=>strtotime($data['updated_at']),
                        'last_modified_time'=>$files['last_modified_time'],
                        'is_deleted' => empty($data['deleted_at']) ? 'false' : 'true'
                    ];
                    $data = [
                        'target_app' => null,
                        'code' => '0,0',
                        'msg' => '',
                        'errors'=>[],
                        'data'=>$tot
                    ];
                    echo json_encode($data);
                }
            }

        } else {
            //header("http/1.1 403 Forbidden");
            exit();
        }
    }
    private function new_oss()
    {
        $oss = new \OSS\OssClient(Config('env.aliyun_oss.KeyId'), Config('env.aliyun_oss.KeySecret'), Config('env.aliyun_oss.Endpoint'), false);
        return $oss;
    }
}