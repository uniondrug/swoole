<?php
/**
 * @author    jan huang <bboyjanhuang@gmail.com>
 * @copyright 2016
 *
 * @link      https://www.github.com/janhuang
 * @link      http://www.fast-d.cn/
 */

namespace Uniondrug\Swoole\Server;

use swoole_http_request;
use swoole_server;
use swoole_websocket_frame;
use swoole_websocket_server;
use Uniondrug\Swoole\Server;

/**
 * Class WebSocketServer
 *
 * @package FastD\Swoole\Server\WebSocket
 */
abstract class WebSocket extends Server
{
    protected $scheme = 'ws';

    /**
     * @param swoole_websocket_server $server
     * @param swoole_http_request     $request
     *
     * @return mixed
     */
    public function onOpen(swoole_websocket_server $server, swoole_http_request $request)
    {
        return $this->doOpen($server, $request);
    }

    /**
     * @param swoole_websocket_server $server
     * @param swoole_http_request     $request
     *
     * @return mixed
     */
    public function doOpen(swoole_websocket_server $server, swoole_http_request $request)
    {
    }

    /**
     * @param swoole_server          $server
     * @param swoole_websocket_frame $frame
     *
     * @return mixed
     */
    public function onMessage(swoole_server $server, swoole_websocket_frame $frame)
    {
        return $this->doMessage($server, $frame);
    }

    /**
     * @param swoole_server          $server
     * @param swoole_websocket_frame $frame
     *
     * @return mixed
     */
    abstract public function doMessage(swoole_server $server, swoole_websocket_frame $frame);

    /**
     * @return swoole_websocket_server
     */
    public function initSwoole()
    {
        return new swoole_websocket_server($this->host, $this->port);
    }

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