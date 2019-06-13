<?php

namespace app\index\controller\v1;

use app\index\controller\Base;
use think\Db;
use app\common\controller\ApiException;

class Index extends Base
{
    /**
     * 文件目录
     * @var string
     */
    private $folder = "";
    /**
     * 文件名
     * @var string
     */
    private $getFilename = "";
    private $filesId = [];
    private $filesList = [];
    public function index(){
        return ;
    }
    /**
     * 获取文件列表
     * User: 陈大剩
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function files()
    {
        $get = input('get.');
        if (!isset($get['folder'])) $get['folder'] = "/";
        if (!isset($get['page'])) $get['page'] = "1";
        if (!isset($get['per'])) $get['per'] = "20";
        $get['folder'] = $this->screen($get['folder']);
        if (isset($get['target_app'])){
            $app_id = Db::table('clients')->where('code', $get['target_app'])->field('code,id')->find();
            if (!empty($app_id)) {
                $this->client_id=$app_id['id'];
                $this->client_name=$get['target_app'];
            }
        }
        $foldersAry = $this->fileTree($get['folder'], 0);
        $this->folder = $get['folder'];
        $where = $statistic_folders = [];
        if (isset($get['last_refresh_time'])) {
            if (strlen($get['last_refresh_time']) < 10) throw new ApiException("请传入有效时间戳", 500);
            $where[] = ["updated_at", ">=", date("Y-m-d H:i:s", $get['last_refresh_time'])];
        }
        if (isset($get['statistic_folders'])) {
            $get['statistic_folders'] = intval($get['statistic_folders']);
        }
        foreach ($foldersAry as $k => $v) {
            if ($v['name'] == 'Converted') {
                $statistic_folders[] = $v;
            }
        }
        $files = Db::table('data_files')->whereNotNull('size')->whereNull('deleted_at')->where('client_id', $this->client_id)->where('member_id', $this->member_id)->where('folder', $get['folder'])->where($where)->limit($get['per'])->page($get['page'])->select();
        $filesCount = Db::table('data_files')->whereNotNull('size')->whereNull('deleted_at')->where('client_id', $this->client_id)->where('member_id', $this->member_id)->where('folder', $get['folder'])->count();
        $countFiles = count($files);
        $filesAll = [];
        foreach ($files as $k => $v) {
            $access_type = $v['access_type'] == 0 ? 'private' : 'public';
            $filesAll[] = [
                'id' => $v['id'],
                'access_type' => $access_type,
                'filename' => $v['filename'],
                'size' => round($v['size'],3),
                'download_link' => get_oss_custom_host() . "/" . $access_type . "/" . $this->member_id . "/" . $this->client_name . "/" . $v['id'] . "?" . $v['download_url'],
                'thumbnail' => "",
                'content_type' => $v['content_type'],
                'folder' => $v['folder'],
                'created_at' => strtotime($v['created_at']),
                'updated_at' => strtotime($v['updated_at']),
                'last_modified_time' => $v['last_modified_time'],
                'is_deleted' => empty($v['deleted_at']) ? false : true,
                'mission_result' => null,
            ];
        }
        if (isset($get['statistic_folders'])) {
            $get['statistic_folders'] = intval($get['statistic_folders']);
            if ($get['statistic_folders'] == 1 && $get['folder'] = "/") {
                $findFolder = Db::table('file_folders')->whereNull('deleted_at')->where('member_id', $this->member_id)->where('parent_id', 0)->where('name', $this->member_id)->find();
                $statistic_folders[] = [
                    'id' => $findFolder['id'],
                    'name' => "home",
                    'files_count' => $filesCount + count($filesAll)
                ];
                $data = [
                    'folder' => $get['folder'],
                    'sub_folders' => $foldersAry,
                    'statistic_folders' => $statistic_folders,
                    'page' => [
                        'current_page' => $get['page'],
                        'page_size' => round($get['per'],3),
                        'total_pages' => ceil($filesCount / $get['per']),
                        'total' => $countFiles
                    ],
                    'files' => $filesAll,
                ];
            }
        } else {
            $data = [
                'folder' => $get['folder'],
                'sub_folders' => $foldersAry,
                'page' => [
                    'current_page' => $get['page'],
                    'page_size' => $get['per'],
                    'total_pages' => ceil($filesCount / $get['per']),
                    'total' => $countFiles
                ],
                'files' => $filesAll,
            ];
        }
        return show($this->client_name, $code = "0,0", $msg = "", $errors = [], $data);
    }

    /**
     * 上传接口v1
     * User: 陈大剩
     * @return \think\response\Json
     * @throws ApiException
     * @throws \OSS\Core\OssException
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function upload()
    {
        $post = input('post.');
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
            }
        }
        if (!isset($post['folder'])) $post['folder'] = "";
        $post['folder'] = $this->screen($post['folder']);
        if (isset($post['remote_url'])) {
            $file = get_files($post['remote_url']);
            if ($file) {
                $getExtension = substr(strrchr($file, '.'), 1);
                $getPathname = './' . $file;
                $info = explode(DS, $file);
                $getFilename = $info[1];
            } else {
                if (empty($file)) {
                    $errors = [
                        'type' => '300,3',
                        'msg' => "Remote_url is empty"
                    ];
                    return show($this->client_name, $errors['type'], $errors['msg'], $errors);
                }
            }
        } else {
            $file = request()->file('file');
            if (!empty($file)) {
                $info = $file->move('./uploads', '');
            }
        }

        if (isset($post['uuid']) && empty($file)) {
            $fileName = "private" . "/" . $this->member_id . "/" . $this->client_name . "/" . $post['uuid'];
            if (isset($post['target_app'])) {
                $toFileName = "private" . $this->member_id . "/" . $post['target_app'] . "/" . $post['uuid'];
                $client_id = $appName['id'];
            } else {
                $client_id = $this->client_id;
                $toFileName = $fileName;
            }
            $oneFiles = Db::table('data_files')->whereNull('deleted_at')->where('id', $post['uuid'])->find();
            if (isset($post['filename'])) {
                $name = $post['filename'];
            } else {
                $name = $oneFiles['filename'];
            }
            $id = $this->directory($post['folder']);
            if (isset($post['modify_time'])) {
                $edit = [
                    'folder' => $post['folder'],
                    'folder_id' => $id,
                    'client_id' => $client_id,
                    'last_modified_time' => $post['modify_time']
                ];
            } else {
                $edit = [
                    'folder' => $post['folder'],
                    'folder_id' => $id,
                    'client_id' => $client_id,
                ];
            }
            if (isset($post['filename']) || isset($post['target_app'])) {
                $this->editObject(Config('env.aliyun_oss.Bucket'), $fileName, Config('env.aliyun_oss.Bucket'), $toFileName, $name, $oneFiles['content_type']);
            }
            $resFiles = Db::table('data_files')->whereNotNull('size')->whereNull('deleted_at')->where('id', $post['uuid'])->update($edit);
            if ($resFiles) {
                $access_type = $oneFiles['access_type'] == 0 ? 'private' : 'public';
                $resAll[] = [
                    'id' => $oneFiles['id'],
                    'access_type' => $access_type,
                    'filename' => $name,
                    'size' => round($oneFiles['size'],3),
                    'download_link' => get_oss_custom_host() . "/" . $access_type . "/" . $this->member_id . "/" . $this->client_name . "/" . $oneFiles['id'] . "?" . $oneFiles['download_url'],
                    'thumbnail' => "",
                    'content_type' => $oneFiles['content_type'],
                    'folder' => $post['folder'],
                    'created_at' => strtotime($oneFiles['created_at']),
                    'updated_at' => strtotime($oneFiles['updated_at']),
                    'last_modified_time' => $oneFiles['last_modified_time'],
                    'is_deleted' => empty($oneFiles['deleted_at']) ? false : true,
                    'mission_result' => "",
                ];
                $count = Db::table('data_files')->whereNotNull('size')->whereNull('deleted_at')->where('client_id', $this->client_id)->where('member_id', $this->member_id)->sum('size');
                Db::table('members')->whereNull('deleted_at')->where('id', $this->member_id)->data(['used_space' => $count])->update();
                return show($this->client_name, $code = "0,0", $msg = "", $errors = [], $resAll);
            } else {
                throw new ApiException("Internal Server Error", 500);
            }
        } else {
            if (isset($post['target_app'])) {
                $this->client_name = $post['target_app'];
            }
            if (empty($file)) {
                $errors = [
                    'type' => '300,3',
                    'msg' => "File is empty"
                ];
                return show($this->client_name, $errors['type'], $errors['msg'], $errors);
            }
            if ($info) {
                if (count($info) !== 2) {
                    $getFilename = $info->getFilename();
                    $getPathname = $info->getPathname();
                    $getExtension = $info->getExtension();
                }
                if (isset($post['uuid'])) {
                    $uuid = $post['uuid'];
                } else {
                    $uuid = guid();
                }
                $str =strtoupper(md5(file_get_contents($info->getPathname())));
                $findFiles = Db::table('data_files')
                    ->whereNull('deleted_at')
                    ->whereNotNull('size')
                    ->where('member_id', $this->member_id)
                    ->where('client_id', $this->client_id)
                    ->where('etag',$str)
                    ->where('folder', $post['folder'])
                    ->where('suffix', $info->getExtension())
                    ->select();
                if (count($findFiles) >= 1){
                    $fileName = "private" . "/" . $this->member_id . "/" . $this->client_name . "/" . $findFiles[0]['id'];
                    if (isset($post['target_app'])) {
                        $toFileName = "private" . $this->member_id . "/" . $post['target_app'] . "/" . $post['uuid'];
                        $client_id = $appName['id'];
                    } else {
                        $client_id = $this->client_id;
                        $toFileName = $fileName;
                    }
                    $id = $this->directory($post['folder']);
                    if (isset($post['filename'])) {
                        $name = $post['filename'];
                        $ossClient = new \OSS\OssClient(Config('env.aliyun_oss.KeyId'), Config('env.aliyun_oss.KeySecret'), Config('env.aliyun_oss.Endpoint'), false);
                        $options = array(
                            'headers' => array(
                                'Content-Disposition' => 'attachment; filename="' . $name . '"',
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
                    } else {
                        $name = $findFiles[0]['filename'];
                    }
                    if (isset($post['modify_time'])) {
                        $edit = [
                            'folder' => $post['folder'],
                            'folder_id' => $id,
                            'filename'=>$name,
                            'client_id' => $client_id,
                            'last_modified_time' => $post['modify_time']
                        ];
                    } else {
                        $edit = [
                            'folder' => $post['folder'],
                            'folder_id' => $id,
                            'filename'=>$name,
                            'client_id' => $client_id,
                        ];
                    }
                    $access_type = $oneFiles['access_type'] == 0 ? 'private' : 'public';
                    $filesAll[] = [
                        'id' => $findFiles[0]['id'],
                        'access_type' => $access_type,
                        'filename' => $name,
                        'size' => round($findFiles[0]['size'],3),
                        'download_link' => get_oss_custom_host() . "/" . $access_type . "/" . $this->member_id . "/" . $this->client_name . "/" . $findFiles[0]['id'] . "?" . $findFiles[0]['download_url'],
                        'thumbnail' => "",
                        'content_type' => $findFiles[0]['content_type'],
                        'folder' => $findFiles[0]['folder'],
                        'created_at' => strtotime($findFiles[0]['created_at']),
                        'updated_at' => strtotime($findFiles[0]['updated_at']),
                        'last_modified_time' => $findFiles[0]['last_modified_time'],
                        'is_deleted' => empty($findFiles[0]['deleted_at']) ? false : true,
                        'mission_result' => "",
                    ];
                    $resFiles = Db::table('data_files')->whereNotNull('size')->whereNull('deleted_at')->where('id', $findFiles[0]['id'])->update($edit);
                    return show($this->client_name, $code = "0,0", $msg = "", $errors = [], $filesAll);
                }
                $this->getFilename = $getFilename;
                if (isset($post['filename'])) {
                    $this->getFilename = $post['filename'];
                }
                $fileName = "private" . "/" . $this->member_id . "/" . $this->client_name . "/" . $uuid;
                $resInfo = $this->uploadFile(Config('env.aliyun_oss.Bucket'), $fileName, $getPathname);
                list($download_head, $download_url) = explode("?", $resInfo['signedUrl']);
                $id = $this->directory($post['folder']);
                if (empty($id)) {
                    throw new ApiException("Internal Server MyError", 500);
                }
                if (count($findFiles) >= 1) {
                    $this->FoldeDell($uuid);
                    throw new ApiException("文件夹中已有相同文件", 400);
                } else {
                    $data = [
                        'id' => $uuid,
                        'client_id' => $this->client_id,
                        'member_id' => $this->member_id,
                        'etag' => $this->etag($resInfo['etag']),
                        'access_type' => 0,
                        'filename' => $this->getFilename,
                        'size' => round($resInfo['info']['size_upload'] / 1024,3),
                        'content_type' => $resInfo['oss-requestheaders']['Content-Type'],
                        'folder' => $post['folder'],
                        'download_url' => $download_url,
                        'created_at' => date('Y-m-d H:i:s.u'),
                        'updated_at' => date('Y-m-d H:i:s.u'),
                        'file' => $fileName,
                        'folder_id' => $id,
                        'last_modified_time' => $last_modified_time,
                        'suffix' => $getExtension,
                    ];
                    if (isset($post['uuid'])) {
                        $res = Db::table('data_files')->whereNotNull('size')->whereNull('deleted_at')->where('id', $uuid)->update($data);
                    } else {
                        $res = Db::table('data_files')->insert($data);
                    }
                    if ($res) {
                        $access_type = $data['access_type'] == 0 ? 'private' : 'public';
                        $filesAll[] = [
                            'id' => $data['id'],
                            'access_type' => $access_type,
                            'filename' => $data['filename'],
                            'size' => round($data['size'],3),
                            'download_link' => get_oss_custom_host() . "/" . $access_type . "/" . $this->member_id . "/" . $this->client_name . "/" . $data['id'] . "?" . $data['download_url'],
                            'thumbnail' => "",
                            'content_type' => $data['content_type'],
                            'folder' => $data['folder'],
                            'created_at' => strtotime($data['created_at']),
                            'updated_at' => strtotime($data['updated_at']),
                            'last_modified_time' => $data['last_modified_time'],
                            'is_deleted' => empty($data['deleted_at']) ? false : true,
                            'mission_result' => "",
                        ];
                        try {
                            $count = Db::table('data_files')->whereNotNull('size')->whereNull('deleted_at')->where('client_id', $this->client_id)->where('member_id', $this->member_id)->sum('size');
                            Db::table('members')->where('id', $this->member_id)->data(['used_space' => $count])->update();
                        } catch (\Exception $e) {

                        }

                        return show($this->client_name, $code = "0,0", $msg = "", $errors = [], $filesAll);
                    }
                }
                // 上传失败获取错误信息
            } else {
                throw new ApiException($file->getError(), 400);
            }
        }
    }

    /**
     * OSS实例
     * User: 陈大剩
     * @return \OSS\OssClient
     */
    private function new_oss()
    {
        $oss = new \OSS\OssClient(Config('env.aliyun_oss.KeyId'), Config('env.aliyun_oss.KeySecret'), Config('env.aliyun_oss.Endpoint'), false);
        return $oss;
    }

    /**
     * 修改文件接口
     * User: 陈大剩
     * @param $fromBucket
     * @param $fromObject
     * @param $toBucket
     * @param $toObject
     * @param $name
     * @param $Type
     * @return string|\think\response\Json
     * @throws \OSS\Core\OssException
     */
    public function editObject($fromBucket, $fromObject, $toBucket, $toObject, $name, $Type)
    {
        $options = array(
            'headers' => array(
                'Content-Type' => $Type,
                'Content-Disposition' => 'attachment; filename="' . $name . '"'
            ));
        try {
            $ossClient = $this->new_oss();
            $res = $ossClient->copyObject($fromBucket, $fromObject, $toBucket, $toObject, $options);
            if ($res) return "1";
        } catch (\Exception $e) {
            return errorMsg('101', $e->getMessage(), 400);
        }
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
    public function uploadFile($bucket, $object, $Path)
    {
        $options = array(
            'headers' => array(
                'Content-Disposition' => 'attachment; filename="' . $this->getFilename . '"',
                'x-oss-meta-self-define-title' => 'user define meta info',
            ));
        try {
            $ossClient = $this->new_oss();
            $res = $ossClient->uploadFile($bucket, $object, $Path, $options);
            $signedUrl = $ossClient->signUrl($bucket, $object, 3153600000);
            $res['signedUrl'] = htmlspecialchars_decode($signedUrl);
            return $res;
        } catch (OssException $e) {
            return errorMsg('101', $e->getMessage(), 400);
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
    public function fileTree($folder = "/up", $parent_id = 0)
    {
        $folderAry = explode("/", $folder);
        $fist = array_shift($folderAry);
        $newdir = implode('/', $folderAry);
        if (empty($fist)) {
            $findFolder = Db::table('file_folders')->whereNull('deleted_at')->where('member_id', $this->member_id)->where('parent_id', 0)->where('name', $this->member_id)->find();
        } else {
            $findFolder = Db::table('file_folders')->whereNull('deleted_at')->where('member_id', $this->member_id)->where('name', $fist)->where('parent_id', $parent_id)->find();
        }
        if (empty($findFolder)) {
            return [];
        } else {
            if (count($folderAry) == 0) {
                $data = Db::table('file_folders')->whereNull('deleted_at')->where('parent_id', $findFolder['id'])->where('member_id', $this->member_id)->select();
                $tree = [];
                foreach ($data as $k => $v) {
                    $count = Db::table('data_files')->whereNotNull('size')->whereNull('deleted_at')->where('folder_id', $v['id'])->where('member_id', $this->member_id)->where('client_id', $this->client_id)->count();
                    $countWj = Db::table('file_folders')->whereNull('deleted_at')->where('parent_id', $v['id'])->where('member_id', $this->member_id)->count();
                    unset($v['parent_id']);
                    unset($v['member_id']);
                    unset($v['created_at']);
                    unset($v['updated_at']);
                    unset($v['deleted_at']);
                    $v['files_count'] = intval($count) + intval($countWj);
                    $tree[] = $v;
                }
                return $tree;
            } else {
                return $this->fileTree($newdir, $findFolder['id']);
            }
        }
    }

    /**
     * 递归获取文件夹当前id
     * User: 陈大剩
     * @param string $folder
     * @param int $parent_id
     * @return int|mixed|void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function deleteTree($folder = "/up", $parent_id = 0)
    {
        if (empty($folder)) {
            return $parent_id;
        }
        $folderAry = explode("/", $folder);
        $fist = array_shift($folderAry);
        $newdir = implode('/', $folderAry);
        if (empty($fist)) {
            $findFolder = Db::table('file_folders')->whereNull('deleted_at')->where('member_id', $this->member_id)->where('parent_id', 0)->where('name', $this->member_id)->find();
        } else {
            $findFolder = Db::table('file_folders')->whereNull('deleted_at')->where('member_id', $this->member_id)->where('name', $fist)->where('parent_id', $parent_id)->find();
        }
        if (empty($findFolder)) {
            return;
        } else {
            if (empty($newdir)) {
                return $findFolder['id'];
            } else {
                return $this->deleteTree($newdir, $findFolder['id']);
            }
        }
    }

    /**
     * 递归查询子文件id
     * User: 陈大剩
     * @param string $id
     * @return string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function deleteFile($id = "6c30f291-9c66-42f8-a068-0d6d018efa16")
    {
        $data = Db::table('file_folders')->whereNull('deleted_at')->where('member_id', $this->member_id)->where('parent_id', $id)->field('id')->select();
        if (empty($data)) {
            return $this->filesId[] = $id;
        } else {
            foreach ($data as $k => $v) {
                $this->deleteFile($v['id']);
            }
            return $this->filesId[] = $id;
        }
    }

    /**
     * 文件列表
     * User: 陈大剩
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function fileList()
    {
        foreach ($this->filesId as $k => $v) {
            $id = Db::table('data_files')->whereNotNull('size')->whereNull('deleted_at')->where('folder_id', $v)->field('id')->select();
            foreach ($id as $val) {
                $this->filesList[] = $val['id'];
            }
        }
    }

    /**
     * etag格式化
     * User: 陈大剩
     * @param $etag
     * @return mixed
     */
    public function etag($etag)
    {
        $data = explode("\"", $etag);
        return $data[1];
    }

    /**
     * 文件详情
     * User: 陈大剩
     * @param $id
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function filesInfo($id)
    {
        try {
            $files = Db::table('data_files')->whereNotNull('size')->whereNull('deleted_at')->where('id', $id)->find();
        } catch (Exception $e) {
            return errorMsg('101', $e->getMessage(), 400);
        }
        $resAll = $errors = [];
        if (empty($files)) {
            $errors = [
                'type' => '300,0',
                'msg' => "DataFile Not Exists"
            ];
            return show($this->client_name, $errors['type'], $errors['msg'], $errors);
        } else {
            $access_type = $files['access_type'] == 0 ? 'private' : 'public';
            $resAll = [
                'id' => $files['id'],
                'access_type' => $access_type,
                'filename' => $files['filename'],
                'size' => round($files['size'],3),
                'download_link' => get_oss_custom_host() . "/" . $access_type . "/" . $this->member_id . "/" . $this->client_name . "/" . $files['id'] . "?" . $files['download_url'],
                'thumbnail' => "",
                'content_type' => $files['content_type'],
                'folder' => $files['folder'],
                'created_at' => strtotime($files['created_at']),
                'updated_at' => strtotime($files['updated_at']),
                'last_modified_time' => $files['last_modified_time'],
                'is_deleted' => empty($files['deleted_at']) ? false : true,
                'mission_result' => "",
            ];
        }

        return show($this->client_name, $code = "0,0", $msg = "", [], $resAll);
    }

    /**
     * 用户空间使用情况统计接口v1
     * User: 陈大剩
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function me()
    {
        $used_space = Db::table('members')->where('id', $this->member_id)->field('used_space')->find();
        $data = [
            'used_space' => round($used_space['used_space'],2)
        ];
        return show($this->client_name, $code = "0,0", $msg = "", [], $data);
    }

    /**
     * 批量删除接口
     * User: 陈大剩
     * @throws ApiException
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     * @throws null
     */
    public function batch_delete()
    {
        $all = $filesId = $tot = [];
        $headers = request()->getContent();
        $data = json_decode($headers, true);
        if (empty($data)) throw new ApiException('Value is empty', 400);
        foreach ($data['data'] as $k => $v) {
            if (isset($v['uuid'])) {
                $tot[] = $this->batchUuid($v['uuid']);
            } else {
                $folder = $this->screen($v['folder']);
                $id = $this->deleteTree($folder);
                if (empty($id)) {
                    $tot[] = [
                        'folder' => $folder,
                        'exists' => false
                    ];
                } else {
                    $tot[] = $this->batchFolder($folder, $id);
                }
            }
        }
        try {
            $count = Db::table('data_files')->whereNotNull('size')->whereNull('deleted_at')->where('client_id', $this->client_id)->where('member_id', $this->member_id)->sum('size');
            Db::table('members')->where('id', $this->member_id)->data(['used_space' => $count])->update();
        } catch (\Exception $e) {

        }
        return show($this->client_name, $code = "0,0", $msg = "", [], $tot);
    }

    public function batchFolder($folder, $id)
    {
        $this->deleteFile($id);
        $this->fileList();
        foreach ($this->filesList as $val) {
            $all[] = "private" . "/" . $this->member_id . "/" . $this->client_name . "/" . $val;
        }
        if (empty($all)) {
            if ($folder !== "/Converted") {
                $Sqlfolders = Db::table('file_folders')->whereNull('deleted_at')->where('id', 'in', $this->filesId)->update(['deleted_at' => date('Y-m-d H:i:s')]);
            }
        } else {
            $ossClient = $this->new_oss();
            try {
                $res = $ossClient->deleteObjects(Config('env.aliyun_oss.Bucket'), $all);
            } catch (OssException $e) {
                throw new ApiException($e->getMessage(), 400);
            }
            if (count($res) == count($all)) {
                foreach ($all as $v) {
                    $ids = explode("/", $v);
                    $filesId[] = $ids[1];
                }
                $sqlRes = Db::table('data_files')->whereNotNull('size')->where('folder', '<>', '/Converted')->whereNull('deleted_at')->where('id', 'in', $this->filesList)->update(['deleted_at' => date('Y-m-d H:i:s')]);
                if ($folder !== "/Converted") {
                    $Sqlfolders = Db::table('file_folders')->whereNull('deleted_at')->where('id', 'in', $this->filesId)->update(['deleted_at' => date('Y-m-d H:i:s')]);
                }
            } else {
                $tot = [
                    'folder' => $folder,
                    'exists' => false
                ];
                return $tot;
            }
        }
        if (!empty($sqlRes) || !empty($Sqlfolders)) {
            $tot = [
                'folder' => $folder,
                'exists' => true
            ];
            return $tot;
        } else {
            $tot = [
                'folder' => $folder,
                'exists' => true
            ];
            return $tot;
        }
    }

    public function batchUuid($uuid)
    {
        try {
            $res = Db::table('data_files')->whereNotNull('size')->where('folder', '<>', '/Converted')->whereNull('deleted_at')->where('id', $uuid)->update(['deleted_at' => date('Y-m-d H:i:s')]);
        } catch (\Exception $e) {
            $tot = [
                'uuid' => $uuid,
                'exists' => 'uuid 格式错误'
            ];
            return $tot;
        }

        if ($res) {
            $all[] = "private" . "/" . $this->member_id . "/" . $this->client_name . "/" . $uuid;
            $ossClient = $this->new_oss();
            try {
                $res = $ossClient->deleteObjects(Config('env.aliyun_oss.Bucket'), $all);
            } catch (\Exception $e) {
                throw new ApiException($e->getMessage(), 400);
            }
            if (count($res) == count($all)) {
                $tot = [
                    'uuid' => $uuid,
                    'exists' => true
                ];
                return $tot;
            } else {
                $tot = [
                    'uuid' => $uuid,
                    'exists' => false
                ];
                return $tot;
            }
        } else {
            $tot = [
                'uuid' => $uuid,
                'exists' => false
            ];
            return $tot;
        }
    }

    /**
     * 根据UUID删除文件
     * User: 陈大剩
     * @param $uuid
     * @throws ApiException
     * @throws null
     */
    public function FoldeDell($uuid)
    {
        if ($uuid) {
            $all[] = "private" . "/" . $this->member_id . "/" . $this->client_name . "/" . $uuid;
            $ossClient = $this->new_oss();
            try {
                $res = $ossClient->deleteObjects(Config('env.aliyun_oss.Bucket'), $all);
            } catch (\Exception $e) {
                throw new ApiException($e->getMessage(), 400);
            }
        }
    }

    /**
     * 移动文件夹／重命名文件夹
     * User: 陈大剩
     * @return \think\response\Json
     * @throws ApiException
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function moveFolder()
    {
        $raw = request()->getContent();
        $rawAry = json_decode($raw, true);
        if (isset($rawAry['source_folder']) && isset($rawAry['target_folder'])) {
            $source_folder = $this->screen($rawAry['source_folder']);
            $folderId = $this->deleteTree($source_folder);
            if (empty($folderId)) {
                $errors = [
                    'type' => '500,5',
                    'msg' => 'Source Folder Not Exists'
                ];
                return show($this->client_name, $errors['type'], $msg = "", $errors, []);
            }
            $newtarget_folder = $this->screen($rawAry['target_folder']);
            $newId = $this->directory($newtarget_folder, 0);
            $foldersRes = Db::table('file_folders')->where('parent_id', $folderId)->where('member_id', $this->member_id)->update(['parent_id' => $newId]);
            $filesRes = Db::table('data_files')->whereNotNull('size')->where('folder_id', $folderId)->where('member_id', $this->member_id)->where('client_id', $this->client_id)->update(['folder_id' => $newId, 'folder' => $newtarget_folder]);
            if (!$foldersRes && !$filesRes) {
                throw new ApiException('Source Folder Not Exists', 400);
            }
            $this->checkFolder($folderId);
            $data = [
                'source_folder' => $source_folder,
                'target_folder' => $newtarget_folder
            ];
            return show($this->client_name, '0,0', $msg = "", [], $data);
        } else {
            throw new ApiException('Value is empty', 400);
        }
    }

    /**
     * 目录除空
     * User: 陈大剩
     * @param $folder_id
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function checkFolder($folder_id)
    {
        $files = Db::table('data_files')->whereNotNull('size')->whereNull('deleted_at')->where('client_id', $this->client_id)->where('member_id', $this->member_id)->where('folder_id', $folder_id)->select();
        $folders = Db::table('file_folders')->whereNull('deleted_at')->where('parent_id', $folder_id)->where('member_id', $this->member_id)->select();
        if (empty($files) && empty($folders)) {
            $res = Db::table('file_folders')->where('name', '<>', 'Converted')->whereNull('deleted_at')->where('id', $folder_id)->where('member_id', $this->member_id)->update(['deleted_at' => date("Y-m-d H:i:s")]);
        }
    }

    /**
     * 批量更新（移动/重命名文件）
     * User: 陈大剩
     * @return \think\response\Json
     */
    public function batch_update()
    {
        $raw = request()->getContent();
        if (empty($raw)) {
            $errors = [
                'type' => '500,5',
                'msg' => 'Source Folder Not Exists'
            ];
            return show($this->client_name, $errors['type'], $msg = "", $errors, []);
        }
        $rawAry = json_decode($raw, true);
        if (empty($rawAry['data'])) {
            $errors = [
                'type' => '500,5',
                'msg' => 'Source Folder Not Exists'
            ];
            return show($this->client_name, $errors['type'], $msg = "", $errors, []);
        }
        $tot = [];
        foreach ($rawAry['data'] as $k => $v) {
            if (!isset($v['uuid'])) {
                $errors = [
                    'type' => '300,8',
                    'msg' => 'Resources Not Exists'
                ];
                return show($this->client_name, $errors['type'], $msg = "", $errors, []);
            } else {
                $tot[] = $this->updateFolder($v);
            }

        }
        return show($this->client_name, '0,0', $msg = "", [], $tot);
    }

    public function updateFolder($data)
    {
        $tot = $upData = [];
        $find = Db::table('data_files')->whereNotNull('size')->where('id', $data['uuid'])->whereNull('deleted_at')->where('client_id', $this->client_id)->where('member_id', $this->member_id)->find();
        if (empty($find)) {
            $tot = [
                'uuid' => $data['uuid'],
                'msg' => 'uuid Not Exists'
            ];
            return $tot;
        }
        if (!empty($data['filename'])) {
            $fromObject = "private" . "/" . $this->member_id . "/" . $this->client_name . "/" . $find['id'];
            $up = $this->editObject(Config('env.aliyun_oss.Bucket'), $fromObject, Config('env.aliyun_oss.Bucket'), $fromObject, $data['filename'], $find['content_type']);
            if ($up == 1) {
                $upData['filename'] = $data['filename'];
            }
        }
        if (!empty($data['folder'])) {
            $folder = $this->screen($data['folder']);
            $folderId = $this->directory($folder);
            $upData['folder_id'] = $folderId;
            $upData['folder'] = $data['folder'];
        }
        if (!empty($data['modify_time'])) {
            $modify_time = $data['modify_time'];
            $upData['last_modified_time'] = $modify_time;
        }
        $upRes = Db::table('data_files')->whereNotNull('size')->where('id', $data['uuid'])->whereNull('deleted_at')->where('client_id', $this->client_id)->where('member_id', $this->member_id)->data($upData)->update();
        if ($upRes) {
            if (empty($data['folder'])) $upData['folder'] = $find['folder'];
            if (empty($data['filename'])) $upData['filename'] = $find['filename'];
            $tot = [
                'uuid' => $data['uuid'],
                'folder' => $upData['folder'],
                'filename' => $upData['filename']
            ];
            return $tot;
        } else {
            $tot = [
                'uuid' => $data['uuid'],
                'msg' => 'Sql Not Exists'
            ];
            return $tot;
        }

    }

    /**
     * 分享文件
     * User: 陈大剩
     * @return \think\response\Json
     * @throws \OSS\Core\OssException
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function share()
    {
        $raw = request()->getContent();
        $data = json_decode($raw, true);
        if (!isset($data['uuid']) || !isset($data['access_type'])) {
            $errors = [
                'type' => '300,0',
                'msg' => 'File not found'
            ];
            return show($this->client_name, $errors['type'], $msg = "", $errors, []);
        }
        try {
            $tot = Db::table('data_files')->whereNotNull('size')->where('id', $data['uuid'])->whereNull('deleted_at')->find();

        } catch (\Exception $e) {
            $errors = [
                'type' => '300,0',
                'msg' => 'Uuid not found'
            ];
            return show($this->client_name, $errors['type'], $msg = "", $errors, []);
        }
        if (empty($tot)) {
            $errors = [
                'type' => '300,0',
                'msg' => 'Uuid not found'
            ];
            return show($this->client_name, $errors['type'], $msg = "", $errors, []);
        }
        if ($data['access_type'] == "public") {
            $oss['access_type'] = "public-read";
        }
        if ($data['access_type'] == "secret") {
            $oss['access_type'] = "private";
        }
        if (empty($oss['access_type'])) {
            $errors = [
                'type' => '300,0',
                'msg' => 'Access_type not found'
            ];
            return show($this->client_name, $errors['type'], $msg = "", $errors, []);
        }
        $fileName = "private" . "/" . $this->member_id . "/" . $this->client_name . "/" . $data['uuid'];
        if ($oss['access_type'] == "public-read") {
            $count = Db::table('sharings')->where('data_file_id', $data['uuid'])->where('member_id', $this->member_id)->where('secret', "")->where('publish_status', 1)->find();
            if (empty($count)) {
                $shar = Db::table('sharings')->where('secret', "")->where('data_file_id', $data['uuid'])->where('member_id', $this->member_id)->find();
                if (empty($shar)) {
                    try {
                        $ossClient = $this->new_oss();
                        $res = $ossClient->putObjectAcl(Config('env.aliyun_oss.Bucket'), $fileName, $oss['access_type']);
                    } catch (OssException $e) {
                        return errorMsg('101', $e->getMessage(), 400);
                    }
                    $str = $this->shorturl(get_oss_custom_host() . $fileName);
                    $uuid = guid();
                    $sharData = [
                        'id' => $uuid,
                        'member_id' => $this->member_id,
                        'data_file_id' => $data['uuid'],
                        'expiration' => date("Y-m-d H:i:s", time() + 15000000000),
                        'next_expiration' => date("Y-m-d H:i:s"),
                        'created_at' => date("Y-m-d H:i:s"),
                        'updated_at' => date("Y-m-d H:i:s"),
                        'publish_status' => 1,
                        'short_url' => $str,
                        'visit_times' => 0
                    ];
                    $res = Db::table('sharings')->insert($sharData);
                    if ($res) {
                        $resData = [
                            'filename' => $tot['filename'],
                            'sharing' => [
                                'file_uuid' => $data['uuid'],
                                'expiration' => $sharData['expiration'],
                                'secret' => "",
                                'share_link' => config('env.website_hostname') . "/s/" . $str,
                                'created_at' => strtotime($sharData['created_at'])
                            ]
                        ];
                        return show($this->client_name, '0,0', $msg = "", [], $resData);
                    }
                } else {
                    $res = Db::table('sharings')->where('secret', "")->where('data_file_id', $data['uuid'])->where('member_id', $this->member_id)->update(['publish_status' => 1]);
                    if ($res) {
                        $resData = [
                            'filename' => $tot['filename'],
                            'sharing' => [
                                'file_uuid' => $data['uuid'],
                                'expiration' => $shar['expiration'],
                                'secret' => "",
                                'share_link' => config('env.website_hostname') . "/s/" . $shar['short_url'],
                                'created_at' => strtotime($shar['created_at'])
                            ]
                        ];
                        return show($this->client_name, '0,0', $msg = "", [], $resData);
                    }
                }
            } else {
                $resData = [
                    'filename' => $tot['filename'],
                    'sharing' => [
                        'file_uuid' => $data['uuid'],
                        'expiration' => $count['expiration'],
                        'secret' => "",
                        'share_link' => config('env.website_hostname') . "/s/" . $count['short_url'],
                        'created_at' => strtotime($count['created_at'])
                    ]
                ];
                return show($this->client_name, '0,0', $msg = "", [], $resData);
            }
        }
        if ($oss['access_type'] == "private") {
            if (!isset($data['expiry'])) {
                $data['expiry'] = 1800;
            }
            try {
                $ossClient = $this->new_oss();
                $res = $ossClient->putObjectAcl(Config('env.aliyun_oss.Bucket'), $fileName, $oss['access_type']);
                $signedUrl = $ossClient->signUrl(Config('env.aliyun_oss.Bucket'), $fileName, $data['expiry']);
                $res['signedUrl'] = htmlspecialchars_decode($signedUrl);
            } catch (OssException $e) {
                return errorMsg('101', $e->getMessage(), 400);
            }
            $url = explode("?", $res['signedUrl']);
            $str = $this->shorturl(get_oss_custom_host() . $fileName . "?" . $url[1]);
            $rand = $this->GetRandStr(4);
            $uuid = guid();
            $sharData = [
                'id' => $uuid,
                'member_id' => $this->member_id,
                'data_file_id' => $data['uuid'],
                'expiration' => date("Y-m-d H:i:s", time() + $data['expiry']),
                'next_expiration' => date("Y-m-d H:i:s"),
                'created_at' => date("Y-m-d H:i:s"),
                'updated_at' => date("Y-m-d H:i:s"),
                'secret' => $rand,
                'publish_status' => 1,
                'short_url' => $str,
                'download_url' => $url[1],
                'visit_times' => 0
            ];
            Db::table('sharings')->where('data_file_id', $data['uuid'])->where('member_id', $this->member_id)->where('publish_status', 1)->update(['publish_status' => 3]);
            $res = Db::table('sharings')->insert($sharData);
            if ($res) {
                $resData = [
                    'filename' => $tot['filename'],
                    'sharing' => [
                        'file_uuid' => $data['uuid'],
                        'expiration' => $sharData['expiration'],
                        'secret' => "$rand",
                        'share_link' => config('env.website_hostname') . "/s/" . $str,
                        'created_at' => strtotime($sharData['created_at'])
                    ]
                ];
                return show($this->client_name, '0,0', $msg = "", [], $resData);
            }
        }
        $errors = [
            'type' => '300,0',
            'msg' => 'Access_type not found'
        ];
        return show($this->client_name, $errors['type'], $msg = "", $errors, []);

    }

    /**
     * 取消分享接口
     * User: 陈大剩
     * @return \think\response\Json
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function cancel_share()
    {
        $raw = request()->getContent();
        $data = json_decode($raw, true);
        if (!isset($data['sid'])) {
            $errors = [
                'type' => '300,9',
                'msg' => 'Sharing Not Exists'
            ];
            return show($this->client_name, $errors['type'], $msg = "", $errors, []);
        }
        if (empty($data['sid'])) {
            $errors = [
                'type' => '300,9',
                'msg' => 'Sharing Not Exists'
            ];
            return show($this->client_name, $errors['type'], $msg = "", $errors, []);
        }
        $res = Db::table('sharings')->where('member_id', $this->member_id)->where('publish_status', 1)->where('short_url', $data['sid'])->update(['publish_status' => 3]);
        if ($res) {
            $tot = [
                'sid' => $data['sid'],
                'status' => true
            ];
            return show($this->client_name, '0,0', $msg = "", [], $tot);
        } else {
            $errors = [
                'type' => '300,9',
                'msg' => 'Sharing Not Exists'
            ];
            return show($this->client_name, $errors['type'], $msg = "", $errors, []);
        }
    }

    /**
     * 分享列表（内部接口）
     * User: 陈大剩
     * @return \think\response\Json
     */
    public function sharings()
    {
        $get = input('get.ids');
        if (empty($get)) {
            return show($this->client_name, "0.0", "", []);
        }
        $get = str_replace("，", ",", $get);
        $data = explode(",", $get);
        foreach ($data as $v) {
            try {
                $tot = Db::table('sharings')->where('member_id', $this->member_id)->where('id', $v)->field('short_url,visit_times')->find();
            } catch (\Exception $e) {
                $tot = [
                    'short_url' => null,
                    'visit_times' => null
                ];
            }
            if (empty($tot['short_url'])) {
                $url = 'Uuid empty';
            } else {
                $url = config('env.website_hostname') . "/s/" . $tot['short_url'];
            }
            if (empty($tot['visit_times'])) {
                $visit_times = null;
            } else {
                $visit_times = $tot['visit_times'];
            }
            $res[$v] = [
                'share_link' => $url,
                'visit_times' => $visit_times
            ];
        }
        return show($this->client_name, "0.0", "", [], $res);
    }
}