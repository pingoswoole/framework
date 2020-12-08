<?php
namespace Pingo\Swoole\Server;

use Pingo\Traits\Singleton;
use Pingo\Component\ConsoleTools;
use Pingo\Swoole\SwooleEvent;

class Http extends SwooleEvent
{
    protected $server;
    protected $setting;
    protected $swoole_set = [];
    const CALLBACK_EVENT = [
        'Start', 
        'Shutdown', 
        'WorkerStart',
        'WorkerStop',
        'WorkerExit',
        'Connect',
        //'Receive',
        //'Packet',
        'Task',
        'Close',
        'Finish',
        'PipeMessage',
        'WorkerError',
        'ManagerStart',
        'ManagerStop',
        'BeforeReload',
        'AfterReload',
        'Request',
        'Message',
        'Open',
        'HandShake',
         
    ];
    use Singleton;

    public function __construct(array $setting = [])
    {
        $this->setting = $setting;
        $this->swoole_set = $this->setting['protocol'][\Pingo\Swoole\Constant::SWOOLE_UDP_SERVER];
    }

    protected function registerEvent(array $events)
    {
        //注册主服务事件回调
        foreach ($events as  $event){
            $this->server->on($event, [$this, "on{$event}"]);
        }
    }

    /**
     * 创建服务
     *
     * @author pingo
     * @created_at 00-00-00
     * @return void
     */
    public function create()
    {
        ConsoleTools::opCacheClear();
        $server_set = $this->swoole_set;
        $this->server = new \Swoole\Server(
            $this->swoole_set['host'], 
            $this->swoole_set['port'], 
            $this->swoole_set['mode'], 
            $this->swoole_set['sock_type']);
        $this->server->set($server_set['setting']);
        $this->registerEvent(self::CALLBACK_EVENT);
        \App\SwooleEvent::globalService($this->server);

    }

    
    /**
     * onStart
     *
     * @author pingo
     * @created_at 00-00-00
     * @param \Swoole\Server $server
     * @return void
     */
    public function onStart(\Swoole\Server $server)
    {
        file_put_contents( $this->setting['master_pid_file'], $server->master_pid);
          
        file_put_contents( $this->setting['manager_pid_file'], $server->manager_pid);
           
        set_process_name($this->setting['master_process_name']);
        
    }

    public function onShutdown(\Swoole\Server $server)
    {
        /* 已关闭所有 Reactor 线程、HeartbeatCheck 线程、UdpRecv 线程
        已关闭所有 Worker 进程、 Task 进程、User 进程
        已 close 所有 TCP/UDP/UnixSocket 监听端口
        已关闭主 Reactor
        强制 kill 进程不会回调 onShutdown，如 kill -9
        需要使用 kill -15 来发送 SIGTREM 信号到主进程才能按照正常的流程终止
        在命令行中使用 Ctrl+C 中断程序会立即停止，底层不会回调 onShutdown */
    }

    public function onWorkerStart(\Swoole\Server $server, int $workerId)
    {
        if($workerId > $this->swoole_set['setting']['worker_num']){
            set_process_name($this->setting['task_process_name'] . $workerId);
        }else{
            set_process_name($this->setting['worker_process_name'] . $workerId);
        }

    }

    public function onWorkerStop(\Swoole\Server $server, int $workerId)
    {

    }

    public function onWorkerExit(\Swoole\Server $server, int $workerId)
    {

    }

    public function onConnect(\Swoole\Server $server, int $fd, int $reactorId)
    {

    }

    public function onReceive(\Swoole\Server $server, int $fd, int $reactorId, string $data)
    {

    }

    public function onPacket(\Swoole\Server $server, string $data, array $clientInfo)
    {

    }

    public function onTask(\Swoole\Server $server, int $task_id, int $src_worker_id,  $data)
    {

    }

    public function onClose(\Swoole\Server $server, int $fd, int $reactorId)
    {

    }

    public function onFinish(\Swoole\Server $server, int $task_id,  $data)
    {

    }

    public function onPipeMessage(\Swoole\Server $server, int $src_worker_id,  $message)
    {

    }

    public function onWorkerError(\Swoole\Server $server, int $worker_id, int $worker_pid, int $exit_code, int $signal)
    {

    }

    public function onManagerStart(\Swoole\Server $server)
    {
        set_process_name($this->setting['manager_process_name']);
    }
    
    public function onManagerStop(\Swoole\Server $server)
    {

    }
    //Worker 进程 Reload 之前触发此事件，在 Manager 进程中回调
    public function onBeforeReload(\Swoole\Server $server)
    {

    }
    //Worker 进程 Reload 之后触发此事件，在 Manager 进程中回调

    public function onAfterReload(\Swoole\Server $server)
    {

    }

    public function onRequest(\Swoole\Http\Request $request, \Swoole\Http\Response $response)
    {

    }

    public function onMessage(\Swoole\WebSocket\Server $server, \Swoole\WebSocket\Frame $frame)
    {

    }

    public function onOpen(\Swoole\WebSocket\Server $server, \Swoole\Http\Request $request)
    {

    }

    public function onHandShake(\Swoole\Http\Request $request, \Swoole\Http\Response $response)
    {

    }
      

}