<?php
/**
 * Created by PhpStorm.
 * User: 陈大剩
 * Date: 2019/5/15
 * Time: 14:19
 */

namespace app\common\controller;

use think\exception\Handle;
use think\Log;
class ApiHandleException extends Handle
{
    /**
     * http 状态码
     * @var int
     */
    public $httpCode = 404;
    public $getMessage="Not Found";
    public function render(\Exception $e) {

        if ($e instanceof ApiException) {
            $this->httpCode = $e->httpCode;
            $this->getMessage=$e->getMessage();
        }else{
            if (config('app_debug')) {   //是否开启debug模式，异常交给父类异常处理，否则输出json格式错误
                return parent::render($e);
            }
            $this->httpCode = 500;
            $this->getMessage = $e->getMessage() ?: '很抱歉，服务器内部错误';
//            $this->recordErrorLog($e);
        }
        // Http异常
        if ($e instanceof \think\exception\HttpException)
        {
             $this->httpCode = $e->getStatusCode();
        }
        return  errorMsg(400,$this->getMessage,$this->httpCode);
    }
    /**
     * 将异常写入日志
     * @param Exception $e
     */
    private function recordErrorLog(Exception $e)
    {
        Log::record($e->getMessage(), 'error');
        Log::record($e->getTraceAsString(), 'error');
    }
}