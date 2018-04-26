<?php
/**
 * @author    jan huang <bboyjanhuang@gmail.com>
 * @copyright 2016
 *
 * @link      https://www.github.com/janhuang
 * @link      http://www.fast-d.cn/
 */

namespace Uniondrug\Swoole;

use Exception;
use swoole_server;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Uniondrug\Swoole\Support\Watcher;

/**
 * Class Server
 *
 * @package FastD\Swoole
 */
abstract class Server
{
    const VERSION = '2.1.0';

    /**
     * @var $name
     */
    protected $name;

    /**
     * @var OutputInterface
     */
    protected $output;

    /**
     * @var swoole_server
     */
    protected $swoole;

    /**
     * Swoole server run configuration.
     *
     * @var array
     */
    protected $config = [
        'worker_num'        => 8,
        'task_worker_num'   => 8,
        'task_tmpdir'       => '/tmp',
        'open_cpu_affinity' => true,
    ];

    const SCHEME = 'tcp';

    /**
     * @var string
     */
    protected $host = '127.0.0.1';

    /**
     * @var string
     */
    protected $port = '9527';

    /**
     * @var string
     */
    protected $pidFile;

    /**
     * @var int
     */
    protected $pid;

    /**
     * @var bool
     */
    protected $booted = false;

    /**
     * 多端口支持
     *
     * @var Server[]
     */
    protected $listens = [];

    /**
     * @var Process[]
     */
    protected $processes = [];

    /**
     * @var Timer[]
     */
    protected $timers = [];

    /**
     * @var int
     */
    protected $fd;

    /**
     * Server constructor.
     *
     * @param                 $name
     * @param null            $address
     * @param array           $config
     * @param OutputInterface $output
     */
    public function __construct($name, $address = null, array $config = [], OutputInterface $output = null)
    {
        $this->name = $name;

        if (null !== $address) {
            $info = parse_url($address);

            $this->host = $info['host'];
            $this->port = $info['port'];
        }

        $this->output = null === $output ? new ConsoleOutput() : $output;

        $this->configure($config);
    }

    /**
     * @param array $config
     *
     * @return $this
     */
    public function configure(array $config)
    {
        $this->config = array_merge($this->config, $config);

        if (isset($this->config['pid_file'])) {
            $this->pidFile = $this->config['pid_file'];
        }

        if (empty($this->pidFile)) {
            $this->pidFile = '/tmp/' . str_replace(' ', '-', $this->name) . '.pid';
            $this->config['pid_file'] = $this->pidFile;
        }

        return $this;
    }

    /**
     * @return bool
     */
    public function isBooted()
    {
        return $this->booted;
    }

    /**
     * 守護進程
     *
     * @return $this
     */
    public function daemon()
    {
        $this->config['daemonize'] = true;

        return $this;
    }

    /**
     * @return string
     */
    public function getScheme()
    {
        return static::SCHEME;
    }

    /**
     * @return string
     */
    public function getHost()
    {
        return $this->host;
    }

    /**
     * @return string
     */
    public function getPort()
    {
        return $this->port;
    }

    /**
     * Get client connection server's file descriptor.
     *
     * @return int
     */
    public function getFileDescriptor()
    {
        return $this->fd;
    }

    /**
     * @return string
     */
    public function getSocketType()
    {
        switch (static::SCHEME) {
            case 'udp':
                $type = SWOOLE_SOCK_UDP;
                break;
            case 'unix':
                $type = SWOOLE_UNIX_STREAM;
                break;
            case 'tcp':
            default :
                $type = SWOOLE_SOCK_TCP;
        }

        return $type;
    }

    /**
     * @return string
     */
    public function getPidFile()
    {
        return $this->pidFile;
    }

    /**
     * @return int
     */
    public function getPid()
    {
        return $this->pid;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return swoole_server
     */
    public function getSwoole()
    {
        return $this->swoole;
    }

    /**
     * @return $this
     */
    protected function handleCallback()
    {
        $handles = get_class_methods($this);
        $isListenerPort = false;
        $serverClass = get_class($this->getSwoole());
        if ('Swoole\Server\Port' == $serverClass || 'swoole_server_port' == $serverClass) {
            $isListenerPort = true;
        }
        foreach ($handles as $value) {
            if ('on' == substr($value, 0, 2)) {
                if ($isListenerPort) {
                    if ('udp' === $this->getScheme()) {
                        $callbacks = ['onPacket',];
                    } else {
                        $callbacks = ['onConnect', 'onClose', 'onReceive',];
                    }
                    if (in_array($value, $callbacks)) {
                        $this->swoole->on(lcfirst(substr($value, 2)), [$this, $value]);
                    }
                } else {
                    $this->swoole->on(lcfirst(substr($value, 2)), [$this, $value]);
                }
            }
        }

        return $this;
    }

    /**
     * 引导服务，当启动是接收到 swoole server 信息，则默认以这个swoole 服务进行引导
     *
     * @param $swoole swoole server or swoole server port
     *
     * @return $this
     */
    public function bootstrap($swoole = null)
    {
        if (!$this->isBooted()) {
            $this->swoole = null === $swoole ? $this->initSwoole() : $swoole;

            $this->swoole->set($this->config);

            $this->handleCallback();

            $this->booted = true;
        }

        return $this;
    }

    /**
     * 如果需要自定义自己的swoole服务器,重写此方法
     *
     * @return swoole_server
     */
    public function initSwoole()
    {
        return new swoole_server($this->host, $this->port, SWOOLE_PROCESS, $this->getSocketType());
    }

    /**
     * @param Server $server
     *
     * @return $this
     */
    public function listen(Server $server)
    {
        $this->listens[] = $server;

        return $this;
    }

    /**
     * @param Process $process
     *
     * @return $this
     */
    public function process(Process $process)
    {
        $process->withServer($this);

        $this->processes[] = $process;

        return $this;
    }

    /**
     * @param Timer $timer
     *
     * @return $this
     */
    public function timer(Timer $timer)
    {
        $timer->withServer($this);

        $this->timers[] = $timer;

        return $this;
    }

    /**
     * @param                                                        $name
     * @param                                                        $address
     * @param array                                                  $config
     * @param \Symfony\Component\Console\Output\OutputInterface|null $output
     *
     * @return static
     */
    public static function createServer($name, $address, array $config = [], OutputInterface $output = null)
    {
        return new static($name, $address, $config, $output);
    }

    /**
     * Write message to consle, with datetime & pid info
     *
     * @param     $messages
     * @param int $options
     */
    public function console($messages, $options = ConsoleOutput::VERBOSITY_NORMAL)
    {
        $time = date("Y-m-d H:i:s");

        $wid = 0;
        $processFlag = isset($this->swoole->master_pid) ? '@' : '#';
        $pid = getmypid();
        if (isset($this->swoole->worker_id) && $this->swoole->worker_id >= 0) {
            $wid = $this->swoole->worker_id;
            if ($this->swoole->taskworker) {
                $processFlag = '^'; // taskworker
            } else {
                $processFlag = '*'; // worker
            }
        }
        if (isset($this->swoole->manager_pid) && $this->swoole->manager_pid == $pid) {
            $processFlag = '$'; // manager
        }
        if (isset($this->swoole->master_pid) && $this->swoole->master_pid == $pid) {
            $processFlag = '#'; // master
        }

        $messages = sprintf("[%s %s%d.%d]<info>\tINFO\t</info>%s", $time, $processFlag, $pid, $wid, $messages);

        $this->output->writeln($messages, $options);
    }

    /**
     * @return int
     */
    public function start()
    {
        if ($this->isRunning()) {
            $this->console(sprintf('Server <info>[%s] %s:%s</info> address already in use', $this->name, $this->host, $this->port));
        } else {
            try {
                $this->bootstrap();
                if (!file_exists($dir = dirname($this->pidFile))) {
                    mkdir($dir, 0755, true);
                }
                // 多端口监听
                foreach ($this->listens as $listen) {
                    $swoole = $this->swoole->listen($listen->getHost(), $listen->getPort(), $listen->getSocketType());
                    $listen->bootstrap($swoole);
                }
                // 进程控制
                foreach ($this->processes as $process) {
                    $this->swoole->addProcess($process->getProcess());
                }

                $this->console(sprintf("Server: <info>%s</info>", $this->name));
                $this->console(sprintf('App version: <info>%s</info>', Server::VERSION));
                $this->console(sprintf('Swoole version: <info>%s</info>', SWOOLE_VERSION));

                $this->swoole->start();
            } catch (Exception $e) {
                $this->output->error("<error>{$e->getMessage()}</error>\n");
            }
        }

        return 0;
    }

    /**
     * @return int
     */
    public function shutdown()
    {
        if (!$this->isRunning()) {
            $this->output->error(sprintf('Server <info>%s</info> is not running...', $this->name));

            return -1;
        }

        $pid = (int) @file_get_contents($this->getPidFile());
        if (process_kill($pid, SIGTERM)) {
            if (file_exists($this->pidFile)) {
                @unlink($this->pidFile);
            }
        }

        $this->console(sprintf('Server <info>%s</info> [<info>#%s</info>] is shutdown...', $this->name, $pid));
        $this->console(sprintf('PID file %s is unlink', $this->pidFile));

        return 0;
    }

    /**
     * @return int
     */
    public function reload()
    {
        if (!$this->isRunning()) {
            $this->console(sprintf('Server <info>%s</info> is not running...', $this->name));

            return -1;
        }

        $pid = (int) @file_get_contents($this->getPidFile());

        posix_kill($pid, SIGUSR1);

        $this->console(sprintf('Server <info>%s</info> [<info>%s</info>] is reloading...', $this->name, $pid));

        return 0;
    }

    /**
     * @return int
     */
    public function restart()
    {
        $this->shutdown();

        return $this->start();
    }

    /**
     * @return int
     */
    public function status()
    {
        if (!$this->isRunning()) {
            $this->console(sprintf('Server <info>%s</info> is not running...', $this->name));

            return -1;
        }

        exec("ps axu | grep '{$this->name}' | grep -v grep", $output);

        // list all process
        $output = array_map(function ($v) {
            $status = preg_split('/\s+/', $v);
            unset($status[2], $status[3], $status[4], $status[6], $status[9]); //
            $status = array_values($status);
            $status[5] = $status[5] . ' ' . implode(' ', array_slice($status, 6));

            return array_slice($status, 0, 6);
        }, $output);

        // combine
        $headers = ['USER', 'PID', 'RSS', 'STAT', 'START', 'COMMAND'];
        foreach ($output as $key => $value) {
            $output[$key] = array_combine($headers, $value);
        }

        $table = new Table($this->output);
        $table
            ->setHeaders($headers)
            ->setRows($output);

        $this->console(sprintf("Server: <info>%s</info>", $this->name));
        $this->console(sprintf('App version: <info>%s</info>', Server::VERSION));
        $this->console(sprintf('Swoole version: <info>%s</info>', SWOOLE_VERSION));
        $this->console(sprintf("PID file: <info>%s</info>, PID: <info>%s</info>", $this->pidFile, (int) @file_get_contents($this->pidFile)) . PHP_EOL);
        $table->render();

        unset($table, $headers, $output);

        return 0;
    }

    /**
     * @param array $directories
     *
     * @return void|int
     */
    public function watch(array $directories = ['.'])
    {
        $that = $this;

        if (!$this->isRunning()) {
            $process = new Process('server watch process', function () use ($that) {
                $that->start();
            }, true);
            $process->start();
        }

        foreach ($directories as $directory) {
            $this->console(sprintf('Watching directory: ["<info>%s</info>"]', realpath($directory)));
        }

        $watcher = new Watcher($this->output);

        $watcher->watch($directories, function () use ($that) {
            $that->reload();
        });

        $watcher->run();

        process_wait();
    }

    /**
     * @return bool
     */
    public function isRunning()
    {
        if (file_exists($this->pidFile)) {
            return process_kill((int) file_get_contents($this->pidFile), 0);
        }

        return process_is_running("{$this->name} master");
    }

    /**
     * Base start handle. Storage process id.
     *
     * @param swoole_server $server
     *
     * @return void
     */
    public function onStart(swoole_server $server)
    {
        if (version_compare(SWOOLE_VERSION, '1.9.5', '<')) {
            file_put_contents($this->pidFile, $server->master_pid);
            $this->pid = $server->master_pid;
        }

        process_rename($this->name . ' master');

        $this->console(sprintf("Listen: <info>%s://%s:%s</info>", $this->getScheme(), $this->getHost(), $this->getPort()));
        foreach ($this->listens as $listen) {
            $this->console(sprintf(" <info> ></info> Listen: <info>%s://%s:%s</info>", $listen->getScheme(), $listen->getHost(), $listen->getPort()));
        }

        $this->console(sprintf('PID file: <info>%s</info>, PID: <info>%s</info>', $this->pidFile, $server->master_pid));
        $this->console(sprintf('Server Master[<info>%s</info>] is started', $server->master_pid), OutputInterface::VERBOSITY_DEBUG);
    }

    /**
     * Shutdown server process.
     *
     * @param swoole_server $server
     *
     * @return void
     */
    public function onShutdown(swoole_server $server)
    {
        if (file_exists($this->pidFile)) {
            unlink($this->pidFile);
        }

        $this->console(sprintf('Server <info>%s</info> Master[<info>%s</info>] is shutdown ', $this->name, $server->master_pid), OutputInterface::VERBOSITY_DEBUG);
    }

    /**
     * @param swoole_server $server
     *
     * @return void
     */
    public function onManagerStart(swoole_server $server)
    {
        process_rename($this->getName() . ' manager');

        $this->console(sprintf('Server Manager[<info>%s</info>] is started', $server->manager_pid), OutputInterface::VERBOSITY_DEBUG);
    }

    /**
     * @param swoole_server $server
     *
     * @return void
     */
    public function onManagerStop(swoole_server $server)
    {
        $this->console(sprintf('Server <info>%s</info> Manager[<info>%s</info>] is shutdown.', $this->name, $server->manager_pid), OutputInterface::VERBOSITY_DEBUG);
    }

    /**
     * @param swoole_server $server
     * @param int           $worker_id
     *
     * @return void
     */
    public function onWorkerStart(swoole_server $server, $worker_id)
    {
        process_rename($this->getName() . ' worker');

        $this->console(sprintf('Server Worker[<info>%s</info>] is started [<info>%s</info>]', $server->worker_pid, $worker_id), OutputInterface::VERBOSITY_DEBUG);
    }

    /**
     * @param swoole_server $server
     * @param int           $worker_id
     *
     * @return void
     */
    public function onWorkerStop(swoole_server $server, $worker_id)
    {
        $this->console(sprintf('Server <info>%s</info> Worker[<info>%s</info>] is shutdown', $this->name, $worker_id), OutputInterface::VERBOSITY_DEBUG);
    }

    /**
     * @param swoole_server $server
     * @param               $workerId
     * @param               $workerPid
     * @param               $code
     */
    public function onWorkerError(swoole_server $server, $workerId, $workerPid, $code)
    {
        $this->console(sprintf('Server <info>%s:%s</info> Worker[<info>%s</info>] error. Exit code: [<question>%s</question>]', $this->name, $workerPid, $workerId, $code), OutputInterface::VERBOSITY_DEBUG);
    }

    /**
     * @param swoole_server $server
     * @param               $taskId
     * @param               $workerId
     * @param               $data
     *
     * @return mixed
     */
    public function onTask(swoole_server $server, $taskId, $workerId, $data)
    {
        return $this->doTask($server, $data, $taskId, $workerId);
    }

    /**
     * @param swoole_server $server
     * @param               $data
     * @param               $taskId
     * @param               $workerId
     *
     * @return mixed
     */
    abstract public function doTask(swoole_server $server, $data, $taskId, $workerId);

    /**
     * @param swoole_server $server
     * @param               $taskId
     * @param               $data
     *
     * @return mixed
     */
    public function onFinish(swoole_server $server, $taskId, $data)
    {
        return $this->doFinish($server, $data, $taskId);
    }

    /**
     * @param swoole_server $server
     * @param               $data
     * @param               $taskId
     *
     * @return mixed
     */
    abstract public function doFinish(swoole_server $server, $data, $taskId);

    /**
     * @param \swoole_server $server
     * @param int            $src_worker_id
     * @param mixed          $message
     *
     * @return mixed
     */
    public function onPipeMessage(swoole_server $server, int $src_worker_id, $message)
    {
        return $this->doPipeMessage($server, $src_worker_id, $message);
    }

    /**
     * @param \swoole_server $server
     * @param int            $src_worker_id
     * @param mixed          $message
     *
     * @return mixed
     */
    abstract public function doPipeMessage(swoole_server $server, int $src_worker_id, $message);

    /**
     * @param swoole_server $server
     * @param               $fd
     * @param               $from_id
     */
    public function onConnect(swoole_server $server, $fd, $from_id)
    {
        $this->fd = $fd;

        $this->doConnect($server, $fd, $from_id);
    }

    /**
     * @param swoole_server $server
     * @param               $fd
     * @param               $from_id
     */
    abstract public function doConnect(swoole_server $server, $fd, $from_id);

    /**
     * @param swoole_server $server
     * @param               $fd
     * @param               $fromId
     */
    public function onClose(swoole_server $server, $fd, $fromId)
    {
        $this->doClose($server, $fd, $fromId);
    }

    /**
     * @param swoole_server $server
     * @param               $fd
     * @param               $fromId
     */
    abstract public function doClose(swoole_server $server, $fd, $fromId);
}