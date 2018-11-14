<?php
include_once 'DqLoader.php';

if(php_sapi_name()=='cli') {
    ini_set('display_errors', 'on');
    error_reporting(E_ALL);
}

/**
 * 设置提供web服务的进程名称，方便查看和管理
 */
$p_name=DqConf::DQ_HTTP_SERVER;
check_self($p_name);

/**
 * php二进制文件路径，用于启动web服务，版本必须大于5.4   
 */
$phpBin=DqConf::$phpBin;


/**
 * 指定web服务器监听的端口和ip
 */
$host = DqConf::getLocalHost();

if(isset($_SERVER['argv'][1])  && $_SERVER['argv'][1]=='--port' && is_numeric($_SERVER['argv'][2])){
    $port = $_SERVER['argv'][2];
}


/**
 * 指定web目录
 */
$webRoot = dirname(__FILE__).'/DqWeb';



/**
 * 记录http访问日志
 */
$logsDir = DqLog::getLogDir();
$logs = $logsDir.'/access.log';

/**
 * 使用php自动的web服务器功能
 */
cli_set_process_title($p_name);
$cmd = "${phpBin} -S ${host}:${port} -t ${webRoot} > ${logs} 2>&1 ";
$fp = popen($cmd,'r');
pclose($fp);


/**
 * 自身进程检测代码，如果当前进程存在则退出
 */
function check_self($name) {
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
            echo 'process exists,will exit';
            exit(0);
        }
    }
    fclose($fp);
    return $pid;
}