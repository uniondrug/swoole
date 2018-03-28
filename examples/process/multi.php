<?php
/**
 * @author    jan huang <bboyjanhuang@gmail.com>
 * @copyright 2016
 *
 * @link      https://www.github.com/janhuang
 * @link      http://www.fast-d.cn/
 */

use FastD\Swoole\Process;

include __DIR__ . '/../../vendor/autoload.php';

$process = new Process('multi', function (swoole_process $process) {
    timer_tick(1000, function ($id) use ($process) {
        static $index = 0;
        $index++;
        echo $index . PHP_EOL;
        if ($index === 2) {
            timer_clear($id);
        }
    });
});

$process->fork(5);

$process->wait(function ($ret) {
    echo 'PID: ' . $ret['pid'] . PHP_EOL;
});