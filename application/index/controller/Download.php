<?php
/**
 * Created by PhpStorm.
 * User: 陈大剩
 * Date: 2019/5/29
 * Time: 14:43
 */

namespace app\index\controller;
use think\Db;
use think\Controller;
class Download extends Controller
{
    public function index($name){
        if (empty($name))return;
        $find=Db::table('sharings')->where('short_url',$name)->find();
        if (empty($find)){
            return "1";
        }
        if ($find['publish_status']!==1){
            return "2";
        }
        try{
            $files=Db::table('data_files')->where('id',$find['data_file_id'])->whereNull('deleted_at')->find();
        }catch (\Exception $e){
            return "3";
        }
        if (empty($files)){
            return "4";
        }
        $app=Db::table('clients')->where('id',$files['client_id'])->field('code')->find();
        if (empty($find['secret'])){
            $this->assign('name',$files['filename']);
            $url=config('env.oss_custom_host')."/private/".$find['member_id']."/".$app['code']."/".$find['data_file_id'];
            $this->assign('url',$url);
            Db::table('sharings')->where('short_url',$name)->setInc('visit_times');
            return $this->fetch();
        }else{
            if (strtotime($find['expiration'])>=time()){
                return $this->redirect('/kd/public/download/'.$name);
            }else{
                return "5";
            }

        }
    }
    public function download($name){
        $msg="";
        if (request()->isPost()){
            $data=input('post.');
            $find=Db::table('sharings')->where('short_url',$name)->find();
            if ($data['share']['password']==$find['secret']){
                $files=Db::table('data_files')->where('id',$find['data_file_id'])->whereNull('deleted_at')->find();
                $app=Db::table('clients')->where('id',$files['client_id'])->field('code')->find();
                $url=config('env.oss_custom_host')."/private/".$find['member_id']."/".$app['code']."/".$find['data_file_id']."?".htmlspecialchars_decode($find['download_url']);
                $this->assign('name',$files['filename']);
                $this->assign('url',$url);
                Db::table('sharings')->where('short_url',$name)->setInc('visit_times');
                return $this->fetch('index');
            }else{
                $msg="密码错误,请输入正常的密码";
            }
        }
        $this->assign('msg',$msg);
        $this->assign('name',$name);
        return $this->fetch();
    }
}