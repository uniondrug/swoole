<?php
/**
 * @author    jan huang <bboyjanhuang@gmail.com>
 * @copyright 2016
 *
 * @link      https://www.github.com/janhuang
 * @link      http://www.fast-d.cn/
 */

use FastD\Swoole\Server\TCP;

include __DIR__ . '/../../vendor/autoload.php';

/**
 * Class DemoServer
 */
class DemoServer extends TCP
{
    public function doConnect(swoole_server $server, $fd, $from_id)
    {
        parent::doConnect($server, $fd, $from_id);
        $server->send($fd, 'connect');
    }

    public function doWork(swoole_server $server, $fd, $data, $from_id)
    {
        echo $fd;
        echo $data . PHP_EOL;
        $server->task($data);
        $server->send($fd, $data."\r\n");
        return $data;
    }

    public function doTask(swoole_server $server, $data, $taskId, $workerId)
    {
        echo $data . ' on task' . PHP_EOL;
        return $data;
    }

    public function doFinish(swoole_server $server, $data, $taskId)
    {
        echo $data . 'Finish' . PHP_EOL;
    }
}

return DemoServer::createServer('tcp swoole', '0.0.0.0:9527', [
]);