<?php
namespace app\index\controller;
use think\Db;
use think\facade\Request;
use think\Config;
class Index extends Base
{
    /**
     * 文件目录
     * @var string
     */
    private $folder="";
    /**
     * 文件名
     * @var string
     */
    private $getFilename="";
    /**
     * 获取文件列表
     * User: 陈大剩
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function files(){
        $get=input('get.');
        if (!isset($get['folder']))$get['folder']="/";
        if (!isset($get['page']))$get['page']="1";
        $get['folder']=$this->screen($get['folder']);
        $foldersAry=$this->fileTree($get['folder'],0);
        $this->folder=$get['folder'];
        $files=Db::table('data_files')->where('client_id',$this->client_id)->where('member_id',$this->member_id)->where('folder',$get['folder'])->limit(20)->page($get['page'])->select();
        $filesCount=Db::table('data_files')->where('client_id',$this->client_id)->where('member_id',$this->member_id)->where('folder',$get['folder'])->count();
        $countFiles=count($files);
        $filesAll=[];
        foreach ($files as $k =>$v){
            $access_type=$v['access_type']==0?'private':'public';
            $filesAll[]=[
                'id'=>$v['id'],
                'access_type'=>$access_type,
                'filename'=>$v['filename'],
                'size'=>$v['size'],
                'download_link'=>Config('env.oss_custom_host')."/".$access_type."/".$this->member_id."/".$this->client_name."/".$v['id']."?".$v['download_url'],
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
    public function upload(){
        $post=input('post.');
        $file = request()->file('file');
        $info = $file->move('./uploads','');
        if ($info) {
            $path = $info->getSaveName();
            $filepath = 'http://localhost/kd/public/uploads/'.$info->getSaveName();
            $this->getFilename=$info->getFilename();
            $fileName = $this->client_name ."/".guid();
            $resInfo=$this->uploadFile(Config('env.aliyun_oss.Bucket'), $fileName, $info->getPathname());
            list($download_head,$download_url)=explode("?",$resInfo['signedUrl']);
            $findFiles=Db::table('data_files')
                ->where('member_id',$this->member_id)
                ->where('client_id',$this->client_id)
                ->where('etag',$resInfo['etag'])
                ->where('folder',"/")
                ->where('suffix',$info->getExtension())
                ->find();
            $post['folder']="/up/up/up/up/test";
            $post['folder'] =$this->screen($post['folder']);
            $id=$this->directory($post['folder']);
            if (count($findFiles)>=1){
                echo 1;
            }else{
                $data=[
                    'client_id'=>$this->client_id,
                    'member_id'=>$this->member_id,
                    'etag'=>$resInfo['etag'],
                    'access_type'=>0,
                    'filename'=>$info->getFilename(),
                    'size'=>$resInfo['info']['size_upload'],
                    'content_type'=>$resInfo['oss-requestheaders']['Content-Type'],
                    'folder'=>$post['folder'],
                    'download_url'=>$download_url,
                    'created_at'=>date('Y-m-d H:i:s.u'),
                    'updated_at'=>date('Y-m-d H:i:s.u'),
                    'file'=>$fileName,
                    'folder_id'=>$id,
                    'last_modified_time'=>time(),
                    'suffix'=> $info->getExtension(),
                ];
                Db::table('data_files')->insertGetId($data);
            }
            // 上传失败获取错误信息
            echo $file->getError();
        }
    }

    /**
     * OSS实例
     * User: 陈大剩
     * @return \OSS\OssClient
     */
    private function new_oss(){
        $oss=new \OSS\OssClient(Config('env.aliyun_oss.KeyId'),Config('env.aliyun_oss.KeySecret'),Config('env.aliyun_oss.Endpoint'),false);
        return $oss;
    }

    /**
     * 阿里云上传接口
     * User: 陈大剩
     * @param $bucket
     * @param $object
     * @param $Path
     * @return null|\think\response\Json
     * @throws \OSS\Core\OssException
     */
    public function uploadFile($bucket,$object,$Path)
    {
        $options = array(
            'headers' => array(
                'Content-Disposition' => 'attachment; filename="'.$this->getFilename.'"',
                'x-oss-meta-self-define-title' => 'user define meta info',
            ));
        try {
            $ossClient = $this->new_oss();
            $res = $ossClient->uploadFile($bucket, $object, $Path,$options);
            $signedUrl = $ossClient->signUrl($bucket, $object, 3153600000);
            $res['signedUrl'] = htmlspecialchars_decode($signedUrl);
            return $res;
        } catch (OssException $e) {
            return errorMsg('101',$e->getMessage(),400);
        }
    }
    /**
     * 递归创建目录
     * User: 陈大剩
     * @param string $folder
     * @param int $parent_id
     * @return \think\response\Json|void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function directory($folder="",$parent_id=0){
        if (empty($folder)){
            return $parent_id;
        }
        $folderAry=explode("/",$folder);
        $fist=array_shift($folderAry);
        $newdir=implode('/', $folderAry);
        if (empty($fist)){
            $findFolder=Db::table('file_folders')->where('member_id',$this->member_id)->where('parent_id',0)->where('name',$this->member_id)->find();
        }else{
            $findFolder=Db::table('file_folders')->where('member_id',$this->member_id)->where('name',$fist)->where('parent_id',$parent_id)->find();
        }
        if (empty($fist)){
            if (empty($findFolder)){
                $data=[
                    'id'=>guid(),
                    'parent_id'=>0,
                    'member_id'=>$this->member_id,
                    'name'=>$this->member_id,
                    'created_at'=>date('Y-m-d H:i:s.u'),
                    'updated_at'=>date('Y-m-d H:i:s.u'),
                ];
                try{
                    $res=Db::table('file_folders')->insertGetId($data);
                    $this->directory($newdir,$data['id']);
                }catch (OssException $e) {
                    return show($this->client_name,"100.0",'',[$e->getMessage()],[],400);
                }
            }else{
                return $this->directory($newdir,$findFolder['id']);
            }
        }else{
            if (empty($findFolder)){
                $data=[
                    'id'=>guid(),
                    'parent_id'=>$parent_id,
                    'member_id'=>$this->member_id,
                    'name'=>$fist,
                    'created_at'=>date('Y-m-d H:i:s.u'),
                    'updated_at'=>date('Y-m-d H:i:s.u'),
                ];
                try{
                    $res=Db::table('file_folders')->insertGetId($data);
                    $this->directory($newdir,$data['id']);
                    if (end($folderAry)==""){
                         return $data['id'];
                    }
                }catch (OssException $e) {
                    return show($this->client_name,"100.0",'',[$e->getMessage()],[],400);
                }
            }else{
                return $this->directory($newdir,$findFolder['id']);
            }
        }
    }

    /**
     * 递归获取文件夹
     * User: 陈大剩
     * @param string $folder
     * @param int $parent_id
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function fileTree($folder="/up",$parent_id=0){
        $folderAry=explode("/",$folder);
        $fist=array_shift($folderAry);
        $newdir=implode('/', $folderAry);
        if (empty($fist)){
            $findFolder=Db::table('file_folders')->where('member_id',$this->member_id)->where('parent_id',0)->where('name',$this->member_id)->find();
        }else{
            $findFolder=Db::table('file_folders')->where('member_id',$this->member_id)->where('name',$fist)->where('parent_id',$parent_id)->find();
        }
        if (empty($findFolder)){
            return [];
        }else{
            if (count($folderAry)==0){
                $data=Db::table('file_folders')->where('parent_id',$findFolder['id'])->where('member_id',$this->member_id)->select();
                $tree = [];
                foreach($data as $k => $v)
                {
                    $count=Db::table('data_files')->where('folder_id',$v['id'])->where('member_id',$this->member_id)->where('folder',$this->folder)->where('client_id',$this->client_id)->count();
                    unset($v['parent_id']);
                    unset($v['member_id']);
                    unset($v['created_at']);
                    unset($v['updated_at']);
                    unset($v['deleted_at']);
                    $v['files_count']=$count;
                    $tree[] = $v;
                }
                return $tree;
            }else{
                return $this->fileTree($newdir,$findFolder['id']);
            }
        }
    }

    /**
     * 去掉前后斜杠
     * User: 陈大剩
     * @param $str
     * @return string
     */
    public function screen($str){
        if (!empty($str)){
            if ($str!=="/"){
                $newFolder=explode("/",$str);
                if (end($newFolder)==""){
                    array_pop($newFolder);
                    $str=implode("/",$newFolder);
                }
                if ($newFolder[0]!==""){
                    $str="/".$str;
                }
            }
        }else{
            $str="/";
        }
        return $str;
    }
}