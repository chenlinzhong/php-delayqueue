<?php
class DqMain
{
    static $childPid=array();
    static $stop = 0;
    static $pid=0;
    
    public static function startServer()
    {
        if(self::get_process_num(DqConf::DQ_SERVER)<1 && !DqMain::$stop) {
            $pid = pcntl_fork();
            if ($pid == -1) {
                DqLog::writeLog('server fork fail', DqLog::LOG_TYPE_EXCEPTION);
            } elseif ($pid) {
            } else {// 子进程处理
                register_shutdown_function('dq_exception_quit_handler');
                DqLog::writeLog('start server succ');
                cli_set_process_title(DqConf::DQ_SERVER);
                DqMain::$pid = posix_getpid();
                $server = new DqServer();
                $server->run();
                exit;
            }
        }
    }

    static function master_install_usr2(){
        static  $install= 0;
        if(!$install) {
            if(pcntl_signal(SIGUSR2, "dq_quite_exit_sig_handler",false)){
                DqLog::writeLog('master install usr2 succ');
                $install = 1;
            }else{
                DqLog::writeLog('master install usr2  fail',DqLog::LOG_TYPE_EXCEPTION);
            }
        }
    }

    public static function checkRedisStatus()
    {
        if(self::get_process_num(DqConf::DQ_REDIS_CHECKER)<1 && !DqMain::$stop) {
            $pid = pcntl_fork();
            if ($pid == -1) {
                DqLog::writeLog('redis-checker fork fail', DqLog::LOG_TYPE_EXCEPTION);
            } elseif ($pid) {
            } else {// 子进程处理
                DqLog::writeLog('start redis-checker succ');
                cli_set_process_title(DqConf::DQ_REDIS_CHECKER);
                DqMain::$pid = posix_getpid();
                DqMain::install_sig_usr1();
                while (true){
                    DqMain::sig_stop_check();
                    DqRedis::checkRedisStatus();
                    pcntl_signal_dispatch();
                    sleep(1);
                }
                exit;
            }
        }
    }

    //检测配置是否修改
    static function is_config_changed(){
        static $md5='';
        $func = new ReflectionClass('DqConf');      //所要查询的类名
        $config=$func->getFileName();
        if(empty($md5)){
            $md5  = md5(file_get_contents($config));
        }else{
            $tmp = md5(file_get_contents($config));
            if($md5!=$tmp){
                $md5 = $tmp;
                return true;
            }
        }
        return false;
    }

    static function get_all_childs_pid(){
        $childPids= self::getchildid(DqConf::DQ_SERVER);
        $childPids= array_merge($childPids,self::getchildid(DqConf::DQ_TIMER));
        $childPids= array_merge($childPids,self::getchildid(DqConf::DQ_CONSUME));
        $childPids= array_merge($childPids,self::getchildid(DqConf::DQ_REDIS_CHECKER));
        return $childPids;
    }


    static function getchildid($name){
        $_cmd = "ps -ef | grep '$name' | grep -v grep | awk '{print $3,$8,$2}' ";
        $fp = popen($_cmd, 'r');
        $current_pid=posix_getpid();
        $pid = array();
        while (!feof($fp) && $fp) {
            $_line = trim(fgets($fp, 1024));
            $arr = explode(" ",$_line);
            if(trim($arr[0])==$current_pid && preg_match("/^${name}/",trim($arr[1]))){
               $pid[] = $arr[2];
            }
        }
        fclose($fp);
        return $pid;
    }



    public static function startTimer($timerNums){
        //获取timer进程个数
        if(self::get_process_num(DqConf::DQ_TIMER)<$timerNums && !DqMain::$stop){
            for ($i = 0; $i <$timerNums; $i++){
                if(in_array($i,self::get_run_id(DqConf::DQ_TIMER))){
                    continue;
                }
                $pid = pcntl_fork();
                if ($pid == -1) {
                    DqLog::writeLog('fork fail',DqLog::LOG_TYPE_EXCEPTION);
                } elseif ($pid) {
                } else {// 子进程处理
                    register_shutdown_function('dq_exception_quit_handler');
                    cli_set_process_title(DqConf::DQ_TIMER.'_'.$i);
                    DqMain::$pid = posix_getpid();
                    DqRedis::timer($i);
                    // 一定要注意退出子进程,否则pcntl_fork() 会被子进程再fork,带来处理上的影响。
                    exit;
                }
            }
        }
    }

    public static function get_run_id($name){
        $_cmd = "ps -ef | grep '$name' | grep -v grep | awk '{print $3,$8,$2}' ";
        $fp = popen($_cmd, 'r');
        $current_pid=posix_getpid();
        $runId = array();
        while (!feof($fp) && $fp) {
            $_line = trim(fgets($fp, 1024));
            $arr = explode(" ",$_line);
            if(trim($arr[0])==$current_pid && preg_match("/^${name}/",trim($arr[1]))){
                $tmp = explode('_',$arr[1]);
                if(isset($tmp[count($tmp)-1]) && is_numeric($tmp[count($tmp)-1])){
                    $runId[] = intval($tmp[count($tmp)-1]);
                }
            }
        }
        fclose($fp);
        return $runId;
    }

    public static function startConsume($proeccss=1)
    {
        for ($i = 0; $i <$proeccss; $i++){
            if(DqMain::$stop){
                return ;
            }
            if(in_array($i,self::get_run_id(DqConf::DQ_CONSUME))){
                continue;
            }
            $pid = pcntl_fork();
            if ($pid == -1) {
                DqLog::writeLog('consume fork fail',DqLog::LOG_TYPE_EXCEPTION);
            } elseif ($pid) {

            } else {// 子进程处理
                register_shutdown_function('dq_exception_quit_handler');
                cli_set_process_title(DqConf::DQ_CONSUME.'_'.$i);
                DqMain::$pid = posix_getpid();
                DqRedis::consume();
                exit;// 一定要注意退出子进程,否则pcntl_fork() 会被子进程再fork,带来处理上的影响。
            }
        }
    }



    //获取进程个数
    static function get_process_num($name){
        return count(self::getchildid($name));
    }

    //检测自身
    static function check_self($name) {
        $_cmd = "ps -ef | grep '$name' | grep -v grep | awk '{print $3,$8,$2}' ";
        $fp = popen($_cmd, 'r');
        $pid = array();
        while (!feof($fp) && $fp) {
            $_line = trim(fgets($fp, 1024));
            if(empty($_line)){
                break;
            }
            $arr = explode(" ",$_line);
            if(trim($arr[1])==$name){
                exit(0);
            }
        }
        fclose($fp);
        return $pid;
    }

    static function install_sig_usr1(){
        if(pcntl_signal(SIGUSR1, "dq_exit_sig_handler",false)){
            DqLog::writeLog('install sig handler succ,pid='.posix_getpid().',name='.cli_get_process_title());
        }else{
            DqLog::writeLog('install sig handler fail,pid='.posix_getpid().',name='.cli_get_process_title(),DqLog::LOG_TYPE_EXCEPTION);
        }
    }

    static function sig_stop_check(){
        if(DqMain::$stop){
            DqLog::writeLog('pid='.posix_getpid().',name='.cli_get_process_title().' get stop flag ,will exit now bye..');
            exit(0);
        }
    }

    static function check_run_time(){
        static  $time=0;
        if(empty($time)){
            $time = time();
        }
        if(!empty($time) && time() - $time > rand(85500,86300)){
            self::$stop = 1;
            DqLog::writeLog('check_run_time over max seconds,will exit');
        }
    }
}

//程序异常退出回掉
function dq_exception_quit_handler(){
    $info = error_get_last();
    $pname = cli_get_process_title();
    DqLog::writeLog('dq_exception_quit_handler,pname='.$pname.':'.json_encode($info),DqLog::LOG_TYPE_EXCEPTION);
    DqRedis::incr_force(); //刷新统计数据
}

function dq_quite_exit_sig_handler($sigNo){
    switch ($sigNo) {
        case SIGUSR2:
            DqLog::writeLog('master process accept quiet_exit sig，pid='.posix_getpid().',name='.cli_get_process_title());
            DqMain::$stop = 1;
    }
}

function dq_exit_sig_handler($sigNo){
    switch ($sigNo) {
        case SIGUSR1:
            DqLog::writeLog('accept exit sig,pid='.posix_getpid().',name='.cli_get_process_title());
            DqMain::$stop = 1;
    }
}

function dq_broken_pipe_handler($sigNo){
    DqLog::writeLog('dq_broken_pipe_handler',DqLog::LOG_TYPE_EXCEPTION);
}


