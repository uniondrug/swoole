# Uniondrug Swoole (based on FastD Swoole)

> THANKS：本模块来自 [FastD](https://github.com/fastdlabs/swoole)

高性能网络服务组件. 提供底层服务封装, 基础管理及客户端调用功能. 使用 `composer` 进行管理, 可在此基础上进行封装整合.

## 环境要求

* Linux (不考虑windows)
* PHP >= 5.6
* swoole >= 1.9.6

源码地址: [swoole](https://github.com/swoole/swoole-src)

pecl 安装

```shell
pecl install swoole
```

### 可选扩展

**如果 PHP >= 7.0 的安装 2.0 版本.**

源码地址: [inotify](http://pecl.php.net/package/inotify)

pecl 安装

```shell
pecl install inotify
```

### 安装

```
composer require uniondrug/swoole
```

## 文档

[中文文档](docs/zh_CN/readme.md)

## 使用

服务继承 `Uniondrug\Swoole\Server`, 实现 `doWork` 方法, 服务器在接收信息 `onReceive` 回调中会调用 `doWork` 方法, `doWork` 方法接受一个封装好的请求对象。

具体逻辑在 `doWork` 方法中实现, `doWork` 方法中返回响应客户端的数据, 格式为: **字符串**

Swoole 配置通过实现 `configure` 方法进行配置，具体配置参数请参考: [Swoole 配置选项](http://wiki.swoole.com/wiki/page/274.html)

#### TCP Server

```php
class DemoServer extends \Uniondrug\Swoole\Server\Tcp
{
    public function doWork(swoole_server $server, $fd, $data, $from_id)
    {
        echo $data . PHP_EOL;
        return 'hello tcp';
    }
}

DemoServer::createServer('tcp swoole', 'tcp://0.0.0.0:9527')->start();
```

#### UDP Server

```php
class DemoServer extends \Uniondrug\Swoole\Server\Udp
{
    public function doPacket(swoole_server $server, $data, $client_info)
    {
        echo $data . PHP_EOL;
        return 'hello tcp';
    }
}

DemoServer::createServer('udp swoole', 'udp://127.0.0.1:9527')->start;
```

#### HTTP Server

同理, `Http` 服务器扩展 `Server` 类, 实现 `doRequest` 方法,实现具体逻辑。

```php
class Http extends \Uniondrug\Swoole\Server\Http
{
    public function doRequest(ServerRequest $serverRequest)
    {
        return new JsonResponse([
            'msg' => 'hello world',
        ]);
    }
}

Http::createServer('http', 'http://0.0.0.0:9527')->start();
```

#### WebSocket Server

```php
class WebSocket extends \Uniondrug\Swoole\Server\WebSocket
{
    public function doOpen(swoole_websocket_server $server, swoole_http_request $request)
    {
        echo "server: handshake success with fd{$request->fd}\n";
    }

    public function doMessage(swoole_server $server, swoole_websocket_frame $frame)
    {
        echo "receive from {$frame->fd}:{$frame->data},opcode:{$frame->opcode},fin:{$frame->finish}\n";
        $server->push($frame->fd, "this is server");
    }
}

WebSocket::createServer('ws', 'ws://0.0.0.0:9527')->start();
```

#### 多端口支持

```php
class Server extends \Uniondrug\Swoole\Server\Tcp
{
    public function doWork(swoole_server $server, $fd, $data, $from_id)
    {
        return 'hello server1';
    }
}

class Server2 extends \Uniondrug\Swoole\Server\Tcp
{
    public function doWork(swoole_server $server, $fd, $data, $from_id)
    {
        return 'hello server2';
    }
}

$server = new Server('tcp server', 'tcp://127.0.0.1:9527');

$server->listen(new Server2('tcp server2', 'tcp://127.0.0.1:9528'));

$server->start();
```

#### 服务管理

```php
class DemoServer extends \Uniondrug\Swoole\Server\Tcp
{
    public function doWork(swoole_server $server, $fd, $data, $from_id)
    {
        echo $data . PHP_EOL;
        return 'hello tcp';
    }
}

$server = DemoServer::createServer('tcp swoole', 'tcp://0.0.0.0:9527');

$argv = $_SERVER['argv'];

$argv[1] = isset($argv[1]) ? $argv[1] : 'status';

switch ($argv[1]) {
    case 'start':
        $server->start();
        break;
    case 'stop':
        $server->shutdown();
        break;
    case 'reload':
        $server->reload();
        break;
    case 'status':
    default:
        $server->status();
}
```

#### File Listener

因为 swoole 是常驻内存，程序通过首次启动就自动预载到内存当中，所以每次修改都需要重启服务。

所以这里提供监听文件变化来到自动重启服务(建议开发环境中使用)

```php

class DemoServer extends \FastD\Swoole\Server\Tcp
{
    public function doWork(swoole_server $server, $fd, $data, $from_id)
    {
        return 'hello tcp';
    }
}

$server = new DemoServer('watch server', 'tcp://0.0.0.0:9527');
// $server = DemoServer::createServer('watch server', 'tcp://0.0.0.0:9527');
$server->watch([__DIR__ . '/listen_files']);
```

#### Sync Client

Client 通过 resolve 执行，通过不同的方法设置不同的回调，同步、异步均使用通用的方法。

```php
$client = new \Uniondrug\Swoole\Client\Sync\SyncClient('tcp://11.11.11.11:9527');

$client
    ->connect(function ($client) {
        $client->send('hello world');
    })
    ->receive(function ($client, $data) {
        echo $data . PHP_EOL;
        $client->close();
    })
    ->resolve()
;
```

#### Async Client

```php
$client = new \Uniondrug\Swoole\Client\Async\AsyncClient('tcp://11.11.11.11:9527');

$client
    ->connect(function ($client) {
        $client->send('hello world');
    })
    ->receive(function ($client, $data) {
        echo $data . PHP_EOL;
    })
    ->error(function ($client) {
        print_r($client);
    })
    ->close(function ($client) {})
    ->resolve()
;
```

#### Process

```php
$process = new Process('single', function () {
    timer_tick(1000, function ($id) {
        static $index = 0;
        $index++;
        echo $index . PHP_EOL;
        if ($index === 10) {
            timer_clear($id);
        }
    });
});

$process->start();

$process->wait(function ($ret) {
    echo 'PID: ' . $ret['pid'];
});
```

#### Multi Process

```php
$process = new Process('multi', function () {
    timer_tick(1000, function ($id) {
        static $index = 0;
        $index++;
        echo $index . PHP_EOL;
        if ($index === 10) {
            timer_clear($id);
        }
    });
});

$process->fork(5);

$process->wait(function ($ret) {
    echo 'PID: ' . $ret['pid'] . PHP_EOL;
});
```

#### Queue

```php
$queue = new \Uniondrug\Swoole\Queue('queue', function ($worker) {
    while (true) {
        $recv = $worker->pop();
        echo "From Master: $recv\n";
    }
});

$queue->start();

while (true) {
    $queue->push('hello');
    sleep(1);
}


$queue->wait(function ($ret) {
    echo 'PID: ' . $ret['pid'];
});
```

## License MIT
