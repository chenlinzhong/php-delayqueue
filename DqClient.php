<?php
include_once 'DqLoader.php';

class DqClient{


    private  $serverList = array(); 
    private  $fd = NULL;

    public  function addServer($server){
        if(is_string($server)){
            $this->serverList[] = $server;
        }
        if(is_array($server) && !empty($server)){
            $this->serverList  = array_merge($this->serverList,$server);
        }
        $this->serverList = array_unique($this->serverList);
    }



    public function connect(){
        if(!empty($this->fd)){
            return $this->fd;
        }
        if (empty($this->serverList)){
            DqLog::writeLog('empty server list');
            return false;
        }
        $serverList=$this->serverList;

        while(count($serverList)){
            try {
                $idx = rand(0,count($serverList)-1);
                $server = $serverList[$idx];
                list($host,$port)= explode(':',$server);

                $fd = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
                //1s内没处理完直接返回
                socket_set_option($fd,SOL_SOCKET,SO_RCVTIMEO,array("sec"=>1, "usec"=>0));
                if (!is_resource($fd)) {
                    $strMsg = 'socket_create error:' . socket_strerror(socket_last_error());
                    throw  new DqException($strMsg);
                }
                if (!socket_connect($fd, $host, $port)) {
                    $strMsg = 'socket_create error:' . socket_strerror(socket_last_error().' ip='.$host.' port='.$port);
                    throw  new DqException($strMsg);
                }
                $this->fd = $fd;
                return $fd;
            } catch (DqException $e) {
                unset($this->serverList[$idx]);  //删除无用的配置

                unset($serverList[$idx]);
                $serverList = array_values($serverList);

                DqLog::writeLog($e->getDqMessage(), DqLog::LOG_TYPE_EXCEPTION);
            }

        }
        return false;
    }

    public function parse_result($ret){
        if($ret['code']==1){
            return true;
        }else{
            return false;
        }
    }

    public function add($topic,$data){
        try{
            $fd = $this->connect();
            if($fd===false){
                throw  new DqException(' connect server faild');
            }
            $data['cmd']='add';
            $data['topic'] = $topic;
            if(DqComm::socket_wirte($fd,$data)){
                $ret = DqComm::socket_read($fd);
                return $this->parse_result($ret);
            }else{
                throw  new DqException('add error,data='.json_encode($data));
            }
        }catch (DqException $e){
            DqLog::writeLog($e->getDqMessage(),DqLog::LOG_TYPE_EXCEPTION);
        }
        return false;
    }


    public function del($topic,$id){
        try{
            $fd = $this->connect();
            if($fd===false){
                throw  new DqException(' connect server faild');
            }
            $data['cmd']='del';
            $data['topic'] = $topic;
            $data['id'] = $id;
            if(DqComm::socket_wirte($fd,$data)){
                $ret = DqComm::socket_read($fd);
                return $this->parse_result($ret);
            }else{
                throw  new DqException('add error,data='.json_encode($data));
            }
        }catch (DqException $e){
            DqLog::writeLog($e->getDqMessage(),DqLog::LOG_TYPE_EXCEPTION);
        }
        return false;
    }

    public function get($topic,$id){
        try{
            $fd = $this->connect();
            if($fd===false){
                throw  new DqException(' connect server faild');
            }
            $data['cmd']='get';
            $data['topic'] = $topic;
            $data['id'] = $id;
            if(DqComm::socket_wirte($fd,$data)){
                $ret = DqComm::socket_read($fd);
                return $ret;
            }else{
                throw  new DqException('add error,data='.json_encode($data));
            }
        }catch (DqException $e){
            DqLog::writeLog($e->getDqMessage(),DqLog::LOG_TYPE_EXCEPTION);
            return false;
        }
    }



}

//$server=array('10.210.234.203:6879');
//
//$topic ='order_comment';
//$id = rand(1,10000);
//$data=array(
//    'id'=>$id, //topic＋业务唯一id的组合
//    'body'=>array('a'=>1,'b'=>2,'c'=>3),
//);
//
//$dqClient = new DqClient();
//$dqClient->addServer($server);
//
//var_dump($dqClient->add($topic,$data));
//var_dump($dqClient->get($topic,$id));
//var_dump($dqClient->del($topic,$id));
