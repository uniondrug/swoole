# 进程

什么是进程，那什么是线程，而协程又是什么?

* 进程是资源分配的最小单位，线程是程序执行的最小单位。
* 进程有自己的独立地址空间，每启动一个进程，系统就会为它分配地址空间，建立数据表来维护代码段、堆栈段和数据段，这种操作非常昂贵。而线程是共享进程中的数据的，使用相同的地址空间，因此CPU切换一个线程的花费远比进程要小很多，同时创建一个线程的开销也比进程要小很多。
* 线程之间的通信更方便，同一进程下的线程共享全局变量、静态变量等数据，而进程之间的通信需要以通信的方式（IPC)进行。不过如何处理好同步与互斥是编写多线程程序的难点。
* 但是多进程程序更健壮，多线程程序只要有一个线程死掉，整个进程也死掉了，而一个进程死掉并不会对另外一个进程造成影响，因为进程有自己独立的地址空间。

简单理解吧，进程是独立的，而线程则是基于进程上再分配出来的，因此线程会直接影响到进程，而进程之间则不会有太多的依赖和影响。

### 单进程

进程会在执行完回调之后，退出自己的执行逻辑。而咱们平时日常开发当中的常驻内存的进程，正是因为内部是一个无限循环的监听、和逻辑处理，才正是他们需要常驻的原因之一。

如简单的: 

```php
while(true) {
    // logic to do 
}
```

以上的代码执行后，如果不通过手动的关闭，是不会退出进程。咱么是需要在内部做好管理操作，对进程进行一定的控制，这就是咱们下节讲到的: [信号量](3-1-signo.md)

```php
use Uniondrug\Swoole\Process;

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
```

### 多进程

多进程与单进程的区别就是由 `start` 方法改变成 `fork` 方法即可，而多进程就会衍生出一个进程间通信的问题，进程间是否需要通信，交互，如何通信，都是咱们需要去考虑的，后面小弟研究透彻再与大家分享。

```php
use Uniondrug\Swoole\Process;


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

下一节: [信号量](3-1-signo.md)