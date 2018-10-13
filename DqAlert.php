<?php

class DqAlert{
    static function send($msg,$topic){
        $topicList= DqModule::getRegisterTopic();
        if(isset($topicList[$topic])){
            $mailList = trim($topicList[$topic]['email']);
            if(empty($mailList)){
                return;
            }
            $mailto = array();
            $tmp = explode(',',$mailList);
            foreach ($tmp as $v){
                $v = trim($v);
                if(!empty($v)) {
                    $mailto[] = $v; 
                }
            }

            if(!empty($mailto)){
                DqMailer::sendMail($mailto,'[延时队列通知]',$msg);
            }
        }
    }

    static function send_redis_down_notice($redis,$msg=''){
        $mailInfo = DqMysql::select('dq_alert');
        if(empty($mailInfo)){
            DqLog::writeLog('empty alert mail conf,plear check',DqLog::LOG_TYPE_EXCEPTION);
            return false;
        }else{
            $mailInfo = $mailInfo[0];
            $extArr = json_decode($mailInfo['ext'],true);
            if(isset($extArr['redis'])){
                $tmp = explode(',',$extArr['redis']);
                $mailTo = array();
                foreach ($tmp as $v){
                    $v = trim($v);
                    if(!empty($v)){
                        $mailTo[] = $v;
                    }
                }

                if(!empty($mailTo)){
                    DqMailer::sendMail($mailTo,'[延时队列通知]-redi连接异常','info='.json_encode($redis).',msg='.$msg);
                }

            }
        }
    }


}


