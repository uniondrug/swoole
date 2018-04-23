<?php
/**
 * @author    jan huang <bboyjanhuang@gmail.com>
 * @copyright 2016
 *
 * @link      https://www.github.com/janhuang
 * @link      http://www.fast-d.cn/
 */

namespace Uniondrug\Swoole\Server;

use Exception;
use Psr\Http\Message\ServerRequestInterface;
use swoole_http_request;
use swoole_http_response;
use swoole_http_server;
use swoole_server;
use Uniondrug\Http\HttpException;
use Uniondrug\Http\Response;
use Uniondrug\Http\SwooleServerRequest;
use Uniondrug\Swoole\Server;

/**
 * Class HttpServer
 *
 * @package FastD\Swoole\Server
 */
abstract class HTTP extends Server
{
    const SERVER_INTERVAL_ERROR = 'Server Interval Error';

    const SCHEME = 'http';

    /**
     * @return \swoole_http_server
     */
    public function initSwoole()
    {
        return new swoole_http_server($this->getHost(), $this->getPort());
    }

    /**
     * @param swoole_http_request  $swooleRequet
     * @param swoole_http_response $swooleResponse
     */
    public function onRequest(swoole_http_request $swooleRequet, swoole_http_response $swooleResponse)
    {
        try {
            $swooleRequestServer = SwooleServerRequest::createServerRequestFromSwoole($swooleRequet);
            $response = $this->doRequest($swooleRequestServer);
            $this->sendHeader($swooleResponse, $response);
            $swooleResponse->status($response->getStatusCode());
            $swooleResponse->end((string) $response->getBody());
            unset($response);
        } catch (HttpException $e) {
            $swooleResponse->status($e->getStatusCode());
            $swooleResponse->end($e->getMessage());
        } catch (Exception $e) {
            $swooleResponse->status(500);
            $swooleResponse->end(static::SERVER_INTERVAL_ERROR);
        }
    }

    /**
     * @param swoole_http_response $swooleResponse
     * @param Response             $response
     */
    protected function sendHeader(swoole_http_response $swooleResponse, Response $response)
    {
        foreach ($response->getHeaders() as $key => $header) {
            $swooleResponse->header($key, $response->getHeaderLine($key));
        }

        foreach ($response->getCookieParams() as $key => $cookieParam) {
            $swooleResponse->cookie($key, $cookieParam);
        }
    }

    /**
     * @param ServerRequestInterface $serverRequest
     *
     * @return Response
     */
    abstract public function doRequest(ServerRequestInterface $serverRequest);

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