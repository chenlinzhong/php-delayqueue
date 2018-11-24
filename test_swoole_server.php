<?php
include_once 'DqLoader.php';



$port = $_SERVER['argv'][1];

try {
    $serv = new swoole_server(DqConf::getLocalHost(), $port);
    $serv->set(array(
        'open_length_check' => true,
        'package_max_length' => 4096,
        'package_length_type' => 'N', //see php pack()
        'package_length_offset' => 0,
        'package_body_offset' => 4,
    ));


    $serv->on('connect', function ($serv, $fd) {
        $clientInfo = $serv->getClientInfo($fd);
        DqLog::writeLog('new client connet,info='.json_encode($clientInfo));
    });


    $serv->on('receive', function ($serv, $fd, $from_id, $data) {
        $length = unpack("N" , $data)[1];
        $data = substr($data,-$length);
        //$reply = "Get Message From Client {$fd}:{$length}:{$data}";
        //DqLog::writeLog($reply);
        //echo $reply."\n";
        $arrData=json_decode($data,true);
        switch ($arrData['cmd']) {
            case 'add':
                add($arrData,$serv,$fd);
        }

    });
    $serv->on('close', function ($serv, $fd) {

    });

}catch (Exception $e){
    echo $e->getMessage();
}


function add($arrData,$serv,$fd){
    $client = new Swoole_Redis();
    $client->connect('127.0.0.1', 6666, function (swoole_redis $client, $result)use($arrData,$serv,$fd){
        $body=json_encode($arrData['body']);
        $id = 135;
        $topic = $arrData['topic'];
        $tid = DqRedis::create_tid($topic,$id);
        $hkey='hkeytest';
        $score=time()+rand(1,5);
        $serv->send($fd, 'this is a test');
        $client->hset($hkey, $tid, $body, function (swoole_redis $client, $result) use($serv,$fd,$tid,$score) {
            if($result) {
                $zkey= 33333;
                $client->zadd($zkey, $score, $tid, function (swoole_redis $client, $result) use ($serv, $fd) {
                    DqRedis::incr_nums(1,2);
                    $serv->send($fd, 'this is a test');
                });
            }else{
                $serv->send($fd, 'this is a test');
            }
        });
    });

}

$serv->start();