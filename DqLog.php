<?php
class DqLog{
    const LOG_TYPE_NORMAL=1;
    const LOG_TYPE_EXCEPTION=2;

    //通知日志
    const LOG_TYPE_NOTIFY_START=3;
    const LOG_TYPE_NOTIFY_FAIL=4;
    const LOG_TYPE_NOTIFY_SUCC=5;

    const LOG_TYPE_REQUEST=6; //记录所有的请求日志


    public static function writeLog($str,$flag=self::LOG_TYPE_NORMAL){
        $p_name='';
        if(php_sapi_name()=='cli'){
            $p_name = cli_get_process_title();
        }
        $str = "[" . date('Y-m-d H:i:s') . "]p_name=".$p_name.' '. $str;
        $dir  = self::getLogDir();  
        $seg=date('Ymd');
        switch ($flag) {
            case self::LOG_TYPE_EXCEPTION:
                $fileName = $dir . '/err_' . $seg . '.txt';
                file_put_contents($fileName, $str . "\n", FILE_APPEND);
                break;
            case self::LOG_TYPE_NORMAL:
                $fileName = $dir . '/notice_' . $seg . '.txt';
                file_put_contents($fileName, $str . "\n", FILE_APPEND);
                break;
            case self::LOG_TYPE_NOTIFY_START:
            case self::LOG_TYPE_NOTIFY_SUCC:
            case self::LOG_TYPE_NOTIFY_FAIL:
                $fileName = $dir . '/notify_' . $seg . '.txt';
                file_put_contents($fileName, $str . "\n", FILE_APPEND);
                break;
            case self::LOG_TYPE_REQUEST:
                $fileName = $dir . '/request_' . $seg . '.txt';
                file_put_contents($fileName, $str . "\n", FILE_APPEND);
                break;
        }
    }

    public static function getLogDir(){
        $dir = dirname(__FILE__) . '/logs/';
        if(!empty(DqConf::$logPath)){
            $dir = rtrim(DqConf::$logPath,'/').'/';
        }
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return $dir;
    }
}