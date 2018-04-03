<?php
/**
 * @author    jan huang <bboyjanhuang@gmail.com>
 * @copyright 2016
 *
 * @link      https://www.github.com/janhuang
 * @link      http://www.fast-d.cn/
 */

namespace Uniondrug\Swoole;

use LogicException;
use RuntimeException;
use swoole_client;
use swoole_http_client;
use Uniondrug\Http\Cookie;
use Uniondrug\Packet\Json;

/**
 * Class Client
 *
 * @package FastD\Swoole
 */
class Client
{
    const HTTP_VERSION = '1.1';

    const USER_AGENT = 'PHP swoole/2.1 (+https://github.com/uniondrug/swoole)';

    /**
     * @var swoole_client
     */
    protected $client;

    /**
     * @var string
     */
    protected $method = 'GET';

    /**
     * @var string
     */
    protected $path = '/';

    /**
     * @var array
     */
    protected $headers = [];

    /**
     * @var Cookie[]
     */
    protected $cookies = [];

    /**
     * @var string
     */
    protected $scheme;

    /**
     * @var string
     */
    protected $host;

    /**
     * @var string
     */
    protected $port;

    /**
     * @var int
     */
    protected $socketType = SWOOLE_SOCK_TCP;

    /**
     * @var int
     */
    protected $timeout = 1;

    /**
     * @var array
     */
    protected $callbacks = [];

    /**
     * @var bool
     */
    protected $async = false;

    /**
     * Client constructor.
     *
     * @param      $uri
     * @param bool $async
     * @param bool $keep
     */
    public function __construct($uri = null, $async = false, $keep = false)
    {
        if (null !== $uri) {
            $this->createRequest($uri, $async, $keep);
        }

        $this->on('connect', [$this, 'onConnect']);
        $this->on('receive', [$this, 'onReceive']);
        $this->on('error', [$this, 'onError']);
        $this->on('close', [$this, 'onClose']);
    }

    /**
     * @param      $url
     * @param bool $async
     * @param bool $keep
     *
     * @return $this
     */
    public function createRequest($url, $async = false, $keep = false)
    {
        $info = parse_url($url);
        $this->scheme = isset($info['scheme']) ? $info['scheme'] : 'http';
        $this->host = $info['host'];
        $this->port = isset($info['port']) ? $info['port'] : 80;
        $this->async = $async;

        switch ($this->scheme) {
            case 'tcp':
            case 'http':
                $socketType = SWOOLE_SOCK_TCP;
                break;
            case 'udp':
                $socketType = SWOOLE_SOCK_UDP;
                break;
            default:
                throw new LogicException("Don't support schema " . $info['scheme']);
        }

        $this->path = isset($info['path']) ? $info['path'] : '/';

        $sync = false === $async ? SWOOLE_SOCK_SYNC : SWOOLE_SOCK_ASYNC;
        $this->socketType = true === $keep ? ($socketType | SWOOLE_KEEP) : $socketType;

        // async
        if ($async && false !== strpos($this->scheme, 'http')) {
            $this->client = new swoole_http_client($this->host, $this->port);
        } else {
            $this->client = new swoole_client($this->socketType, $sync);
        }

        return $this;
    }

    /**
     * @param string|array $data
     *
     * @return string
     * @throws \Uniondrug\Packet\Exceptions\PacketException
     */
    protected function wrapBody($data = '')
    {
        // HTTP
        if (false !== strpos($this->scheme, 'http')) {
            // Body
            $body = '';
            if (!empty($data)) {
                if (!in_array($this->method, ['GET', 'HEAD', 'OPTIONS'])) {
                    $body = Json::encode((array) $data);
                    $this->setHeader('Content-Length', strlen($body));
                    $this->setHeader('Content-Type', 'application/json');
                } else {
                    if (is_array($data)) {
                        $data = http_build_query($data);
                    }
                    $this->path .= (false === strpos($this->path, '?') ? '?' : '&') . $data;
                }
            }

            //Cookie
            $cookies = '';
            foreach ($this->cookies as $cookie) {
                $cookies .= $cookie->asString();
            }

            //Header
            $ua = static::USER_AGENT;
            $version = static::HTTP_VERSION;
            $header = "{$this->method} {$this->path} HTTP/{$version}\r\n";
            foreach ($this->headers as $key => $value) {
                $header .= "$key: " . (is_array($value) ? implode(',', $value) : $value) . "\r\n";
            }
            if (!empty($cookies)) {
                $header .= "Cookie: {$cookies}\r\n";
            }
            $header .= "Accept: application/json\r\n";
            $header .= "User-Agent: {$ua}\r\n";
            $header .= "\r\n";

            $data = $header . $body;
        } else {
            $body = [
                'method'  => $this->method,
                'path'    => $this->path,
                'headers' => $this->headers,
            ];
            if (!empty($data)) {
                $body['body'] = (array) $data;
            }
            $data = Json::encode($body);
        }

        return $data;
    }

    /**
     * @param $method
     *
     * @return $this
     */
    public function setMethod($method)
    {
        $this->method = $method;

        return $this;
    }

    /**
     * @param $path
     *
     * @return $this
     */
    public function setPath($path)
    {
        $this->path = $path;

        return $this;
    }

    /**
     * @param      $name
     * @param null $value
     * @param null $expire
     * @param null $path
     * @param null $domain
     * @param null $secure
     * @param null $httpOnly
     *
     * @return $this
     */
    public function setCookie(
        $name,
        $value = null,
        $expire = null,
        $path = null,
        $domain = null,
        $secure = null,
        $httpOnly = null
    )
    {
        $this->cookies[] = new Cookie($name, $value, $expire, $path, $domain, $secure, $httpOnly);

        return $this;
    }

    /**
     * @param array $cookies
     *
     * @return $this
     */
    public function setCookies(array $cookies)
    {
        $this->cookies = array_merge($this->cookies, $cookies);

        return $this;
    }

    /**
     * @param $key
     * @param $value
     *
     * @return $this
     */
    public function setHeader($key, $value)
    {
        $this->headers[$key] = $value;

        return $this;
    }

    /**
     * @param $timeout
     *
     * @return $this
     */
    public function setTimeout($timeout)
    {
        $this->timeout = $timeout;

        return $this;
    }

    /**
     * @param array $headers
     *
     * @return $this
     */
    public function setHeaders(array $headers)
    {
        $this->headers = array_merge($this->headers, $headers);

        return $this;
    }

    /**
     * @param $name
     * @param $handler
     *
     * @return mixed
     */
    public function on($name, $handler)
    {
        $this->callbacks[$name] = $handler;

        return $this;
    }

    /**
     * @param $configure
     *
     * @return $this
     */
    public function configure($configure)
    {
        $this->client->set($configure);

        return $this;
    }

    /**
     * @param swoole_client $client
     *
     * @return mixed
     */
    public function onConnect(swoole_client $client)
    {
    }

    /**
     * @param swoole_client $client
     * @param string        $data
     *
     * @return mixed
     */
    public function onReceive(swoole_client $client, $data)
    {
    }

    /**
     * @param swoole_client $client
     *
     * @return mixed
     */
    public function onError(swoole_client $client)
    {
    }

    /**
     * @param swoole_client $client
     *
     * @return mixed
     */
    public function onClose(swoole_client $client)
    {
    }

    /**
     * @return mixed
     */
    public function connect()
    {
        return $this->client->connect($this->host, $this->port, $this->timeout);
    }

    /**
     * @return bool
     */
    public function isConnected()
    {
        return $this->client->isConnected();
    }

    /**
     * @return mixed
     */
    public function receive()
    {
        return $this->client->recv();
    }

    /**
     * @return mixed
     */
    public function close()
    {
        return $this->client->close();
    }

    /**
     * @param string|array $data
     *
     * @return mixed
     * @throws \Uniondrug\Packet\Exceptions\PacketException
     */
    public function send($data = '')
    {
        if (!$this->isConnected()) {
            if (null === $this->client) {
                throw new LogicException('Please call the createRequest method first');
            }
            if (!$this->connect()) {
                throw new RuntimeException(socket_strerror($this->client->errCode));
            }
        }

        $this->client->send($this->wrapBody($data));

        if (!$this->async) {
            return $this->receive();
        }

        return true;
    }

    /**
     * start async client
     */
    public function start()
    {
        foreach ($this->callbacks as $event => $callback) {
            $this->client->on($event, $callback);
        }
        $this->connect();
    }

    public function __destruct()
    {
        if (
            null !== $this->client
            && $this->client instanceof swoole_client
            && $this->isConnected()
        ) {
            $this->close();
        }
    }
}