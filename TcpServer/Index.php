<?php
/**
 * Created by PhpStorm.
 * User: XH18
 * Date: 2020/7/24
 * Time: 14:15
 */

namespace TcpServer;

require __DIR__ . '/vendor/autoload.php';


use Psr\Log\AbstractLogger;

spl_autoload_register(function ($class){
    require __DIR__.'/../'.$class.'.php';
});

class Logger extends AbstractLogger
{
    public function log($level, $message, array $context = array())
    {
        echo sprintf("%s: %s %s", $level, $message, !empty($context) ? json_encode($context) : '') . PHP_EOL;
    }
}

// listen on address 127.0.0.1 and port 8000
$echoServer = new SlectServer('127.0.0.1', 8080);
//$echoServer = new \Hbliang\SimpleTcpServer\BlockServer('127.0.0.1', 8000);

// trigger while receiving data from client
$echoServer->on('data', function (Connection $connection, $data) {
    // send data to client
    $connection->write($data . PHP_EOL);
});

// trigger when new connection comes
$echoServer->on('connection', function (Connection $connection) {
    $connection->write('welcome' .PHP_EOL);
});

// trigger when occur error
$echoServer->on('error', function (\Exception $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
});

$echoServer->setLogger(new Logger());

$echoServer->run();






