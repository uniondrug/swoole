# 客户端 

首先得必须说明一下什么是客户端，所谓客户端，就是咱们所指的 "消费者"，服务器，也有可能是客户端。可以这么理解，只要是发起请求到另外一段获取获取的，即可视为客户端。因此有时候咱们的服务器，也是其中一个客户端。

### 同步客户端

同步客户端是最传统的一种方式，也是最容易上手的，整个过程都是阻塞的。

```php
use Uniondrug\Swoole\Client;


$client = new Client('tcp://127.0.0.1:9527');

$client->send();
```

### 异步客户端

不管是同步还是异步客户端，每个方法都是一个回调，统一客户端的写法，避免造成多种操作方式的，造成混淆。

值得注意的是，异步客户端需要对 `connect`, `receive`, `error`, `close` 进行重写，并且通过 start 进行启动客户端

```php
use Uniondrug\Swoole\Client;


$client = new Client('tcp://127.0.0.1:9527');

$client->start();
```

下一节: [进程](2-3-process.md)