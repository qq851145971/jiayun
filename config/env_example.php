<?php
/**
 * Created by PhpStorm.
 * User: 陈大剩
 * Date: 2019/5/16
 * Time: 11:03
 */
return [
    'oss_custom_host'=>'',
    'app_code'=>'',
    'tonken_key'=>'',
    'aliyun_oss' => [
        'KeyId'      => '',  //您的Access Key ID
        'KeySecret'  => '',  //您的Access Key Secret
        'Endpoint'   => '',  //阿里云oss 外网地址endpoint
        'Bucket'     => '',  //Bucket名称
    ],
    'redis'=>[
        'hostname'        => '127.0.0.1',
        'password'        => '',
        'hostport'        => '6379',
        'out_time'        =>120,
        'time_out'        =>3
    ]
];