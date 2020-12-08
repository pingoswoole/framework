<?php
namespace Pingo\Swoole;

use Pingo\Component\ConsoleTools;
use Pingo\Traits\Singleton;
use Pingo\Contracts\Swoole\Factory;
use Pingo\Config\Config;
/**
 * swoole 管理
 *
 * @author pingo
 * @created_at 00-00-00
 */
class Manager implements Factory
{
    use Singleton;
    private $swooleServer;
    private $childServer;
    private $isStart = false;
    private static $pidFile;
    private $setting = [];

    public function __construct()
    {
        
    }

    public function setSetting(array $setting)
    {
        $this->setting = array_merge($this->setting, $setting);
    }

    public function getSetting()
    {
        return $this->setting;
    }

    public function getSwooleServer(string $serverName = null)
    {
        if(is_null($serverName)){
            return $this->swooleServer;
        }
        return $this->childServer[$serverName]?? null;
    }

    public function createSwooleServer(...$args):bool
    {
        if(empty($this->setting)) $this->setting = Config::getInstance()->get('servers');
        switch ($this->setting['server_type']) {
            case Constant::SWOOLE_HTTP_SERVER:
                $classServerName =\Pingo\Swoole\Server\Http::class;
                break;
            case Constant::SWOOLE_WEBSOCKET_SERVER:
                $classServerName = \Pingo\Swoole\Server\WebSocket::class;
                break;
            case Constant::SWOOLE_MQTT_SERVER:
                $classServerName = \Pingo\Swoole\Server\Mqtt::class;
                break;
            case Constant::SWOOLE_TCP_SERVER:
                $classServerName = \Pingo\Swoole\Server\Tcp::class;
                break;
            case Constant::SWOOLE_UDP_SERVER:
                $classServerName = \Pingo\Swoole\Server\Udp::class;
                break;
            default:
                // mix server
                $classServerName = \Pingo\Swoole\Server\Mix::class;
        }
        list($this->swooleServer, $this->childServer) = $classServerName::getInstance($this->setting)->create();
        \App\SwooleEvent::globalService($this->swooleServer);
        return true;
    }

    public function addServer(string $serverName, int $port, int $type, string $address, array $setting, array $event_register): bool
    {
        return true;
    }

    public function registerEvent($server, array $event_register)
    {

    }

    public function start()
    {
        $this->isStart = true;
        $this->swooleServer->start();
    }

    public function stop()
    {
        $pidFile = Config::getInstance()->get("servers.master_pid_file");
        if(file_exists($pidFile)){
            $pid = intval(file_get_contents($pidFile));
            \Swoole\Process::kill($pid, SIGKILL);
            unlink($pidFile);
            ConsoleTools::echoSuccess("stop commond is excute success ");
        }else{
            ConsoleTools::echoSuccess("pid file is not exists!");
        }
    }

    public function reload()
    {
        $pidFile = Config::getInstance()->get("servers.master_pid_file");
        if(file_exists($pidFile)){
            $pid = intval(file_get_contents($pidFile));
            \Swoole\Process::kill($pid, SIGUSR1);
             ConsoleTools::echoSuccess("reload commond is excute success");
        }else{
            ConsoleTools::echoSuccess("pid file is not exists!");
        }
    }

    public function isStart():bool
    {
        return $this->isStart;
    }
    

}