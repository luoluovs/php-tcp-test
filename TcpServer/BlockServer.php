<?php
/**
 * Created by PhpStorm.
 * User: XH18
 * Date: 2020/7/24
 * Time: 9:50
 */

namespace TcpServer;

use Evenement\EventEmitter;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;

//一个阻塞的 Server
class BlockServer extends EventEmitter implements LoggerAwareInterface, ServerInterface
{
    use LoggerAwareTrait;

    //主socket链接
    protected $master;

    protected $connection;

    public $loggor;

    public $running = false;


    public function __construct($domain = 'localhost', $port = 8080)
    {
        //创建一个socket链接
        //getprotobyname 获取网络协议编号
        //AF_INET 在windows中AF_INET与PF_INET完全一样,然后在绑定本地地址或连接远程地址时需要初始化sockaddr_in结构，其中指定address family时一般设置为AF_INET，即使用IP
        // AF PF刚好对应address family, protocol family
        //SOCK_STREAM是基于TCP的，数据传输比较有保障。SOCK_DGRAM是基于UDP的，专门用于局域网，
        //基于广播SOCK_STREAM 是数据流,一般是tcp/ip协议的编程,SOCK_DGRAM分是数据包,是udp协议网络编程
        $socket = socket_create(AF_INET, SOCK_STREAM, getprotobyname('tcp'));
        if($socket === false){
            $this->thorwLastError();
        }

        //绑定地址
        if(socket_bind($socket,$domain,$port) == false){
            $this->thorwLastError();
        }


        $this->master = $socket;
        $this->connection = new \SplObjectStorage();
        $this->logger = new NullLogger();
    }

    public function close()
    {
        if (!$this->running) {
            return;
        }
        socket_close($this->master);
    }

    public function run()
    {
        //监听请求的连接
        if (socket_listen($this->master) === false) {
            $this->thorwLastError();
        }
        $this->running = true;

        $this->logger->info('BlockServer start');

        while ($this->running) {
            $this->logger->info('BlockServer waiting for connection ...');
            //接受一个新的连接，返回一个子的socket
            $socket = socket_accept($this->master);
            if ($socket === false) {
                $this->emit('error', [$this->lastError()]);
                continue;
            }
            //处理新的请求连接
            $this->handleNewConnection($socket);
        }
    }

    public function handleNewConnection($socket)
    {
        $connection = new Connection($this, $socket);
        $this->logger->info('new client from :' . $connection->getRemoteAddr());

        $this->connection->attach($connection);

        //触发connection事件,监听connection事件作出反应
        $this->emit('connection', [$connection]);

        do {
            if (($data = $connection->read($socket)) === false) {
                $this->emit('error', [$this->lastError()]);
                $connection->close();
                break;
            }

            //忽略空消息
            if (!$data == trim($data)) {
                continue;
            }

            //
            if ($data == 'quit') {
                $connection->close();
                $this->logger->info('client quit ...');
                break;
            }
            $this->emit('data', [$connection, $data]);
        } while (true);
    }

    public function removeConnection(ConnectionInterface $connection)
    {
        $this->connection->detach($connection);
    }

    protected function lastError()
    {
        $error = socket_strerror(socket_last_error());
        $this->logger->error('BlockServer connection error : ' . $this->encodeing($error));
        throw new \Exception($error);
    }

    public function thorwLastError()
    {
        return $this->lastError();
    }

    public function encodeing($str){
        $encode = strtoupper(mb_detect_encoding($str, ["ASCII",'UTF-8',"GB2312","GBK",'BIG5']));
        if($encode!='UTF-8'){
            $str = mb_convert_encoding($str, 'UTF-8', $encode);
        }

        return $str;
    }

}