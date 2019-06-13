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
    public $member_id = "";
    public $client_id = "";
    public $client_name = "";

    public function initialize()
    {
        parent::initialize();
        error_reporting(E_ERROR);
//        if (Config('app_debug') !== true) {
//
//        }
        return $this->checkRequestAuth();
    }

    public function checkRequestAuth()
    {
        $headers = request()->header();
        if (!isset($headers['Authorization']) && !isset($headers['authorization'])) {
            throw new ApiException('token不存在', 401);
        }
        if (!isset($headers['Authorization'])) $headers['Authorization'] = $headers['authorization'];
        if (empty($headers['Authorization'])) {
            throw new ApiException('token不存在', 401);
        }
        $Authorization = explode(" ", $headers['Authorization']);
        $token = "";
        if (count($Authorization) == 2) {
            $token = $Authorization[1];
        } else {
            $token = $Authorization[0];
        }
        $key = config('env.token_key');
        try {
            $info = JWT::decode($token, $key, ["HS512"]); //解密jwt
            $this->headers = $headers;
            $this->member_id = $info->member->id;
        } catch (\Exception $e) {
            throw new ApiException('token验证失败', 401);
        }
        return $this->authInfo($info->client->id);
    }

    public function authInfo($client_id)
    {
        $app = Db::table('clients')->where('app_id', $client_id)->field('code,id')->find();
        $this->client_id = $app['id'];
        return $this->client_name = $app['code'];
    }

    public function jwt()
    {
        $key = "2f5cdce3b2e1e98d421ab144fa03ad4c0f8d59020a0ec5ec9726a97d277fd23da1909d0475a302818c9bfb98f60dd146da452d9e003ba2746ede8edfbf97288f";  //这里是自定义的一个随机字串，应该写在config文件中的，解密时也会用，相当    于加密中常用的 盐  salt
        $token = [
            "member" => [
                "id" => 'a8c076a3-d910-4801-b887-30fdfb6ad1a5'
            ],
            "client" => [
                "id" => "8d32f0bc7eed5e16f75132fc4cd34d6a0e577f225610b089f2c7e4fdef3b8a81",
                "code" => 2,
                "official" => false
            ],
            "iat" => time(), //签发时间
            "nbf" => time(), //在什么时候jwt开始生效  （这里表示生成100秒后才生效）
            "exp" => time() + 720000, //token 过期时间
        ];
        $jwt = JWT::encode($token, $key, "HS512"); //根据参数生成了 token
        return json([
            "token" => $jwt
        ]);
    }

    public function code62($x)
    {
        $show = '';
        while ($x > 0) {
            $s = $x % 62;
            if ($s > 35) {
                $s = chr($s + 61);
            } elseif ($s > 9 && $s <= 35) {
                $s = chr($s + 55);
            }
            $show .= $s;
            $x = floor($x / 62);
        }
        return $show;
    }

    public function shorturl($url)
    {
        $url = crc32($url);
        $result = sprintf("%u", $url);
        return $this->code62($result);
    }

    public function GetRandStr($len)
    {
        $chars = array(
            "a", "b", "c", "d", "e", "f", "g", "h", "i", "j", "k",
            "l", "m", "n", "o", "p", "q", "r", "s", "t", "u", "v",
            "w", "x", "y", "z", "A", "B", "C", "D", "E", "F", "G",
            "H", "I", "J", "K", "L", "M", "N", "O", "P", "Q", "R",
            "S", "T", "U", "V", "W", "X", "Y", "Z", "0", "1", "2",
            "3", "4", "5", "6", "7", "8", "9"
        );
        $charsLen = count($chars) - 1;
        shuffle($chars);
        $output = "";
        for ($i = 0; $i < $len; $i++) {
            $output .= $chars[mt_rand(0, $charsLen)];
        }
        return $output;
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
    public function directory($folder = "/a", $parent_id = 0)
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
        if (empty($fist)) {
            if (empty($findFolder)) {
                $data = [
                    'id' => guid(),
                    'parent_id' => 0,
                    'member_id' => $this->member_id,
                    'name' => $this->member_id,
                    'created_at' => date('Y-m-d H:i:s.u'),
                    'updated_at' => date('Y-m-d H:i:s.u'),
                ];
                try {
                    $res = Db::table('file_folders')->insert($data);
                    return $this->directory($newdir, $data['id']);
                } catch (\Exception $e) {
                    return show($this->client_name, "100.0", '', [$e->getMessage()], [], 400);
                }
            } else {
                return $this->directory($newdir, $findFolder['id']);
            }
        } else {
            if (empty($findFolder)) {
                $data = [
                    'id' => guid(),
                    'parent_id' => $parent_id,
                    'member_id' => $this->member_id,
                    'name' => $fist,
                    'created_at' => date('Y-m-d H:i:s.u'),
                    'updated_at' => date('Y-m-d H:i:s.u'),
                ];
                try {
                    $res = Db::table('file_folders')->insert($data);
                    return $this->directory($newdir, $data['id']);
                    if (empty(end($folderAry))) {
                        return $data['id'];
                    }
                } catch (\Exception $e) {
                    return show($this->client_name, "100.0", '', [$e->getMessage()], [], 400);
                }
            } else {
                return $this->directory($newdir, $findFolder['id']);
            }
        }
    }

    /**
     * 去掉前后斜杠
     * User: 陈大剩
     * @param $str
     * @return string
     */
    public function screen($str)
    {
        if (!empty($str)) {
            if ($str !== "/") {
                $newFolder = explode("/", $str);
                if (end($newFolder) == "") {
                    array_pop($newFolder);
                    $str = implode("/", $newFolder);
                }
                if ($newFolder[0] !== "") {
                    $str = "/" . $str;
                }
            }
        } else {
            $str = "/";
        }
        return $str;
    }

    /**
     * 空方法
     */
    public function _empty()
    {
        throw new ApiException('empty method!', 404);
    }
}