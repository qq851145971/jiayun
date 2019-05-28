<?php
/**
 * Created by PhpStorm.
 * User: 陈大剩
 * Date: 2019/5/28
 * Time: 10:03
 */

namespace app\common\controller;


class Redis
{
    public $redis = "";
    public function __construct() {
        ini_set('default_socket_time', -1);
        if(!extension_loaded('redis')) {
            throw new \Exception("redis.so文件不存在");
        }
        try {
            $this->redis = new \Redis();
            $result = $this->redis->connect(config('env.redis.hostname'),config('env.redis.hostport'),config('env.redis.time_out'));
        } catch(\Exception $e) {
            throw new \Exception("redis服务异常");
        }
        if($result === false) {
            throw new \Exception("redis 链接失败");
        }
    }


    /**
     * 魔术方法，调用redis底层类
     * User: 陈大剩
     * @param $name
     * @param $arguments
     * @return mixed
     */
    public function __call($name,$arguments) {
        return $this->redis->$name(...$arguments);
    }
}