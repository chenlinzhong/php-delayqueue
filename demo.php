<?php
include_once 'DqLoader.php';
date_default_timezone_set("PRC");

//server列表
$server=array(
    '127.0.0.1:6789',
    //'127.0.0.1:6788',
);

$dqClient = new DqClient();
$dqClient->addServer($server);

$topic ='order_openvip_checker'; //topic在后台注册
$id = uniqid();
$data=array(
    'id'=>$id,
    'body'=>array(
        'a'=>1,
        'b'=>2,
        'c'=>3,
        'ext'=>str_repeat('a',64),
    ),
    //可选，设置后以这个通知时间为准，默认延时时间在注册topic的时候指定
    //'fix_time'=>date('Y-m-d 23:50:50'),
);

$time = msectime(); 

//添加
$boolRet = $dqClient->add($topic, $data);
echo 'add耗时:'.(msectime() - $time)."ms\n";

//查询
$time = msectime();
$result = $dqClient->get($topic, $id);
echo 'get耗时:'.(msectime() - $time)."ms\n";

//删除
$time = msectime();
$boolRet = $dqClient->del($topic,$id);
echo 'del耗时:'.(msectime() - $time)."ms\n";



function msectime() {
    list($msec, $sec) = explode(' ', microtime());
    $msectime =  (float)sprintf('%.0f', (floatval($msec) + floatval($sec)) * 1000);
    return $msectime;
}
