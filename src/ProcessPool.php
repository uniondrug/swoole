<?php
/**
 * ProcessPool.php
 *
 */

namespace Uniondrug\Swoole;

use Swoole\Process\Pool;

class ProcessPool
{
    /**
     * @var \Swoole\Process\Pool
     */
    protected $processPool;

    protected $booted = false;

    protected $workerNum = 2;

    protected $ipcMode = 0;

    protected $backlog = 2048;

    protected $msgQueueKey;

    protected $socket = 'tcp://127.0.0.1:24680';

    public function __construct()
    {
        $this->configure();
    }

    public function configure()
    {

    }

    public function bootstrap()
    {
        $this->processPool = new Pool($this->workerNum, $this->ipcMode, $this->msgQueueKey);
        $this->processPool->on('WorkerStart', [$this, 'onWorkerStart']);
        $this->processPool->on('WorkerStop', [$this, 'onWorkerStop']);
        if ($this->ipcMode != 0) {
            $this->processPool->on('Message', [$this, 'onMessage']);
        }
        if ($this->ipcMode === SWOOLE_IPC_SOCKET) {
            $this->listen();
        }
        $this->booted = true;
    }

    public function listen()
    {
        $listen = parse_url($this->socket);
        if (isset($listen['scheme']) && $listen['scheme'] === 'unix') {
            $this->processPool->listen($this->socket);
        } else if (isset($listen['scheme']) && $listen['scheme'] === 'tcp') {
            if (!isset($listen['host'])) {
                throw new \RuntimeException('Invalid socket: ' . $this->socket);
            }
            if (!isset($listen['port'])) {
                $listen['port'] = '24680';
            }
            $this->processPool->listen($listen['host'], $listen['port'], $this->backlog);
        }
    }

    public function start()
    {
        if (!$this->booted) {
            $this->bootstrap();
        }

        $this->processPool->start();
    }

    public function onWorkerStart(Pool $pool, int $workerId)
    {

    }

    public function onWorkerStop(Pool $pool, int $workerId)
    {

    }

    public function onMessage(Pool $pool, string $data)
    {

    }
}
