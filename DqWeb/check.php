<?php
include 'DqMysql.php';

$topic=$_GET['topic'];
$id = intval($_GET['id']);

$condition='topic="'.$topic.'"';
if(!empty($id)){
    $condition .= ' and id!='.$id;
}

$arr = DqMysql::select('dq_topic',$condition);


if(empty($arr)){
    echo json_encode(array('code'=>1,'msg'=>'可以使用'));
}else{
    echo json_encode(array('code'=>2,'msg'=>'topic已存在'));
}