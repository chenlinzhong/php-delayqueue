<?php
include_once 'DqLoader.php';

class DqServer{
    private $fd = null;
    private $topicConsumeClientList=array();

    private $clientsObjects=array(); //所有客户端对象


    public function  createClientsObject($newfd){
        if(count($this->clientsObjects)>=DqConf::$max_connection){
            Tool_Log_Commlog::writeLog('over max connection '.DqConf::$max_connection.',will reject',DqLog::LOG_TYPE_EXCEPTION);
            socket_close($newfd);
            return ;
        }
        list($ip,$port) = $this->getIpPortFromFd($newfd);
        self::wirteLog("client_create {$ip}:{$port}");
        $clients=array(
            'fd'=> $newfd,
            'ip'=>$ip,
            'port'=>$port,
            'create_time'=>date('Y-m-d H:i:s'),         
        );
        $key = $this->getClientKey($newfd);
        $this->clientsObjects[$key] = $clients;
    }

    //根据fd获取在对象数组中的下标
    private function getClientKey($newfd){
        $num = intval($newfd);
        return sprintf('fd:%s',$num);
    }


    //获取对象
    public function getClientsObject($newfd){
        $key = $this->getClientKey($newfd);
        return isset($this->clientsObjects[$key]) ? $this->clientsObjects[$key]: false;
    }

    //删除对象
    public function delClientsObject($newfd){
        socket_close($newfd);
        //s$strClients = $this->clientToStr($newfd);
        $key = $this->getClientKey($newfd);
        unset($this->clientsObjects[$key]);
        $this->del_topic_client($newfd);
        //self::wirteLog('client_del,client info='.$strClients);
    }

    //序列化对象
    private function clientToStr($newfd){
        $key = $this->getClientKey($newfd);
        $clientObject = $this->clientsObjects[$key];
        unset($clientObject['fd']);
        $strClients = json_encode($clientObject);
        return $strClients;
    }

    //获取所有客户端连接的fd
    public function getAllClientsFd(){
        $fds = array();
        foreach ($this->clientsObjects as $v){
            array_push($fds,$v['fd']);
        }
        return $fds;
    }

    //从数组删除一个元素，并返回删除后数组
    public function array_del($element,$arr){
        $key = array_search($element,$arr);
        if($key!==false){
            unset($arr[$key]);
        }
        return $arr;
    }

    public  function run(){
        try {
            $fd = $this->get_fd();
            if($fd===false){
                throw  new DqException('socket error,will exitd');
            }

            list($ip,$port) = $this->getIpPortFromFd($fd);
            echo "listen on $ip:$port,warting...";
            self::wirteLog("listen on $ip:$port,warting...");
            DqMain::install_sig_usr1();
            while(true){
                try{
                    $read = array_merge(array($this->fd),$this->getAllClientsFd());
                    socket_select($read, $write,  $exce, DqConf::get_socket_select_timeout());
                    if(socket_last_error()==4){  //系统中断
                        DqMain::$stop =1;
                        DqRedis::incr_force(); //刷新统计数据
                        DqLog::writeLog('server Interrupted system call,will exit now ,bye... ');
                        exit(0);
                    }
                    //刷新数据
                    if(count($read)==0){
                       DqRedis::incr_force(); //刷新统计数据
                    }
                    //检测是否有新的链接过来
                    $this->check_new_connection($read);
                    $read = $this->array_del($this->fd,$read);
                    
                    //处理请求
                    $this->handle_request($read);
                    //处理回复
                    $this->sendReply();
                }catch (Exception $e){
                    self::wirteLog($e->getMessage(),DqLog::LOG_TYPE_EXCEPTION);
                }
            }
        }catch (DqException $e){
            self::wirteLog($e->getDqMessage());
        }

    }
    
    public function handle_request($fdsRead){
        foreach ($fdsRead as $cfd){
            $arrMsg = DqComm::socket_read($cfd);
            if($arrMsg===false){ //异常： 连接关闭，数据格式不对
                $this->delClientsObject($cfd);
            }else{
                $this->handle_msg($cfd,$arrMsg);
            }
        }
    }

    //处理消息
    public function handle_msg($cfd,$arr){
        switch ($arr['cmd']) {
            case 'add':
                if(DqRedis::handle($arr)) {
                    $this->addReply($cfd, 1, 'succ');
                }else{
                    $this->addReply($cfd, 0, 'fail');
                }
                break;
            case 'del':
                $id = $arr['id'];
                $topic = $arr['topic'];
                $tid = DqRedis::create_tid($topic,$id);

                $ret = DqRedis::delMsg($topic,$tid,true);
                if($ret){
                    $this->addReply($cfd,1,'succ');
                }else{
                    $this->addReply($cfd,0,'fail');
                }
                break;
            case 'get':
                $id = $arr['id'];
                $topic = $arr['topic'];
                $tid  = DqRedis::create_tid($topic,$id);
                $result = DqRedis::getBody($tid);
                if(!empty($result)) {
                    $this->addReply($cfd, 1, 'succ',$result);
                }else{
                    $this->addReply($cfd, 0, 'fail');
                }
                break;
            default:
                break;
        }
    }

    public  function add_topic_client($topic,$fd){
        $this->topicConsumeClientList[$topic][] = intval($fd);
    }

    public function del_topic_client($fd){
        $fd = intval($fd);
        $allTopics = array_keys($this->topicConsumeClientList);
        foreach ($allTopics as $topic){
            $this->topicConsumeClientList[$topic] = $this->array_del($fd,$this->topicConsumeClientList[$topic]);
        }
    }

    static $readyToReplyfds=array();
    public function addReply($newfd,$code,$msg='',$data=array()){
        $reply = array('code'=>$code,'msg'=>$msg,'data'=>$data);
        $key = $this->getClientKey($newfd);
        if(isset($this->clientsObjects[$key])){
            $this->clientsObjects[$key]['reply']=$reply;
            //记录需要回复的fd
            if(!in_array($newfd,self::$readyToReplyfds)) {
                self::$readyToReplyfds[] = $newfd;
            }
            return true;
        }else{
            DqLog::writeLog('fd='.intval($newfd).'not found fd,data='.json_encode($data),DqLog::LOG_TYPE_EXCEPTION);
            return false;
        }
    }
    public function getReply($newfd){
        $key = $this->getClientKey($newfd);
        if(isset($this->clientsObjects[$key]['reply'])){
            return $this->clientsObjects[$key]['reply'];
        }else{
            DqLog::writeLog('fd='.intval($newfd).'not found fd getReply',DqLog::LOG_TYPE_EXCEPTION);
            return false;
        }
    }

    public function delReplay($newfd){
        $key = $this->getClientKey($newfd);
        //删除已经回复的fd
        self::$readyToReplyfds = $this->array_del($newfd,self::$readyToReplyfds);
        unset($this->clientsObjects[$key]['reply']);
    }

    public function sendReply(){
        foreach (self::$readyToReplyfds as $fd){
            $reponse = $this->getReply($fd);
            if(empty($reponse)){
                $this->delClientsObject($fd);
                DqLog::writeLog($this->clientToStr($fd),DqLog::LOG_TYPE_EXCEPTION);
            }
            if(DqComm::socket_wirte($fd,$reponse) ===false){ //如果写入异常
                $this->delClientsObject($fd);
                DqLog::writeLog($this->clientToStr($fd).' response error,msg='.json_encode($reponse));
            }else{
                $this->delReplay($fd);
            }
        }
    }



    public function check_new_connection(&$read){
        if(in_array($this->get_fd(),$read)){
            $newfd = socket_accept($this->get_fd());
            $this->createClientsObject($newfd);
        }
    }

    public function getIpPortFromFd($fd){
        try {
            if ($this->fd !== $fd) {
                socket_getpeername($fd, $ip, $port);
            } else {
                socket_getsockname($fd, $ip, $port);
            }
            return array($ip, $port);
        }catch (Exception $e){
            self::wirteLog($e->getMessage().$e->getLine());
        }
    }

    //创建服务端socket
    private  function get_fd(){
        if(!is_null($this->fd)){
            return $this->fd;
        }
        try {
            if (($sfd = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) === false) {
                throw  new DqException("socket_create() failed reason: " . socket_strerror(socket_last_error()));
            }
            if (socket_bind($sfd,DqConf::getLocalHost(), DqConf::getListenPort()) === false) {
                socket_close($sfd);
                throw  new DqException("socket_bind() failed reason: " .  socket_strerror(socket_last_error()).',');
            }
            if (socket_listen($sfd, DqConf::getListenQueueLen()) === false) {
                socket_close($sfd);
                throw  new DqException('socket_listen failed reason:'.socket_strerror(socket_last_error()));
            }
            if (!socket_set_option($sfd, SOL_SOCKET, SO_REUSEADDR, 1)) {
                socket_close($sfd);
                throw  new DqException('socket_set_option failed reason:'.socket_strerror(socket_last_error()));
            }
            $this->fd = $sfd;
            return $sfd;
        }catch (DqException $e){
            self::wirteLog($e->getDqMessage(),DqLog::LOG_TYPE_EXCEPTION);
        }
        return false;
    }


    //日志接口
    public static function  wirteLog($strMsg,$flag=DqLog::LOG_TYPE_NORMAL){
        DqLog::writeLog($strMsg,$flag);
    }

}


