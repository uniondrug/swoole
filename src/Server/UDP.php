<?php
/**
 * @author    jan huang <bboyjanhuang@gmail.com>
 * @copyright 2016
 *
 * @link      https://www.github.com/janhuang
 * @link      http://www.fast-d.cn/
 */

namespace Uniondrug\Swoole\Server;

use swoole_server;
use Uniondrug\Swoole\Server;

/**
 * Class Udp
 *
 * @package FastD\Swoole\Server
 */
abstract class UDP extends Server
{
    const SCHEME = 'udp';

    /**
     * 服务器同时监听TCP/UDP端口时，收到TCP协议的数据会回调onReceive，收到UDP数据包回调onPacket
     *
     * @param swoole_server $server
     * @param string        $data
     * @param array         $clientInfo
     *
     * @return void
     */
    public function onPacket(swoole_server $server, $data, array $clientInfo)
    {
        try {
            $this->doPacket($server, $data, $clientInfo);
        } catch (\Exception $e) {
            $content = sprintf("Error: %s\nFile: %s \n Code: %s",
                $e->getMessage(),
                $e->getFile(),
                $e->getCode()
            );
            $server->sendto($clientInfo['address'], $clientInfo['port'], $content);
        }
    }

    /**
     * @param swoole_server $server
     * @param               $data
     * @param               $clientInfo
     *
     * @return mixed
     */
    abstract public function doPacket(swoole_server $server, $data, $clientInfo);

    /**
     * @param swoole_server $server
     * @param               $data
     * @param               $taskId
     * @param               $workerId
     *
     * @return mixed
     */
    public function doTask(swoole_server $server, $data, $taskId, $workerId)
    {
    }

    /**
     * @param swoole_server $server
     * @param               $data
     * @param               $taskId
     *
     * @return mixed
     */
    public function doFinish(swoole_server $server, $data, $taskId)
    {
    }

    /**
     * @param swoole_server $server
     * @param               $fd
     * @param               $from_id
     */
    public function doConnect(swoole_server $server, $fd, $from_id)
    {
    }

    /**
     * @param swoole_server $server
     * @param               $fd
     * @param               $fromId
     */
    public function doClose(swoole_server $server, $fd, $fromId)
    {
    }

    /**
     * @param \swoole_server $server
     * @param int            $src_worker_id
     * @param mixed          $message
     *
     * @return mixed|void
     */
    public function doPipeMessage(swoole_server $server, int $src_worker_id, $message)
    {
    }
}