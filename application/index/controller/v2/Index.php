<?php
/**
 * Created by PhpStorm.
 * User: 陈大剩
 * Date: 2019/5/31
 * Time: 15:28
 */

namespace app\index\controller\v2;

use think\Db;
use app\index\controller\Base;

class Index extends Base
{
    private $filename = "";
    public function files(){
        $get = input('get.');
        $time=0;
        if (isset($get['last_refresh_time'])){
            if (empty($get['last_refresh_time'])){
                $time=0;
            }else{
                $time=$get['last_refresh_time'];
            }
        }else{
            $time=0;
        }
        if (strlen($time)==10){
            $time=$time."000";
        }
        $data=[];
        $res=Db::table('data_files')->where('client_id', $this->client_id)->where('member_id', $this->member_id)->whereNull('deleted_at')->where('last_modified_time','>=',$time)->select();
        foreach ($res as $v){
            $access_type = $v['access_type'] == 0 ? 'private' : 'public';
            $data[]=[
                'id'=>$v['id'],
                'access_type'=>$access_type,
                'filename'=>$v['filename'],
                'size'=>round($v['size'],3),
                'download_link'=>Config('env.oss_custom_host') . "/" . $access_type . "/" . $this->member_id . "/" . $this->client_name . "/" . $v['id'] . "?" . $v['download_url'],
                'thumbnail'=>"",
                'content_type'=>$v['content_type'],
                'folder'=>$v['folder'],
                'created_at'=>strtotime($v['created_at']),
                'updated_at'=>strtotime($v['updated_at']),
                'last_modified_time'=>$v['last_modified_time'],
                'is_deleted'=>empty($v['deleted_at']) ? 'false' : 'true',
            ];
        }
        return show($this->client_name, $code = "0,0", $msg = "", $errors = [], $data);
    }
    public function upload()
    {
        $post = input('post.');
        if (empty($post)) {
            $errors = [
                'type' => '301,1',
                'msg' => "Invalid client"
            ];
            return show("null", $errors['type'], $errors['msg'], $errors);
        }
        if (!isset($post['modify_time'])) {
            $last_modified_time = get13TimeStamp();
        } else {
            $last_modified_time = is_numeric($post['modify_time']) ? $post['modify_time'] : get13TimeStamp();
        }
        if (isset($post['target_app'])) {
            $appName = Db::table('clients')->where('code', $post['target_app'])->find();
            if (empty($appName)) {
                $errors = [
                    'type' => '100,2',
                    'msg' => "Invalid client"
                ];
                return show("null", $errors['type'], $errors['msg'], $errors);
            }else{
                $this->client_id=$appName['id'];
                $this->client_name=$post['target_app'];
            }
        }
        if (!isset($post['folder'])) $post['folder'] = "";
        $post['folder'] = $this->screen($post['folder']);
        if (isset($post['uuid'])){
            $oneFiles = Db::table('data_files')->whereNull('deleted_at')->where('id', $post['uuid'])->find();
            if (empty($oneFiles)){
                $errors = [
                    'type' => '100,4',
                    'msg' => "uuid empty"
                ];
                return show("null", $errors['type'], $errors['msg'], $errors);
            }else{
                $fileName = "private" . "/" . $this->member_id . "/" . $this->client_name . "/" . $post['uuid'];
                $id = $this->directory($post['folder']);
                if (isset($post['modify_time'])) {
                    $edit = [
                        'folder' => $post['folder'],
                        'folder_id' => $id,
                        'last_modified_time' => $post['modify_time']
                    ];
                } else {
                    $edit = [
                        'folder' => $post['folder'],
                        'folder_id' => $id,
                    ];
                }
                if (isset($post['filename'])) {
                    $ossClient = new \OSS\OssClient(Config('env.aliyun_oss.KeyId'), Config('env.aliyun_oss.KeySecret'), Config('env.aliyun_oss.Endpoint'), false);
                    $options = array(
                        'headers' => array(
                            'Content-Disposition' => 'attachment; filename="' . $post['filename'] . '"',
                        ));
                    try {
                        $ossClient->copyObject(Config('env.aliyun_oss.Bucket'), $fileName, Config('env.aliyun_oss.Bucket'), $fileName, $options);
                        $signedUrl = $ossClient->signUrl(Config('env.aliyun_oss.Bucket'),$fileName, 315360000);
                        $res['signedUrl'] = htmlspecialchars_decode($signedUrl);
                        list($download_head, $download_url) = explode("?", $res['signedUrl']);
                    } catch (\Exception $e) {
                        $errors = [
                            'type' => '100,3',
                            'msg' => "Invalid client"
                        ];
                        return show("null", $errors['type'], $errors['msg'], $errors);
                    }
                    $edit['download_url']=$download_url;
                    $edit['filename']=$post['filename'];
                    $ra = $this->oss_qianming();
                    $res = Db::table('data_files')->where('id',$post['uuid'])->update($edit);
                    if ($res){
                        $tot = [
                            'id' => $post['uuid'],
                            'authorize' => $ra
                        ];
                        return show($this->client_name, $code = "0,0", $msg = "", [], $tot);
                    }
                }else{
                    $ra = $this->oss_qianming();
                    $res = Db::table('data_files')->where('id',$post['uuid'])->update($edit);
                    if ($res){
                        $tot = [
                            'id' => $post['uuid'],
                            'authorize' => $ra
                        ];
                        return show($this->client_name, $code = "0,0", $msg = "", [], $tot);
                    }
                }
            }
        }else{
            if (isset($post['filename'])) {
                if (empty($post['filename'])) {
                    $errors = [
                        'type' => '301,2',
                        'msg' => "Invalid client"
                    ];
                    return show("null", $errors['type'], $errors['msg'], $errors);
                } else {
                    $this->filename = $post['filename'];
                }
            } else {
                $errors = [
                    'type' => '301,2',
                    'msg' => "Invalid client"
                ];
                return show("null", $errors['type'], $errors['msg'], $errors);
            }
            $ra = $this->oss_qianming();
            $folder_id = $this->directory($post['folder']);
            $uuid = guid();
            $data = [
                'id' => $uuid,
                'client_id' => $this->client_id,
                'member_id' => $this->member_id,
                'access_type' => 0,
                'filename' => $post['filename'],
                'folder' => $post['folder'],
                'created_at' => date("Y-m-d H:i:s"),
                'updated_at' => date("Y-m-d H:i:s"),
                'file' => $ra['dir'],
                'folder_id' => $folder_id,
                'last_modified_time' => get13TimeStamp()
            ];
            if (isset($post['modify_time'])) {
                $data['last_modified_time']=$post['modify_time'];
            }
            $res = Db::table('data_files')->insert($data);
            if ($res) {
                $tot = [
                    'id' => $data['id'],
                    'authorize' => $ra
                ];
                return show($this->client_name, $code = "0,0", $msg = "", [], $tot);
            } else {

            }
        }
    }

    public function gmt_iso8601($time)
    {
        $dtStr = date("c", $time);
        $mydatetime = new \DateTime($dtStr);
        $expiration = $mydatetime->format(\DateTime::ISO8601);
        $pos = strpos($expiration, '+');
        $expiration = substr($expiration, 0, $pos);
        return $expiration . "Z";
    }

    function oss_qianming()
    {
        $id = config('env.aliyun_oss.KeyId');
        $key = config('env.aliyun_oss.KeySecret');
        $host = config('env.oss_custom_host');
        // $callbackUrl为上传回调服务器的URL，请将下面的IP和Port配置为您自己的真实URL信息。
        $callbackUrl = config('env.callback_url') . "/api/files/post_callback";
        $dir = "private" . "/" . $this->member_id . "/" . $this->client_name;          // 用户上传文件时指定的前缀。
        $callback_param = array('callbackUrl' => $callbackUrl,
            'callbackBody' => 'filename=${object}&size=${size}&mimeType=${mimeType}&height=${imageInfo.height}&width=${imageInfo.width}&etag=${etag}',
            'callbackBodyType' => "application/x-www-form-urlencoded");
        $callback_string = json_encode($callback_param);
        $base64_callback_body = base64_encode($callback_string);
        $now = time();
        $expire = 1800; //设置该policy超时时间是10s. 即这个policy过了这个有效时间，将不能访问
        $end = $now + $expire;
        $expiration = $this->gmt_iso8601($end);
        //最大文件大小.用户可以自己设置
        $condition = array(0 => 'content-length-range', 1 => 0, 2 => 1048576000);
        $conditions[] = $condition;
        //表示用户上传的数据,必须是以$dir开始, 不然上传会失败,这一步不是必须项,只是为了安全起见,防止用户通过policy上传到别人的目录
        $start = array(0 => 'starts-with', 1 => '$key', 2 => $dir);
        $conditions[] = $start;
        $arr = array('expiration' => $expiration, 'conditions' => $conditions);
        $policy = json_encode($arr);
        $base64_policy = base64_encode($policy);
        $string_to_sign = $base64_policy;
        $signature = base64_encode(hash_hmac('sha1', $string_to_sign, $key, true));
        $response = array();
        $response['accessid'] = $id;
        $response['host'] = $host;
        $response['policy'] = $base64_policy;
        $response['signature'] = $signature;
        $response['expire'] = $end;
        $response['callback'] = $base64_callback_body;
        //这个参数是设置用户上传指定的前缀
        $response['dir'] = $dir;
        return $response;
    }
}