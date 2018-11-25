<?php
include 'DqMysql.php';
$op = $_GET['op'];
switch ($op){
    case 'redis_add':
        $data=array(
            't_name'=>$_GET['t_name'],
            't_content'=>$_GET['t_content'],
        );
        if($_GET['id']>0) {
            if (DqMysql::updateData('dq_redis', $data,'id='.$_GET['id'])) {
                echo json_encode(array('code' => 1, 'msg' => 'succ'));
            } else {
                echo json_encode(array('code' => 0, 'msg' => 'fail'));
            }
        }else{
            if (DqMysql::insertData('dq_redis', $data)) {
                echo json_encode(array('code' => 1, 'msg' => 'succ'));
            } else {
                echo json_encode(array('code' => 0, 'msg' => 'fail'));
            }
        }
        break;
    case 'topic_add':
        $data=array(
            't_name'=>$_GET['t_name'],
            'delay'=>$_GET['delay'],
            'callback'=>$_GET['callback'],
            'timeout'=>$_GET['timeout'],
            'email'=>$_GET['email'],
            'topic'=>$_GET['topic'],
            'createor'=>$_GET['createor'],
            'method'=>$_GET['method'],
            're_notify_flag'=>$_GET['re_notify_flag'],
            'priority'=>$_GET['priority'],
        );
        if($_GET['id']>0) {
            if (DqMysql::updateData('dq_topic', $data,'id='.$_GET['id'])) {
                echo json_encode(array('code' => 1, 'msg' => 'succ'));
            } else {
                echo json_encode(array('code' => 0, 'msg' => 'fail'));
            }
        }else{
            if (DqMysql::insertData('dq_topic', $data)) {
                echo json_encode(array('code' => 1, 'msg' => 'succ'));
            } else {
                echo json_encode(array('code' => 0, 'msg' => 'fail'));
            }
        }
        break;
    case 'del':
        $table = $_GET['table'];
        $id = $_GET['id'];
        if (DqMysql::delete($table, $id)) {
            echo json_encode(array('code' => 1, 'msg' => 'succ'));
        } else {
            echo json_encode(array('code' => 0, 'msg' => 'fail'));
        }
        break;
    case 'alert':
        $data=array(
            'host'=>$_GET['host'],
            'port'=>$_GET['port'],
            'user'=>$_GET['user'],
            'pwd'=>$_GET['pwd'],
            'ext'=>addslashes(json_encode($_GET['ext'])),
        );
        if(isset($_GET['id']) && $_GET['id']>0) {
            if (DqMysql::updateData('dq_alert', $data,'id='.$_GET['id'])) {
                echo json_encode(array('code' => 1, 'msg' => 'succ'));
            } else {
                echo json_encode(array('code' => 0, 'msg' => 'fail'));
            }
        }else{
            if (DqMysql::insertData('dq_alert', $data)) {
                echo json_encode(array('code' => 1, 'msg' => 'succ'));
            } else {
                echo json_encode(array('code' => 0, 'msg' => 'fail'));
            }
        }
        break;
    case 'update_status':
        $table = $_GET['table'];
        $id = $_GET['id'];
        $arrData=array('status'=>$_GET['status']);
        if (DqMysql::updateData($table,$arrData,'id='.$id)) {
            echo json_encode(array('code' => 1, 'msg' => 'succ'));
        } else {
            echo json_encode(array('code' => 0, 'msg' => 'fail'));
        }
        break;
}



