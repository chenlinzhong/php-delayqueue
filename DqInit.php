<?php
include_once 'DqLoader.php';

if(php_sapi_name()=='cli') {
    ini_set('display_errors', 'on');
    error_reporting(E_ALL);
}

ini_set('default_socket_timeout', -1); //不超时

DqMain::check_self(DqConf::DQ_MASTER);

pcntl_signal(SIGPIPE,SIG_IGN);

/**
 * 设置listen端口
 */
if(isset($_SERVER['argv'][1]) && $_SERVER['argv'][1]=='--port' && is_numeric($_SERVER['argv'][2])){
    DqConf::$port = $_SERVER['argv'][2];
}

/**
 * 设置进程名称
 */
cli_set_process_title(DqConf::DQ_MASTER);   

$childNotifySucc=array();
DqMain::$pid = posix_getpid();
while(true) {
    /**
     * 模块初始化，如果启动失败或者意外退出，会自动拉起
     */
    DqMain::startServer();
    DqMain::startTimer(DqConf::$bucket);
    DqMain::startConsume(DqConf::$consume_nums);
    DqMain::checkRedisStatus();

    DqMain::master_install_usr2();

    /**
     * 回收子进程，避免成为僵死进程，占用服务器资源
     */
    $ret = pcntl_waitpid(0, $status,WNOHANG);

    /**
     * 如果配置文件有改动，自动退出
     */
    if(DqMain::is_config_changed()){
        DqLog::writeLog('config file has changed');
        DqMain::$stop = 1;
    }
    if(DqMain::$stop){
        $allChildPids = DqMain::get_all_childs_pid();
        if(count($allChildPids)==0){
            $ret = pcntl_waitpid(0, $status, WNOHANG); //再次回收子进程信息
            DqLog::writeLog('all childs process has exited,parent will exit now bye bye');
            exit(0);
        }else {
            foreach ($allChildPids as $pid) {
                if (!isset($childNotifySucc[$pid])) {
                    if (posix_kill($pid, SIGUSR1)) {
                        $childNotifySucc[$pid] = 1;
                        DqLog::writeLog('notify child process to exit succ,pid=' . $pid);
                    } else {
                        DqLog::writeLog('notify child process to exit fail,pid=' . $pid, DqLog::LOG_TYPE_EXCEPTION);
                    }
                } else {
                    DqLog::writeLog('child process has send exit sig,wait to exit,pid=' . $pid);
                }
            }
        }
    }
    pcntl_signal_dispatch();
    //DqMain::check_run_time();
    sleep(1);
}
