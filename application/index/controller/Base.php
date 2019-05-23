<?php
/**
 * Created by PhpStorm.
 * User: 陈大剩
 * Date: 2019/5/10
 * Time: 17:58
 */

namespace app\index\controller;

use think\Controller;
use app\common\controller\ApiException;
use think\Config;
use think\Db;
use \Firebase\JWT\JWT;
class Base extends Controller
{
    /**
     * headers信息
     * @var string
     */
    public $headers = '';
    public $member_id="";
    public $client_id="";
    public $client_name="";
    public function initialize()
    {
//        if(Config('app_debug') !== true) {
//            return $this->checkRequestAuth();
//        }
        parent::initialize();
        return $this->checkRequestAuth();
    }

    public function checkRequestAuth(){
        $headers = request()->header();
        if (!isset($headers['Authorization']) && !isset($headers['authorization'])){
            throw new ApiException('token不存在', 400);
        }
        if (!isset($headers['Authorization']))$headers['Authorization']=$headers['authorization'];
        if(empty($headers['Authorization'])) {
            throw new ApiException('token不存在', 400);
        }
        $key = config('env.tonken_key');
        try{
            $info = JWT::decode($headers['Authorization'],$key,["HS512"]); //解密jwt
            $this->headers = $headers;
            $this->member_id=$info->member->id;
            $this->client_id=$info->client->id;
        }catch (\Exception $e) {
            throw new ApiException('token验证失败', 400);
        }
        return $this->authInfo($info->client->id);
    }
    public function authInfo($client_id){
        $app=Db::table('clients')->where('id',$client_id)->field('code')->find();
        return $this->client_name=$app['code'];
    }
    public function jwt(){
        $key = "2f5cdce3b2e1e98d421ab144fa03ad4c0f8d59020a0ec5ec9726a97d277fd23da1909d0475a302818c9bfb98f60dd146da452d9e003ba2746ede8edfbf97288f";  //这里是自定义的一个随机字串，应该写在config文件中的，解密时也会用，相当    于加密中常用的 盐  salt
        $token = [
            "member"=>[
                "id"=>'a8c076a3-d910-4801-b887-30fdfb6ad1a5'
            ],
            "client"=>[
                "id"=>"05522fa3-6002-4462-bcea-d36dcfda7e34",
                "code"=>2,
                "official"=>false
            ],
            "iat" => time(), //签发时间
            "nbf" => time(), //在什么时候jwt开始生效  （这里表示生成100秒后才生效）
            "exp" => time()+720000, //token 过期时间
        ];
        $jwt = JWT::encode($token,$key,"HS512"); //根据参数生成了 token
        return json([
            "token"=>$jwt
        ]);
    }
    /**
     * 空方法
     */
    public function _empty()
    {
        throw new ApiException('empty method!', 404);
    }
}