<?php
/**
 * Created by PhpStorm.
 * User: 陈大剩
 * Date: 2019/5/29
 * Time: 14:43
 */

namespace app\index\controller\v1;
use think\Db;
use think\Controller;
class Download extends Controller
{
    public function index($name){
        if (empty($name))return;
        $find=Db::table('sharings')->where('short_url',$name)->find();
        if (empty($find)){
            return "没有数据";
        }
        if ($find['publish_status']!==1){
            return "已取消分享";
        }
        try{
            $files=Db::table('data_files')->where('id',$find['data_file_id'])->whereNull('deleted_at')->find();
        }catch (\Exception $e){
            return "文件不存在";
        }
        if (empty($files)){
            return "文件不存在";
        }
        $app=Db::table('clients')->where('id',$files['client_id'])->field('code')->find();
        if (empty($find['secret'])){
            $this->assign('name',$files['filename']);
            $url=get_oss_custom_host()."/private/".$find['member_id']."/".$app['code']."/".$find['data_file_id'];
            $this->assign('url',$url);
            Db::table('sharings')->where('short_url',$name)->setInc('visit_times');
            return $this->fetch('download/index');
        }else{
            if (strtotime($find['expiration'])>=time()){
                return $this->redirect(':3013/download/'.$name);
            }else{
                return "已过期";
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
                $url=get_oss_custom_host()."/private/".$find['member_id']."/".$app['code']."/".$find['data_file_id']."?".htmlspecialchars_decode($find['download_url']);
                $this->assign('name',$files['filename']);
                $this->assign('url',$url);
                Db::table('sharings')->where('short_url',$name)->setInc('visit_times');
                return $this->fetch('download/index');
            }else{
                $msg="密码错误,请输入正常的密码";
            }
        }
        $this->assign('msg',$msg);
        $this->assign('name',$name);
        return $this->fetch('download/download');
    }
}