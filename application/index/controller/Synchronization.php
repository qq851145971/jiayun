<?php
/**
 * Created by PhpStorm.
 * User: 陈大剩
 * Date: 2019/6/25
 * Time: 13:18
 */

namespace app\index\controller;

use think\Controller;
use think\facade\Log;
use think\Db;
class Synchronization extends Controller
{
    public function index(){

        $data=Db::connect(config('env.db2'))->name('members')->limit('10')->select();
        Log::record($data);
    }
}