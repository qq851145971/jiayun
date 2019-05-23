<?php
/**
 * Created by PhpStorm.
 * User: 陈大剩
 * Date: 2019/5/23
 * Time: 0:30
 */

namespace app\index\controller;
use think\Request;
class MyError
{
    public function index(Request $request)
    {
        $msg = $request->controller();
        return $this->msg($msg);
    }

    protected function msg($name)
    {
        return errorMsg($name,440);
    }
}