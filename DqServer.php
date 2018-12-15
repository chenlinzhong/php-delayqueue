<?php
include_once 'DqLoader.php';

class DqServer{
    private $fd = null;

    private $clientsObjects=array(); //所有客户端对象


    public function  createClientsObject($newfd){
        if(is_resource($newfd)) {
            if (count($this->clientsObjects) >= DqConf::$max_connection) {
                DqLog::writeLog('over max connection ' . DqConf::$max_connection . ',will reject', DqLog::LOG_TYPE_EXCEPTION);
                socket_close($newfd);
                return false;
            }
            list($ip, $port) = $this->getIpPortFromFd($newfd);
            $clients = array(
                'fd' => $newfd,
                'ip' => $ip,
                'port' => $port,
                'create_time' => date('Y-m-d H:i:s'),
            );
            $key = $this->getClientKey($newfd);
            $this->clientsObjects[$key] = $clients;
            self::wirteLog("client_create {$ip}:{$port},clients=" . count($this->clientsObjects).',maxfd='.intval($newfd));
        }
    }

    //根据fd获取在对象数组中的下标
    private function getClientKey($newfd){
        $num = intval($newfd);
        return sprintf('fd:%s',$num);
    }

    //删除对象
    public function delClientsObject($newfd){
        if(is_resource($newfd)) {
            socket_close($newfd);
        }
        $key = $this->getClientKey($newfd);
        if(isset($this->clientsObjects[$key])) {
            unset($this->clientsObjects[$key]);
            DqLog::writeLog('now_clients,clients='.count($this->clientsObjects));
        }
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
        $time = time();
        foreach ($this->clientsObjects as $v){
            $cfd = $v['fd'];
            $key = $this->getClientKey($cfd);
            $client = isset($this->clientsObjects[$key]) ? $this->clientsObjects[$key] : array();
            //超过5分钟断开连接
            if(!empty($client) && ($time-strtotime($client['create_time']))>300){
                $this->delClientsObject($cfd);
                $strMsg = sprintf('clients over max time,will disconnect,ip=%s:%s',$client['ip'],$client['port']);
                DqLog::writeLog($strMsg,DqLog::LOG_TYPE_EXCEPTION);
                continue;
            }
            array_push($fds,$cfd);
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
            self::wirteLog("listen on $ip:$port,warting...");
            //线上不能使用echo输出，会导致EIO报错，程序异常退出
            //echo "listen on $ip:$port,warting..."."\n";
            DqMain::install_sig_usr1();
            //SIGPIPE信号忽略
            pcntl_signal(SIGPIPE, SIG_IGN, false);
            while(true){
                try{
                    pcntl_signal_dispatch();
                    if(DqMain::$stop){
                        DqRedis::incr_force(); //刷新统计数据
                        DqMain::sig_stop_check();
                    }
                    $read = array_merge(array($this->fd),$this->getAllClientsFd());
                    @socket_select($read, $write,  $exce, DqConf::get_socket_select_timeout());
                    //刷新数据
                    if (count($read) == 0) {
                        DqRedis::incr_force(); //刷新统计数据
                    }
                    //检测是否有新的链接过来
                    $this->check_new_connection($read);
                    $read = $this->array_del($this->fd, $read);
                    //处理请求
                    $this->handle_request($read);
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
        if(!is_resource($cfd)){
            return false;
        }
        switch ($arr['cmd']) {
            case 'add':
                if(DqRedis::handle($arr)) {
                    $this->sendReply($cfd, 1, 'succ');
                }else{
                    $this->sendReply($cfd, 0, 'fail');
                }
                break;
            case 'del':
                $id = $arr['id'];
                $topic = $arr['topic'];
                $tid = DqRedis::create_tid($topic,$id);

                $ret = DqRedis::delMsg($topic,$tid,true);
                if($ret){
                    $this->sendReply($cfd,1,'succ');
                }else{
                    $this->sendReply($cfd,0,'fail');
                }
                break;
            case 'get':
                $id = $arr['id'];
                $topic = $arr['topic'];
                $tid  = DqRedis::create_tid($topic,$id);
                $result = DqRedis::getBody($tid);
                if(!empty($result)) {
                    $this->sendReply($cfd, 1, 'succ',$result);
                }else{
                    $this->sendReply($cfd, 0, 'fail');
                }
                break;
            default:
                break;
        }
    }



    public function sendReply($fd,$code,$msg,$data=array()){
        $reply = array('code'=>$code,'msg'=>$msg,'data'=>$data);
        if(DqComm::socket_wirte($fd,$reply) ===false){ //如果写入异常
            $this->delClientsObject($fd);
            DqLog::writeLog('response error,msg='.json_encode($reply).' clients='.count($this->clientsObjects),DqLog::LOG_TYPE_EXCEPTION);
        }
    }



    public function check_new_connection(&$read){
        if(in_array($this->get_fd(),$read)){
            $newfd = socket_accept($this->get_fd());
            if(is_resource($newfd)){
                $this->createClientsObject($newfd);
            }else{
                DqLog::writeLog('socket_accept failed');
                $errorcode = socket_last_error();
                $errormsg = socket_strerror($errorcode);
                DqLog::writeLog('socket_accept failed,msg='.$errormsg,DqLog::LOG_TYPE_EXCEPTION);
            }
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


