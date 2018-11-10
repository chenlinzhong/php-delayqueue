<?php
include_once 'DqLoader.php';
class DqModule{
    static $topRegistered=array();
    static function getRegisterTopic(){
        static $time=0;
        if(empty($time) || time()-$time > DqConf::$config_refresh || empty(self::$topRegistered)) {
            $retrys=3;
            while ($retrys--) {
                $arr = DqMysql::select('dq_topic');
                if(empty($arr)){
                    usleep(100000);
                }else{
                    break;
                }
            }
            foreach ($arr as $v) {
                self::$topRegistered[$v['topic']] = $v;
            }
            $time = time();  
            DqLog::writeLog('getRegisterTopic,data='.json_encode(self::$topRegistered).',pid='.posix_getpid());
        }
        if(empty(self::$topRegistered)) {
            DqLog::writeLog('register topic empty', DqLog::LOG_TYPE_EXCEPTION);
        }
        return self::$topRegistered;
    }

    static function notify($topic,$tid,$data){
        $reply_empty_flag='reply_empty';
        try {
            $allTopic = DqModule::getRegisterTopic();
            $notifyInfo = $allTopic[$topic];
            $id = $notifyInfo['id'];
            $strMsg = 'notify start reg_id_'.$id.' data=' . json_encode($data);
            DqLog::writeLog($strMsg,DqLog::LOG_TYPE_NOTIFY_START);
            //删除消息
            DqRedis::delMsg($topic, $tid);
            $result = self::send_http_request($notifyInfo, $data);

            DqRedis::incr_nums($id, 'total_notfiy');
            DqRedis::incr_nums($id, 'tn:' . date('Ymd'));

            if($result) {
                //判断跟re_notify_flag判断是否需要重试
                $flag= trim($notifyInfo['re_notify_flag']);
                if($result!==true){
                    $arrRes = json_decode($result,true);
                    if(!empty($flag) && is_array($arrRes)){
                        $compareRet = self::check_renotify_flag($arrRes,$flag);
                        DqLog::writeLog("$topic,$tid,re_notify_flag=" . $flag . ',response=' . $result.',ret='.$compareRet);
                        if($compareRet){
                            throw  new Exception($reply_empty_flag);
                        }
                    }
                }
            }else{  //请求接口没有收到回复
                throw  new Exception($reply_empty_flag);
            }
        }catch (Exception $e){

            //通知接口回复为空，认定是通知一次，重新写入队列，1分钟后再通知，累计通知10次后，发邮件通知之后丢弃处理
            if($e->getMessage()==$reply_empty_flag){
                DqLog::writeLog("$topic,$tid,reply_empty,will notify agin");
                DqRedis::incr_nums($id,'te:'.date('Ymd'));
                self::notify_fail_handler($topic,$tid,$data);
            }else{
                $strMsg = 'notify occur exp,args='.json_encode(func_get_args());
                DqLog::writeLog($strMsg,DqLog::LOG_TYPE_EXCEPTION);
            }

        }
    }

    //检测flag标志
    static function check_renotify_flag($res,$re_noitfy_flag=''){
        $re_noitfy_flag = trim($re_noitfy_flag);
        if(empty($re_noitfy_flag)){
            return false;
        }
        preg_match_all('/\{\s*(res\..+?)\s*\}/', $re_noitfy_flag, $matches, PREG_SET_ORDER);
        foreach ($matches as $value) {
            $tmp = explode('.', $value[1]);
            $cusVar = '$res';
            unset($tmp[0]);
            foreach ($tmp as $v) {
                $cusVar .= '["' . $v . '"]';
            }
            $re_noitfy_flag = str_replace($value[0],$cusVar,$re_noitfy_flag);
        }
        $ret = false;
        eval("\$ret=".$re_noitfy_flag.";");
        return $ret;
    }

    static function notify_fail_handler($topic,$tid,$data){
        try {
            if (isset($data['exp_notify_nums'])) {
                $data['exp_notify_nums']++;
            } else {
                $data['exp_notify_nums'] = 1;
            }
            list($topic,$oid) = DqRedis::parse_tid($tid);
            $notify_min =  2*($data['exp_notify_nums']-1)+1;
            $handleData = array(
                'id' => $oid,
                'topic' => $topic,
                'body' => $data,
                'fix_time'=>date('Y-m-d H:i:s',time()+60*$notify_min), //1分钟后再次发起通知
            );
            if(!DqRedis::handle($handleData)){
                throw new Exception('notify_fail_handler,reset to redis occer error');
            }
        }catch (Exception $e){
            $str="notify_fail_handler accur exp,msg=".$e->getMessage().',args='.json_encode(func_num_args());
            DqLog::writeLog($str,DqLog::LOG_TYPE_EXCEPTION);
        }
    }

    static function send_http_request($registerCallback,$params){
        try {
            $url = $registerCallback['callback'];
            $method = !empty($registerCallback['method']) ? $registerCallback['method'] : 'GET';
            $timeout = $registerCallback['timeout'];
            $id = $registerCallback['id'];
            $retrys=3;
            while ($retrys--) {
                $http_request = new DqCurl($url);
                $http_request->set_method($method);
                $http_request->set_connect_timeout(1000);
                $http_request->set_timeout($timeout);
                foreach ($params as $k=>$v) {
                    if(strtolower(trim($method))=='post'){
                        $http_request->add_post_field($k, $v);
                    }else {
                        $http_request->add_query_field($k, $v);
                    }
                }
                $http_request->send();
                $response = $http_request->response_content;
                if(!empty($response)){
                    break;
                }
                usleep(10000);
            }
            if(empty($response)){
                if (isset($params['exp_notify_nums']) && $params['exp_notify_nums']> DqConf::$notify_exp_nums) {
                    $strMsg = "reply empty and repeat notify over ".DqConf::$notify_exp_nums." times,name=".$registerCallback['t_name'].",curl=".$http_request->get_curl_cli();
                    DqAlert::send($strMsg,$registerCallback['topic']);
                    return true;
                }
                $strMsg = 'reg_id_'.$id.',curl='.$http_request->get_curl_cli().' return is null,callbackinfo='.json_encode($registerCallback);
                DqLog::writeLog($strMsg,DqLog::LOG_TYPE_NOTIFY_FAIL);
                return $response;
            }
            $strMsg = 'reg_id_'.$id.',curl='.$http_request->get_curl_cli().' return='.$response.',callbackinfo='.json_encode($registerCallback);
            DqLog::writeLog($strMsg,DqLog::LOG_TYPE_NOTIFY_SUCC);
            return $response;
        } catch (Exception $e) {
            DqLog::writeLog('send_http_request fail,msg='.$e->getMessage(),DqLog::LOG_TYPE_EXCEPTION);
            return false;
        }
    }
}