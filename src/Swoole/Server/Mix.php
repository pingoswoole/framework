<?php
namespace Pingo\Swoole\Server;

use Pingo\Traits\Singleton;
use Pingo\Component\ConsoleTools;
use Pingo\Component\Di;
use Pingo\Swoole\SwooleEvent;
use Pingo\Swoole\Context;
use Pingo\Config\Config;
use Pingo\Http\Request;
use Pingo\Http\Response;
use Pingo\Pool\ConnectionPool;
use Pingo\Pool\Connectors\PDOConnector;
use Pingo\Pool\PoolManager;
use Pingo\Swoole\Constant;

class Mix extends SwooleEvent
{
    protected $server;
    protected $childServer = [];
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
        $this->swoole_set = $this->setting['protocol'][\Pingo\Swoole\Constant::SWOOLE_WEBSOCKET_SERVER];
    }

    protected function registerEvent(array $events)
    {
        //注册主服务事件回调
        foreach ($events as  $event){
            
            if($event === "Request"){

                $route = \Pingo\Http\Route::getInstance();
                $requestHook = Di::getInstance()->get(Constant::HTTP_GLOBAL_ON_REQUEST);
                $afterRequestHook = Di::getInstance()->get(Constant::HTTP_GLOBAL_AFTER_REQUEST);
                 
                $this->server->on($event, function($request, $response) use($route, $requestHook, $afterRequestHook){
                    Context::set('Request', $request);
                    Context::set('Response', $response);
                    //var_dump($route);
                    $request_psr = new Request($request);
                    $response_psr = new Response($response);
                    try {
                        $ret = null;
                        if (is_callable($requestHook)) {
                            $ret = call_user_func($requestHook, $request_psr, $response_psr);
                        }
                        if ($ret !== false) {
                            $route->dispatch($request_psr, $response_psr);
                        }
                    } catch (\Throwable $throwable) {
                        call_user_func(Di::getInstance()->get(Constant::HTTP_EXCEPTION_HANDLER), $throwable, $request_psr, $response_psr);
                    } finally {
                        try {
                            if (is_callable($afterRequestHook)) {
                                call_user_func($afterRequestHook, $request_psr, $response_psr);
                            }
                        } catch (\Throwable $throwable) {
                            call_user_func(Di::getInstance()->get(Constant::HTTP_EXCEPTION_HANDLER), $throwable, $request_psr, $response_psr);
                        }
                    }
                    $response_psr->__response();
                    
                });

            }else{
                $this->server->on($event, [$this, "on{$event}"]);
            }
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
        $this->server = new \Swoole\Http\Server(
            $this->swoole_set['host'], 
            $this->swoole_set['port'], 
            $this->swoole_set['mode'], 
            $this->swoole_set['sock_type']);
        $this->server->set($this->swoole_set['setting']);
        $this->registerEvent(self::CALLBACK_EVENT);
        
        $this->addServer();

        return [$this->server, $this->childServer];

    }

    private function addServer()
    {
        $tcp_set = $this->setting['protocol'][\Pingo\Swoole\Constant::SWOOLE_TCP_SERVER];
        $udp_set = $this->setting['protocol'][\Pingo\Swoole\Constant::SWOOLE_UDP_SERVER];
        $mqtt_set = $this->setting['protocol'][\Pingo\Swoole\Constant::SWOOLE_MQTT_SERVER];
        //TCP
        if($tcp_set['enable']){
            $tcpServer = $this->server->addListener( $tcp_set['host'], $tcp_set['port'], SWOOLE_SOCK_TCP);
            $tcpServer->set($tcp_set);
            foreach($tcp_set['callbacks'] as $event => $callback)
            {
                $tcpServer->on($event, function(...$args) use($callback){
                    call_user_func($callback, ...$args);
                });
            }
            $this->childServer['tcp'] = $tcpServer;
        }
        //UDP
        if($udp_set['enable']){
            $udpServer = $this->server->addListener( $udp_set['host'], $udp_set['port'], SWOOLE_SOCK_UDP);
            $udpServer->set($udp_set);
            foreach($udp_set['callbacks'] as $event => $callback)
            {
                $udpServer->on($event, function(...$args) use($callback){
                    call_user_func($callback, ...$args);
                });
            }
            $this->childServer['udp'] = $udpServer;
            
        }
        //MQTT
        if($mqtt_set['enable']){
            $mqttServer = $this->server->addListener( $mqtt_set['host'], $mqtt_set['port'], SWOOLE_SOCK_TCP);
            $mqttServer->set($mqtt_set);
            foreach($mqtt_set['callbacks'] as $event => $callback)
            {
                $mqttServer->on($event, function(...$args) use($callback){
                    call_user_func($callback, ...$args);
                });
            }
            $this->childServer['mqtt'] = $mqttServer;
            
        }
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
        
        PoolManager::getInstance();
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
        /* //进程池设置
        $database_setting = Config::getInstance()->get("database");
        \Pingo\Database\PDOPool::getInstance($database_setting);
        */
        $redis_setting = Config::getInstance()->get("redis");
        \Pingo\Database\RedisPool::getInstance($redis_setting); 
        //进程池设置
        $database_setting = Config::getInstance()->get("database");
        \Pingo\Database\PDOPool::getInstance($database_setting);
        \Pingo\Database\RedisPool::getInstance($redis_setting);
        //注冊第二個連接池
        $pools = PoolManager::getInstance();
        $pool1 = new ConnectionPool(
            [
                'minActive' => 2,
                'maxActive' => 30,
                'maxWaitTime' => 10,
            ],
            new PDOConnector,
            [
                'dsn'        => "mysql:host={$database_setting['host']};dbname={$database_setting['database']}",
                'port'        => $database_setting['port'],
                'username'        => $database_setting['username'],
                'password'    => $database_setting['password'],
                'database'    => $database_setting['database'],
                'timeout'     => 10,
                'charset'     => 'utf8mb4',
                'strict_type' => true,
                'fetch_mode'  => true,
            ]);
        $pool1->init();
        $pools->addConnectionPool('mysql', $pool1);

    }

    public function onWorkerStop(\Swoole\Server $server, int $workerId)
    {
        PoolManager::getInstance()->closeConnectionPools();
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
       /*  Context::set('Request', $request);
        Context::set('Response', $response);
        $this->_route->dispatch($request, $response); */
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