<?php
namespace app\index\controller;
use think\Db;
use think\facade\Request;
use \Firebase\JWT\JWT;
use think\Config;
class Index extends Base
{
    public function index()
    {
        return '<style type="text/css">*{ padding: 0; margin: 0; } div{ padding: 4px 48px;} a{color:#2E5CD5;cursor: pointer;text-decoration: none} a:hover{text-decoration:underline; } body{ background: #fff; font-family: "Century Gothic","Microsoft yahei"; color: #333;font-size:18px;} h1{ font-size: 100px; font-weight: normal; margin-bottom: 12px; } p{ line-height: 1.6em; font-size: 42px }</style><div style="padding: 24px 48px;"> <h1>:) </h1><p> ThinkPHP V5.1<br/><span style="font-size:30px">12载初心不改（2006-2018） - 你值得信赖的PHP框架</span></p></div><script type="text/javascript" src="https://tajs.qq.com/stats?sId=64890268" charset="UTF-8"></script><script type="text/javascript" src="https://e.topthink.com/Public/static/client.js"></script><think id="eab4b9f840753f8e7"></think>';
    }

    public function hello()
    {
        $info = Request::header();
        $data=[
            'id'=>1,
            'name'=>22
        ];
        dump($info);
      return  json($data)->code(201)->header(['Cache-control' => '1']);
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
    private $getFilename="";
    public function check(){
        $jwt ="eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzUxMiJ9.eyJtZW1iZXIiOnsiaWQiOiJhOGMwNzZhMy1kOTEwLTQ4MDEtYjg4Ny0zMGZkZmI2YWQxYTUifSwiY2xpZW50Ijp7ImlkIjoiMDU1MjJmYTMtNjAwMi00NDYyLWJjZWEtZDM2ZGNmZGE3ZTM0IiwiY29kZSI6Miwib2ZmaWNpYWwiOmZhbHNlfSwiaWF0IjoxNTU3NzI5MDYwLCJuYmYiOjE1NTc3MjkwNjAsImV4cCI6MTU1NzczNjI2MH0.WoOk_DsqKnxQ7Mo4hnAS-nxpd4IWSq13z-9xRyApXzeDkpkMm5ABa3iN10pCDu_9bVn6hrcw6jOLLSv2omFy1Q";  //上一步中返回给用户的token
        $key = "2f5cdce3b2e1e98d421ab144fa03ad4c0f8d59020a0ec5ec9726a97d277fd23da1909d0475a302818c9bfb98f60dd146da452d9e003ba2746ede8edfbf97288f";  //上一个方法中的 $key 本应该配置在 config文件中的
        $info = JWT::decode($jwt,$key,["HS512"]); //解密jwt
        return json($info);
    }

    /**
     * 获取文件列表
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function files(){
        $get=input('get.');
        if (!isset($get['folder']))$get['folder']="/";
        if (!isset($get['page']))$get['page']="1";
        $files=Db::table('data_files')->where('client_id',$this->client_id)->where('member_id',$this->member_id)->where('folder',$get['folder'])->limit(20)->page($get['page'])->select();
        $filesCount=Db::table('data_files')->where('client_id',$this->client_id)->where('member_id',$this->member_id)->where('folder',$get['folder'])->count();
        $countFiles=count($files);
        if (empty($files)){
            $foldersAry=$filesAll=[];
//            $files=Db::table('data_files')->where('client_id',$tot->client->id)->where('member_id',$tot->member->id)->where('folder',$get['folder'])->limit(1)->page(1)->select();
//            $folders=Db::table('file_folders')->where('member_id',$tot->member->id)->where('id',$files[0]['folder_id'])->find();
//            $foldersAll=Db::table('file_folders')->where('member_id',$tot->member->id)->select();
//            $foldersAry=getTree($foldersAll,$folders['id']);
        }else{
            $folders=Db::table('file_folders')->where('member_id',$this->member_id)->where('id',$files[0]['folder_id'])->find();
            $foldersAll=Db::table('file_folders')->where('member_id',$this->member_id)->select();
            $foldersAry=getTree($foldersAll,$folders['id']);
            foreach ($files as $k =>$v){
                $access_type=$v['access_type']==0?'private':'public';
                $filesAll[]=[
                    'id'=>$v['id'],
                    'access_type'=>$access_type,
                    'filename'=>$v['filename'],
                    'size'=>$v['size'],
                    'download_link'=>Config('env.oss_custom_host')."/".$access_type."/".$this->member_id."/".Config('env.app')."/".$v['id']."?".$v['download_url'],
                    'thumbnail'=>"",
                    'content_type'=>$v['content_type'],
                    'folder'=>$v['folder'],
                    'created_at'=>strtotime($v['created_at']),
                    'updated_at'=>strtotime($v['updated_at']),
                    'last_modified_time'=>$v['last_modified_time'],
                    'is_deleted'=>empty($v['deleted_at'])?'false':'true',
                    'mission_result'=>"",
                ];
            }
        }
        $data=[
            'folder'=>$get['folder'],
            'sub_folders'=>$foldersAry,
            'page'=>[
                'current_page'=>$get['page'],
                'page_size'=>20,
                'total_pages'=>$filesCount,
                'total'=>$countFiles
            ],
            'files'=>$filesAll,

        ];
         return show('17pdf', $code = "0,0",$msg ="",$errors = [],$data);
    }
    public function testCheck(){
        $get=input('get.');
        $tot=check($get['jwt']);
        dump($tot);
    }
    public function up()
    {
        return $this->fetch();
    }

    public function upload(){
        $file = request()->file('file');
        $info = $file->move('./uploads','');
        if ($info) {
            $path = $info->getSaveName();
            $filepath = 'http://localhost/kd/public/uploads/'.$info->getSaveName();
            $this->getFilename=$info->getFilename();
            $fileName = $this->client_name ."/".guid();
            $resInfo=$this->uploadFile(Config('env.aliyun_oss.Bucket'), $fileName, $info->getPathname());
//            Db::table('data_files')->where('member_id',$this->member_id)->where('client_id',$this->client_id)->where('etag',$resInfo['etag'])->
            dump($resInfo);
        } else {
            // 上传失败获取错误信息
            echo $file->getError();
        }
    }

    private function new_oss(){
        $oss=new \OSS\OssClient(Config('env.aliyun_oss.KeyId'),Config('env.aliyun_oss.KeySecret'),Config('env.aliyun_oss.Endpoint'));
        return $oss;
    }
    public function uploadFile($bucket,$object,$Path)
    {
        $options = array(
            'headers' => array(
                'Content-Disposition' => 'attachment; filename="'.$this->getFilename.'"',
                'x-oss-meta-self-define-title' => 'user define meta info',
            ));
        try {
            $ossClient = $this->new_oss();
            //uploadFile的上传方法
            $res = $ossClient->uploadFile($bucket, $object, $Path,$options);
            return $res;
        } catch (OssException $e) {
            //如果出错这里返回报错信息
            return $e->getMessage();
        }
    }
    public function uuid(){
        dump($this->client_name);
    }

}