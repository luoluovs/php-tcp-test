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
class SlectServer extends EventEmitter implements LoggerAwareInterface,ServerInterface
{
    use LoggerAwareTrait;

    public const SELECT_TIMEOUT = 0;
    //主socket链接
    protected $master;

    protected $connection;

    protected $resource = [];

    protected $booted = false;

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

        //设置选项
        socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);

        //绑定地址
        if(socket_bind($socket,$domain,$port) === false){
            $this->thorwLastError();
        }

        //设置非阻塞
        socket_set_nonblock($socket);

        $this->master = $socket;
        $this->connection = new \SplObjectStorage();
        $this->logger = new NullLogger();
    }

    /**
     * close : 如果不在运行状态，关闭连接
     ** created by zhangjian at 2020/7/24 13:18
     */
    public function close()
    {
        if (!$this->running) {
            return;
        }
        socket_close($this->master);
    }

    /**
     * quit : 停止运行
     ** created by zhangjian at 2020/7/24 13:19
     */
    public function quit(){
        $this->running = false;
    }

    /**
     * resume : 开始监听连接，打开运行状态
     ** created by zhangjian at 2020/7/24 13:24
     */
    public function resume(){

        if(!$this->booted){
            $this->booted = true;

            if(socket_listen($this->master) === false){
                $this->thorwLastError();
            }
        }

        $this->running = true;

        if(!in_array($this->master,$this->resource)){
            $this->resource[(int)$this->master] = $this->master;
        }
    }

    /**
     * run : 运行服务
     * @throws \Exception
     ** created by zhangjian at 2020/7/24 13:25
     */
    public function run()
    {
        $this->resume();
        $this->logger->info('SlectServer start');

        while ($this->running) {
            //$this->logger->info('SlectServer waiting for connection ...');

            $reads = $this->resource;
            $writes = [];
            $except = [];

            //获取read数组中活动的socket，并且把不活跃的从read数组中删除
            //这是一个同步方法，必须得到响应之后才会继续下一步,常用在同步非阻塞IO

            /*
             * socket_select 接受三个套接字数组，分别检查数组中的套接字是否处于可以操作的状态（返回时只保留可操作的套接字）
                使用最多的是 $read，因此以读为例
                在套接字数组 $read 中最初应保有一个服务端监听套接字
                每当该套接字可读时，就表示有一个用户发起了连接。此时你需要对该连接创建一个套接字，并加入到 $read 数组中
                当然，并不只是服务端监听的套接字会变成可读的，用户套接字也会变成可读的，此时你就可以读取用户发来的数据了
                socket_select 只在套接字数组发生了变化时才返回。也就是说，一旦执行到 socket_select 的下一条语句，则必有一个套接字是需要你操作的
            */

            if(socket_select($reads,$writes,$except,self::SELECT_TIMEOUT) <1 ){
                continue;
            }

            //当有新的请求时
            if(in_array($this->master,$reads)){
                if(($newSocket = socket_accept($this->master)) === false){
                    $this->emit('error', [$this->lastError()]);
                    continue;
                }
                //处理新的请求连接
                $this->handleNewConnection($newSocket);

                //主soclket请求不需要处理
                unset($reads[array_search($this->master,$this->resource)]);
            }

            // 因为PHP是单线程，下面两个foreach循环操作无可避免是阻塞的。

            // 如果handleReadAction 和 handleWriteAction 方法需要执行时间较长，会影响到整个server的通信。
            // 程序被阻塞在此，就无法及时接收新的连接和处理新到达的数据。
            foreach ($reads as $read){
                $this->handleReadAction($read);
            }
            foreach ($writes as $write){
                $this->handleWriteAction($write);
            }

        }
    }

    /**
     * handleReadAction : 读取资源
     * @param $resource
     * @throws \Exception
     ** created by zhangjian at 2020/7/24 14:10
     */
    protected function handleReadAction($resource){
        $connection = new Connection($this,$resource);
        if(($data = $connection->read()) === false){
            $this->emit('error',[$this->lastError()]);
            $connection->close();
        }

        if(!$data == trim($data)){
            return ;
        }

        if ($data == 'quit') {
            $connection->close();
            $this->logger->info('client quit ...');
            return;
        }

        $this->emit('data', [$connection, $data]);
    }

    protected function handleWriteAction($resource){
        return $resource;
    }

    /**
     * handleNewConnection : 处理新请求
     * @param $socket
     ** created by zhangjian at 2020/7/24 14:14
     */
    public function handleNewConnection($socket)
    {
        $connection = new Connection($this, $socket);
        $this->logger->info('new client from :' . $connection->getRemoteAddr());

        $this->resource[(int)$socket] = $socket;

        //触发connection事件,监听connection事件作出反应
        $this->emit('connection', [$connection]);
    }

    /**
     * removeConnection : 移除某个连接，也就是移除某个资源
     * @param ConnectionInterface $connection
     ** created by zhangjian at 2020/7/24 14:14
     */
    public function removeConnection(ConnectionInterface $connection)
    {
        $resource = $connection->getResource();
        $resourceId = (int) $resource;

        if(isset($this->resource[$resourceId])){
            unset($this->resource[$resourceId]);
        }
    }

    protected function lastError()
    {
        $error = socket_strerror(socket_last_error());
        $this->logger->error('SlectServer connection error : ' . $this->encodeing($error));
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