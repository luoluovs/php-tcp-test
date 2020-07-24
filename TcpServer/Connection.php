<?php
/**
 * Created by PhpStorm.
 * User: XH18
 * Date: 2020/7/24
 * Time: 9:23
 */

namespace TcpServer;

class Connection implements ConnectionInterface {

    public const MAX_READ = 2048;


    protected $server;

    protected $resource;

    public function __construct(ServerInterface $server,$resource)
    {
        $this->server = $server;
        $this->resource = $resource;
    }

    public function getRemoteAddr(){
        socket_getpeername($this->resource,$ip,$port);
        return $ip. ':' .$port;
    }

    public function getLocalAddr(){
        socket_getsockname($this->resource,$ip,$port);
        return $ip.':'.$port;
    }

    public function close(){
        socket_close($this->resource);
        $this->server->removeConnection($this);
    }

    public function write($data){
        return socket_write($this->resource,(string)$data);
    }

    public function read(){
        return socket_read($this->resource,self::MAX_READ,PHP_BINARY_READ);
    }

    public function getResource(){
        return $this->resource;
    }

}

