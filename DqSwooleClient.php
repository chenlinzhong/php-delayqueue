<?php
class DqSwooleClient
{


    private $serverList = array();
    private $fd = NULL;

    public function addServer($server)
    {
        if (is_string($server)) {
            $this->serverList[] = $server;
        }
        if (is_array($server) && !empty($server)) {
            $this->serverList = array_merge($this->serverList, $server);
        }
        $this->serverList = array_unique($this->serverList);
    }


    public function connect()
    {
        if (!empty($this->fd)) {
            return $this->fd;
        }
        if (empty($this->serverList)) {
            DqLog::writeLog('empty server list');
            return false;
        }
        $serverList = $this->serverList;

        while (count($serverList)) {
            try {
                $idx = rand(0, count($serverList) - 1);
                $server = $serverList[$idx];
                list($host, $port) = explode(':', $server);

                $fd = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
                //1s内没处理完直接返回
                socket_set_option($fd, SOL_SOCKET, SO_RCVTIMEO, array("sec" => 5, "usec" => 0));
                if (!is_resource($fd)) {
                    $strMsg = 'socket_create error:' . socket_strerror(socket_last_error());
                    throw  new DqException($strMsg);
                }
                if (!socket_connect($fd, $host, $port)) {
                    $strMsg = 'socket_create error:' . socket_strerror(socket_last_error() . ' ip=' . $host . ' port=' . $port);
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

    public function add($topic,$data){
        try{
            $fd = $this->connect();
            if($fd===false){
                throw  new DqException(' connect server faild');
            }
            $data['cmd']='add';
            $data['topic'] = $topic;
            $message = json_encode($data);
            $sendMessage = pack("N", strlen($message)) . $message;
            if(socket_write($fd,$sendMessage)){
                echo socket_read($fd,1024)."\n\n";
            }else{
                echo 'send message fail'."\n";
            }

        }catch (DqException $e){
            DqLog::writeLog($e->getDqMessage(),DqLog::LOG_TYPE_EXCEPTION);
        }
        return false;
    }

}