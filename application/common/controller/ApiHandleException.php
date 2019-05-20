<?php
/**
 * Created by PhpStorm.
 * User: 陈大剩
 * Date: 2019/5/15
 * Time: 14:19
 */

namespace app\common\controller;

use think\exception\Handle;
class ApiHandleException extends Handle
{
    /**
     * http 状态码
     * @var int
     */
    public $httpCode = 404;
    public $getMessage="Not Found";
    public function render(\Exception $e) {

        if(config('app_debug') == true) {
            return parent::render($e);
        }
        if ($e instanceof ApiException) {
            $this->httpCode = $e->httpCode;
            $this->getMessage=$e->getMessage();
        }
        return  errorMsg(400,$this->getMessage,$this->httpCode);
    }
}