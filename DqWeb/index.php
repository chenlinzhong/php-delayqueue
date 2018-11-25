<?php
define('WEB_ROOT',dirname(__FILE__));
include_once dirname(__FILE__).'/../DqLoader.php';

require WEB_ROOT.'/libs/Smarty.class.php';

ini_set('display_errors','off');

try {
    $smarty = new Smarty;
    $smarty->caching = false;
    $smarty->cache_lifetime = 1;
    $op = empty($_GET['op']) ? 'add' : $_GET['op'];

    $redis_list = DqMysql::select('dq_redis');

    $alertInfo = DqMysql::select('dq_alert');
    if(!empty($alertInfo)){
        $alertInfo = $alertInfo[count($alertInfo)-1];
        $alertInfo['ext'] = json_decode($alertInfo['ext'],true);
    }


    $redis  = new Redis();
    foreach ($redis_list as &$v) {
        $tmp = array();
        $parts = explode(',', $v['t_content']);
        list($host, $port, $auth) = DqConf::parse_config($parts[0]);
        if($redis->connect($host,$port)){
            $v['online_desc'] = '<span style="color:green;">连接正常</span>';
        }else{
            $v['online_desc'] ='<span style="color:red;">连接失败</span>';
        }
        try {
            $v['total_nums'] = DqRedis::get_nums('redis:'.$v['id'],DqRedis::TOTAL_WRITE_NUMS);
            $v['notify_nums'] = DqRedis::get_nums('redis:'.$v['id'],DqRedis::TOTAL_NOTIFY_NUMS);
            $redisInfo = $redis->info();
            $v['used_memory_human'] = $redisInfo['used_memory_human'];
            $v['redis_version'] = $redisInfo['redis_version'];
           // $v['rdb_last_save_time'] = date('Y-m-d H:i:s',$redisInfo['last_save_time']);
            $v['total_del'] = DqRedis::get_nums('redis:'.$v['id'],DqRedis::TOTAL_DELETE_NUMS);
        }catch (Exception $e){

        }
    }

    $strCondition = '';
    $page = isset($_GET['page']) ? $_GET['page'] : 1;
    $size = 15;

    $con = array();
    if (!empty($_GET['s_topic'])) {
        $con[] = 'topic like "%' . $_GET['s_topic'] . '%"';
    }
    if (!empty($_GET['s_name'])) {
        $con[] = 't_name like "%' . $_GET['s_name'] . '%"';
    }

    $strCondition = implode(' and ', $con);


    $topicList = DqMysql::select('dq_topic', $strCondition, $page, $size);
    foreach ($topicList as &$list) {
        $id = $list['id'];
        $list['total_write'] = DqRedis::get_nums($id, 'total_write');
        $list['total_notfiy'] = DqRedis::get_nums($id, 'total_notfiy');
        $list['today_write'] = DqRedis::get_nums($id, 'tw:' . date('Ymd'));
        $list['today_notify'] = DqRedis::get_nums($id, 'tn:' . date('Ymd'));
        $list['today_exp'] = DqRedis::get_nums($id, 'te:' . date('Ymd'));
        $list['total_del'] = DqRedis::get_nums($id,'total_del');
        $list['today_del'] = DqRedis::get_nums($id,'tdel:'.date('Ymd'));
        if($list['status']==1){
            $list['online']='下线';
            $list['online_status']=2;
            $list['status_desc']='<span style="color:green;">生效中</span>';
        }else{
            $list['online']='上线';
            $list['online_status']=1;
            $list['status_desc']='<span style="color:red;">已下线</span>';
        }
        $list['priority_name'] = DqConf::$priorityName[$list['priority']];
    }
    $topicTotal = DqMysql::selectCount('dq_topic', $strCondition);

    $pages = ceil($topicTotal / $size);

    $smarty->assign('alert',$alertInfo);
    $smarty->assign("name", "hello,world");
    $smarty->assign("redis_list", $redis_list);
    $smarty->assign("get", $_GET);
    $smarty->assign('query', http_build_query($_GET));
    $smarty->assign('tab', $_GET['tab']);
    $smarty->assign('topic_list', $topicList);
    $smarty->assign('pages', $pages);
    $smarty->assign('page', $page);
    $smarty->display('add.tpl');
}catch (Exception $e){
    echo $e->getMessage();
}

