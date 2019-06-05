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
    public function upload(){
        $post=input('post.');
        if (empty($post)){
            $errors=[
                'type'=>'301,1',
                'msg'=>"Invalid client"
            ];
            return show("null",$errors['type'],$errors['msg'],$errors);
        }
        if (isset($post['filename'])){
            $errors=[
                'type'=>'301,1',
                'msg'=>"Invalid client"
            ];
            return show("null",$errors['type'],$errors['msg'],$errors);
        }else{

        }
        if (!isset($post['modify_time'])){
            $last_modified_time=get13TimeStamp();
        }else{
            $last_modified_time=is_numeric($post['modify_time'])?$post['modify_time']:get13TimeStamp();
        }
        if (isset($post['target_app'])){
            $appName=Db::table('clients')->where('code',$post['target_app'])->find();
            if (empty($appName)){
                $errors=[
                    'type'=>'100,2',
                    'msg'=>"Invalid client"
                ];
                return show("null",$errors['type'],$errors['msg'],$errors);
            }
        }
        if (!isset($post['folder']))$post['folder']="";
        $post['folder'] =$this->screen($post['folder']);
        $ra=$this->oss_qianming();
        $da=$this->directory($post['folder']);
        dump($da);
    }
    public function gmt_iso8601($time) {
        $dtStr = date("c", $time);
        $mydatetime = new \DateTime($dtStr);
        $expiration = $mydatetime->format(\DateTime::ISO8601);
        $pos = strpos($expiration, '+');
        $expiration = substr($expiration, 0, $pos);
        return $expiration."Z";
    }


    function oss_qianming(){
        $id= config('env.aliyun_oss.KeyId');
        $key= config('env.aliyun_oss.KeySecret');
        $host = config('env.oss_custom_host');
        // $callbackUrl为上传回调服务器的URL，请将下面的IP和Port配置为您自己的真实URL信息。
        $callbackUrl = 'http://88.88.88.88:8888/aliyun-oss-appserver-php/php/callback.php';
        $dir = "private"."/".$this->member_id."/".$this->client_name;          // 用户上传文件时指定的前缀。

        $callback_param = array('callbackUrl'=>$callbackUrl,
            'callbackBody'=>'filename=${object}&size=${size}&mimeType=${mimeType}&height=${imageInfo.height}&width=${imageInfo.width}',
            'callbackBodyType'=>"application/x-www-form-urlencoded");
        $callback_string = json_encode($callback_param);
        $base64_callback_body = base64_encode($callback_string);
        $now = time();
        $expire = 1800; //设置该policy超时时间是10s. 即这个policy过了这个有效时间，将不能访问
        $end = $now + $expire;
        $expiration = $this->gmt_iso8601($end);
        //最大文件大小.用户可以自己设置
        $condition = array(0=>'content-length-range', 1=>0, 2=>1048576000);
        $conditions[] = $condition;

        //表示用户上传的数据,必须是以$dir开始, 不然上传会失败,这一步不是必须项,只是为了安全起见,防止用户通过policy上传到别人的目录
        $start = array(0=>'starts-with', 1=>'$key', 2=>$dir);
        $conditions[] = $start;

        $arr = array('expiration'=>$expiration,'conditions'=>$conditions);
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