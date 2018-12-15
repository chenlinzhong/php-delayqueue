<?php
include_once 'DqLog.php';
include_once 'DqException.php';

class DqComm{
    static $max_package_size=4096;  
    //获取消息
    public static function socket_read($cfd){
        try {
            if (is_resource($cfd)) {
                $msg_len = @socket_read($cfd, 4); //读取数据前四个字节,表示本条消息的长度
                if($msg_len===false){
                    $strMsg =  socket_strerror(socket_last_error($cfd));
                    throw  new DqException('read error,msg='.$strMsg);
                }
                $msg_len = intval($msg_len);
                $body = socket_read($cfd, $msg_len);
                if ($body===false) {
                    return false;
                }
                $body_len = strlen($body);
                if ($body_len!=$msg_len) {
                    throw new DqException(' body len is not match,body='.$body.' needed_len='.$msg_len);
                }
                $arr  = json_decode($body,true);
                if(!is_array($arr)){
                    throw new DqException(' parse body error,body='.$body);
                }
                return $arr;
            } else {
                throw  new DqException('given fd not a resource');
            }
        }catch (DqException $e){
            DqLog::writeLog($e->getDqMessage(),DqLog::LOG_TYPE_EXCEPTION);
            return false;
        }
    }

    public static function format_data($params){
        $str = json_encode($params);
        $len = strlen($str);
        $packageData=sprintf('%04d%s',$len,$str);
        return array($len+4,$packageData);
    }

    public static function socket_wirte($fd,$data){
        if(is_resource($fd)) {
            list($len, $data) = self::format_data($data);
            if ($len >= self::$max_package_size) {
                DqLog::writeLog('data too long,str=' . $data . ' max_package_size=' . self::$max_package_size,DqLog::LOG_TYPE_EXCEPTION);
            }
            while ($len) {
                $nwrite = @socket_write($fd, $data, $len); //可能一次性写不完，需要多次写入
                if ($nwrite === false) { /*数据写入失败*/
                    DqLog::writeLog('socker error: data=' . $data . ',fd=' . $fd . ',error=' . json_encode(error_get_last()),DqLog::LOG_TYPE_EXCEPTION);
                    return false;
                } else if ($nwrite > 0) {
                    $len -= $nwrite;
                    $data = substr($data, $nwrite);
                }
            }
            return true;
        }
        return false;
    }

    public static function msectime() {
        list($msec, $sec) = explode(' ', microtime());
        $msectime =  (float)sprintf('%.0f', (floatval($msec) + floatval($sec)) * 1000);
        return $msectime;
    }

}