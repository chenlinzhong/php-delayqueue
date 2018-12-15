<?php
include_once 'DqLoader.php';
class DqRedis{

    const TOTAL_WRITE_NUMS ='dq_write_total_nums';
    const TOTAL_NOTIFY_NUMS='dq_notify_total_nums';
    const TOTAL_DELETE_NUMS='dq_del_total_nums';

    const TID_SEP_CHAR=':';

    public static function getRedisServerById($jobId){
        $key = crc32($jobId) % count(DqConf::getRedisServer());
        $server =  DqConf::getRedisServer();    
        return $server[$key];
    }

    //获取配置配件
    static $instance=array();
    public static function getRedisInstance($jobid,$flag='master',$force=false){
        static  $instance = null;
        try {
            $redis = self::getRedisServerById($jobid);
            if(empty($redis)){
                throw new Exception('get empty redis server,jobid='.$jobid);
            }
            return self::connect($redis, $flag, $force);
        }catch (Exception $e){
            DqLog::writeLog('getRedisInstance fail,msg='.$e->getMessage(),DqLog::LOG_TYPE_EXCEPTION);
        }
    }

    private static function connect($redis,$flag='master',$force=false){
        static $time=0;
        $host = $redis[$flag]['host'];
        $port = $redis[$flag]['port'];
        $rid = $redis[$flag]['id'];

        if(empty($host) || empty($port)){
            throw new Exception('empty host or port');
        }

        $auth = $redis[$flag]['auth'];
        $key = $host.":".$port;

        try {
            if (!$force) {
                if((time() - $time) > DqConf::$redis_ping_interval && isset(self::$instance[$key]) ){ //每个20秒心跳检测一次
                    $time = time();
                    if(self::$instance[$key]->ping()!='+PONG'){
                        unset(self::$instance[$key]);
                        DqLog::writeLog('redis disconnect and server will reconnect..',DqLog::LOG_TYPE_EXCEPTION);
                    }
                }
                if (isset(self::$instance[$key]) ) {
                    return self::$instance[$key];
                }
            }
        }catch (Exception $e){
            DqLog::writeLog($e->getMessage().'line:'.$e->getLine(),DqLog::LOG_TYPE_EXCEPTION);
        }
        $redis = new Redis();
        $redis->rid = $rid;
        $redis->connect($host, $port, 3);
        if (!empty($auth)) {
            $redis->auth($auth);
        }
        self::$instance[$key]  = $redis;
        $time = time();
        return self::$instance[$key];
    }

    public static function getJobKey(){
        return DqConf::$prefix.'job_info';
    }

    public static function getBucketKey($topic,$flag=false){
        $key = crc32($topic) % DqConf::$bucket;
        if($flag==false) {
            return DqConf::$prefix . 'bucket_' . $key;
        }else{
            return DqConf::$prefix . 'bucket_' . $flag;
        }
    }

    public static function getReadyQueueKey($priority){
        return DqConf::$prefix.$priority.'ready_queue';
    }


    public static function getExpireTime($data){
        if(isset($data['fix_time'])){
            return strtotime($data['fix_time']);
        }
        $topic = $data['topic'];
        $topRegistered = DqModule::getRegisterTopic();
        if(!isset($topRegistered[$topic])){
            DqLog::writeLog("$topic topic is unregisted",DqLog::LOG_TYPE_EXCEPTION);
            return false;
        }
        $delay = $topRegistered[$topic]['delay'];
        return time() + $delay;

    }

    public static function handle($data){
        try {
            //$start = DqComm::msectime();
            $topRegistered = DqModule::getRegisterTopic();
            $topic = $data['topic'];
            $id = $data['id'];
            $tid = self::create_tid($topic,$id);

            $redis = self::getRedisInstance($tid);

            if (!in_array($topic, array_keys($topRegistered))) {
                throw  new DqException('topic=' . $topic . ' is not registed');
            }

            if ($topRegistered[$topic]['status']!=1) {
                throw  new DqException('topic=' . $topic . ' is offline or deleted');
            }

            $hkey = self::getJobKey();
            $zkey = self::getBucketKey($topic);
            $score = self::getExpireTime($data);

            $data['body']['notify_time'] = date('Y-m-d H:i:s',$score);
            if(!isset($data['body']['dq_set_time'])) {
                $data['body']['dq_set_time'] = date('Y-m-d H:i:s');
            }
            $body = json_encode($data['body']);
            $pipe = $redis->multi(Redis::PIPELINE);
            $pipe->hSet($hkey, $tid, $body);
            $pipe->zadd($zkey, $score, $tid);
            $result = $pipe->exec();
            $isSucc = $result[0] || $result[1];
            if($isSucc){
                $notifyId  = $topRegistered[$topic]['id']; //数据库自增id
                DqRedis::incr_nums($notifyId,'total_write');
                DqRedis::incr_nums($notifyId,'tw:'.date('Ymd'));
                //记录总的写入个数
                self::redis_self_incr($redis->rid,self::TOTAL_WRITE_NUMS);
                DqLog::writeLog('handle succ:'.json_encode($data),DqLog::LOG_TYPE_REQUEST);
            }else{
                DqLog::writeLog('handle fail:'.json_encode($data),DqLog::LOG_TYPE_REQUEST);
            }
            return $isSucc;
        }catch (DqException $e){
            DqLog::writeLog('handle fail:'.json_encode($data).',msg='.$e->getMessage(),DqLog::LOG_TYPE_REQUEST);
            DqLog::writeLog($e->getMessage(),DqLog::LOG_TYPE_EXCEPTION);
            return false;
        }
    }



    public static function timer($i=0){
        DqMain::install_sig_usr1();
        while(true) {
            try {
                $redisServers = self::getAllRedisServer();
                foreach ($redisServers as $objRdis) {
                    $bucketKey = self::getBucketKey('',$i);
                    $start = 0;
                    while (true) {
                        pcntl_signal_dispatch();
                        DqMain::sig_stop_check();
                        $data = $objRdis->zRange($bucketKey, $start, $start, true);
                        if (empty($data)) {
                            break;
                        }
                        $tid = key($data);
                        $score = $data[$tid];
                        list($topic,$id) = self::parse_tid($tid);
                        if ($score < time()) { //第一个元素到时间了移动到就绪队列
                            $topRegistered = DqModule::getRegisterTopic();
                            $priority = $topRegistered[$topic]['priority'];
                            $readyKey = self::getReadyQueueKey($priority);
                            $lockKey  = 'lock:'.$tid;
                            if($objRdis->setnx($lockKey,1)) {  //加锁保证移动数据操作原子性
                                DqLog::writeLog('data ready,data=' . json_encode($data).',pos='.$start);
                                if ($objRdis->zrem($bucketKey, $tid)) {
                                    if (!$objRdis->rpush($readyKey, $tid)) {
                                        $strMsg = 'move to ready queue failed,id=' . $tid . ',data=' . $objRdis->hget(self::getJobKey(), $tid);
                                        DqLog::writeLog($strMsg, DqLog::LOG_TYPE_EXCEPTION);
                                    }
                                }
                                if(!$objRdis->delete($lockKey)){
                                   DqLog::writeLog('delete lock fail,key='.$lockKey,DqLog::LOG_TYPE_EXCEPTION);
                                }
                            }
                        }else{
                            break;
                        }
                        $start++;

                    }
                }
            } catch (Exception $e) {
                DqLog::writeLog($e->getMessage(), DqLog::LOG_TYPE_EXCEPTION);
            }
            usleep(100000);
        }
    }

    //获取id获取内容
    public static function getBody($id){
        $redis =  self::getRedisInstance($id);
        $data =$redis->hget(self::getJobKey(),$id);
        return json_decode($data,true);
    }

    //删除内容
    public static function delMsg($topic,$tid,$is_user_del=false){
        try {
            $redis = self::getRedisInstance($tid);
            $flag1 = $redis->hdel(self::getJobKey(), $tid);
            $flag2 = $redis->zDelete(self::getBucketKey($topic), $tid);
            $ret = $flag1 || $flag2;
            if ($ret && $is_user_del) {
                $redis->incr(self::TOTAL_DELETE_NUMS);
                $modules = DqModule::getRegisterTopic();
                $regId = $modules[$topic]['id'];
                DqRedis::incr_nums($regId, 'tdel:' . date('Ymd'),true);
                DqRedis::incr_nums($regId, 'total_del',true);
            }
            DqLog::writeLog("delMsg succ,param=$topic,$tid,$is_user_del,ret=$flag1 || $flag2",DqLog::LOG_TYPE_REQUEST);
            return $ret;
        }catch (Exception $e){
            DqLog::writeLog('delMsg fail,msg='.$e->getMessage().",param=$topic,$tid,$is_user_del",DqLog::LOG_TYPE_REQUEST);
        }
    }

    public static function getAllRedisServer(){
        $redisServers = DqConf::getRedisServer();
        $servers = array();
        foreach ($redisServers as $redis) {
            $objRdis = self::connect($redis, 'master');
            $servers[] = $objRdis;
        }
        return $servers;
    }

    public static function getAllTopic(){
        $allModules = array_keys(DqModule::getRegisterTopic());
        return $allModules;
    }


    static function parse_tid($tid){
        $pos = strpos($tid,self::TID_SEP_CHAR);
        if($pos===false){
            return false;
        }
        $id = substr($tid,$pos+1);
        $topic=substr($tid,0,$pos);
        return array($topic,$id);
    }

    static function create_tid($topic,$id){
        return $topic.self::TID_SEP_CHAR.$id;
    }

    public static function consume(){
        DqMain::install_sig_usr1();
        while(true) {
            $consume_nums_per_cycle=0;
            $redisServerList=array();
            try {
                $redisServerList = self::getAllRedisServer();
                foreach ($redisServerList as $redis) {
                    foreach (DqConf::$priorityConfig as $priority=>$nums) {
                        $key = self::getReadyQueueKey($priority);
                        while ($nums--) {
                            $consume_nums_per_cycle ++;
                            pcntl_signal_dispatch();
                            DqMain::sig_stop_check();
                            $tid = $redis->lpop($key);
                            if (empty($tid)) {
                                self::incr_force();
                                break;
                            }
                            DqLog::writeLog('ready pop tid=' . $tid);
                            $body = self::getBody($tid);
                            if (empty($body)) {
                                continue;
                            }
                            DqLog::writeLog('ready pop tid=' . $tid . ' body=' . json_encode($body));
                            self::redis_self_incr($redis->rid, self::TOTAL_NOTIFY_NUMS);
                            list($topic) = self::parse_tid($tid);
                            DqModule::notify($topic, $tid, $body);
                        }
                    }
                }
            } catch (Exception $e) {
                DqLog::writeLog($e->getMessage(), DqLog::LOG_TYPE_EXCEPTION);
            }
            //如果所有redis中都没有可消费的消息，则等待1s
            if($consume_nums_per_cycle==count($redisServerList)*count(DqConf::$priorityConfig)) {
                sleep(1);
            }
        }
    }

    static public  function checkRedisStatus(){
        static  $num =0;
        static  $last_check_time=0;

        $redisServers = DqConf::getRedisServer();
        if(empty($redisServers)){
            DqLog::writeLog('redis server empty,please add..',DqLog::LOG_TYPE_EXCEPTION);
            return ;
        }
        foreach ($redisServers as $redis) {
            try{
                $objRdis = self::connect($redis, 'master', true);
                $objRdis->ping();
            } catch (Exception $e) {
                $msg = $e->getMessage();
                DqLog::writeLog($e->getMessage().' redis='.json_encode($redis),DqLog::LOG_TYPE_EXCEPTION);
                if($msg=='Redis server went away'){
                    if(empty($last_check_time)){
                        $last_check_time = time();
                        $num ++;
                    }else{
                        if(time()-$last_check_time>30){
                            if($num>10) {
                                DqAlert::send_redis_down_notice($redis, $msg);
                            }
                            $num = 0;
                            $last_check_time = 0;
                        }else{
                            $num ++;
                        }
                    }

                }
            }
        }
    }

    static public function incr_force(){
        self::redis_self_incr('','',true);
        self::incr_nums('','',true);
    }

    static public function redis_self_incr($id='',$key='',$force=false){
        static $buf=array();
        static $time = 0;
        if(empty($time)){
            $time = time();
        }
        try {
            if (!empty($id) && !empty($key)) {
                isset($buf[$id][$key]) ? $buf[$id][$key]++ : $buf[$id][$key] = 1;
            }
            if (!$force) {
                if (time() - $time < DqConf::$flush_incr_interval) {
                    return true;
                }
            }
            $servers = DqConf::getRedisServer();
            foreach ($servers as $redis) {
                try {
                    $id = $redis['master']['id'];
                    if(isset($buf[$id])) {
                        $incrs = $buf[$id];
                        foreach ($incrs as $k => $v) {
                            $k1='redis:'.$id.':'.$k;
                            $sql='insert into dq_stat set u_key="%s",num="%s",create_time="%s" on duplicate key update num=num+%s';
                            $sql = sprintf($sql,$k1,$v,date('Y-m-d H:i:s'),$v);
                            $obj = DqMysql::getDbInstance();
                            if(empty($obj)){
                                return true;
                            }
                            $is_incr_succ=$obj->exec($sql);
                            if($is_incr_succ){
                                unset($buf[$id][$k]); //删除已写入的
                            }else{
                                DqLog::writeLog("redis_self_incr fail,$k, $v,buf=".json_encode($buf).',sql='.$sql,DqLog::LOG_TYPE_EXCEPTION);
                            }
                        }
                    }
                } catch (Exception $e) {
                    DqLog::writeLog('redis_self_incr fail,msg=' . $e->getMessage(),DqLog::LOG_TYPE_EXCEPTION);
                }
            }

            $time = time();
            return true;
        }catch (Exception $e){
            DqLog::writeLog('redis_self_incr fail,msg=' . $e->getMessage(),DqLog::LOG_TYPE_EXCEPTION);
            return false;
        }
    }

    static public  function incr_nums($id='',$key='',$force=false){
        static  $buf=array();
        static  $time = 0;
        if(!empty($id) && !empty($key)){
            $key = $id . ':' . $key;
            if(isset($buf[$key])){
                $buf[$key] ++;
            }else{
                $buf[$key] = 1;
            }
        }
        if(empty($time)){
            $time = time();
        }
        if(!$force) {
            if (time() - $time < DqConf::$flush_incr_interval) {
                return true;
            }
        }
        if(empty($buf)){
            return true;
        }
        try {

            foreach ($buf as $key => $incr) {
                if($incr<=0){
                    continue;
                }
                try {
                    $sql='insert into dq_stat set u_key="%s",num="%s",create_time="%s" on duplicate key update num=num+%s';
                    $sql = sprintf($sql,$key,$incr,date('Y-m-d H:i:s'),$incr);
                    $obj = DqMysql::getDbInstance();
                    if(empty($obj)){
                        return true;
                    }
                    $is_incr_succ=$obj->exec($sql);
                    if (!$is_incr_succ) {
                        DqLog::writeLog("incr_nums fail $id,$key,$incr,buf=".json_encode($buf).',sql='.$sql, DqLog::LOG_TYPE_EXCEPTION);
                    }else{
                        unset($buf[$key]); //已写入的删除
                    }
                } catch (Exception $e) {
                    DqLog::writeLog("incr_nums fail $id,$key,msg=" . $e->getMessage(), DqLog::LOG_TYPE_EXCEPTION);
                }
            }
            $time = time();
            return true;
        }catch (Exception $e){
            DqLog::writeLog('incr_nums exception,msg='.$e->getMessage(),DqLog::LOG_TYPE_EXCEPTION);
            return false;
        }
    }



    public static function get_nums($id,$key){
        try {
            $key = $id . ':' . $key;
            $data=DqMysql::select('dq_stat','u_key="'.$key.'"');
            return isset($data[0]['num']) ? $data[0]['num']:0;
        }catch (Exception $e){
            DqLog::writeLog("get_nums fail $id,$key,msg=".$e->getMessage(),DqLog::LOG_TYPE_EXCEPTION);
            return 0;
        }
    }

}


