<?php

declare(strict_types=1);
/**
 * PingoSwoole
 * @author pingo <pingstrong@163.com>
 */
namespace Pingo\Swoole;

use Pingo\Component\ConsoleTools;
use Pingo\Config\Config;
use Pingo\Component\Di;
use Pingo\Database\Migration;
use Pingo\Log\LoggerInterface;
use Pingo\Log\Logger;
use Pingo\Trigger\Trigger;
use Pingo\Trigger\TriggerInterface;
use Pingo\Trigger\Location;

/**
 * 应用
 *
 * @author pingo
 * @created_at 00-00-00
 */
class Application
{
    private $swooleServer   = null;
    private $childServer    = null;
    private $isRun          = false;

    /**
     * 启动服务公告语
     *
     * @author pingo
     * @created_at 00-00-00
     * @return void
     */
    public static function banner()
    {
        echo <<<WELCOME
            _                                     _      
        (_)                                   | |     
    _ __  _ _ __   __ _ _____      _____   ___ | | ___ 
    | '_ \| | '_ \ / _` / __\ \ /\ / / _ \ / _ \| |/ _ \
    | |_) | | | | | (_| \__ \\ V  V / (_) | (_) | |  __/
    | .__/|_|_| |_|\__, |___/ \_/\_/ \___/ \___/|_|\___|
    | |             __/ |                               
    |_|            |___/        
                  
    -------------------------------------------------------

WELCOME;
        ConsoleTools::echo("listen ip", config("servers.ip"));
        ConsoleTools::echo("listen port", config("servers.port"));
        ConsoleTools::echo("swoole version", phpversion("swoole"));
        ConsoleTools::echo("php version", phpversion());
        ConsoleTools::echo("pingoswoole version", Constant::VERSION);
        ConsoleTools::echo("tmp dir", WEB_TMP_PATH);
        ConsoleTools::echo("log dir", WEB_LOG_PATH);
    }

     
    /**
     * 执行入口
     *
     * @author pingo
     * @created_at 00-00-00
     * @return void
     */
    public static function run()
    {
        if (false === $commonds = ConsoleTools::parseArgs()) {
            exit(ConsoleTools::echoError("命令参数错误！！！"));
        }
        //环境检查
        self::checkEnv();
        //加载配置文件
        self::loadConfig();
        //初始化运行时文件目录
        self::initRuntimeDir();
        //注册【错误、异常、脚本关闭】处理器
        self::registerExceptionHandler();
        //初始化APP配置
        \App\SwooleEvent::initialize();
        //启动swoole
        self::startServer($commonds[0], $commonds[1], $commonds[2]);
    }
    /**
     * 环境检查
     *
     * @author pingo
     * @created_at 00-00-00
     * @return void
     */
    public static function checkEnv()
    {
        if (!extension_loaded("swoole")) {
            ConsoleTools::echoError("please install swoole php extension.");
            exit();
        }
        if (version_compare(phpversion(), '7.3.0', '<')) {
            ConsoleTools::echoError("php version must >= 7.3.0, current php version = ".phpversion());
            exit();
        }
        if (version_compare(swoole_version(), '4.4.5', '<')) {
            ConsoleTools::echoError("the swoole version must >= 4.4.5, current swoole version = ".swoole_version());
            exit();
        }
        if (!extension_loaded('pcntl')) {
            ConsoleTools::echoError("Missing install pcntl extentions,please install it");
            exit();
        }

        if (!extension_loaded('posix')) {
            ConsoleTools::echoError("Missing install posix extentions,please install it");
            exit();
        }

        if (!extension_loaded('zlib')) {
            ConsoleTools::echoError("Missing install zlib extentions,please install it");
            exit();
        }

        if (!extension_loaded('mbstring')) {
            ConsoleTools::echoError("Missing install mbstring extentions,please install it");
            exit();
        }
    }
    /**
     * 加载配置文件
     *
     * @author pingo
     * @created_at 00-00-00
     * @return void
     */
    private static function loadConfig()
    {
        Config::getInstance()->loadDir(WEB_CONF_PATH);
    }

    //初始化运行时文件目录
    private static function initRuntimeDir()
    {
        //创建日记目录、临时目录
        if (!is_dir(WEB_LOG_PATH)) {
            mkdir(WEB_LOG_PATH, 0777, true);
        }
        if (!is_dir(WEB_TMP_PATH)) {
            mkdir(WEB_TMP_PATH, 0777, true);
        }
    }

    //注册【错误、异常、脚本关闭】处理器
    private static function registerExceptionHandler()
    {
        ini_set("display_errors", "On");
        $level = Di::getInstance()->get(Constant::ERROR_REPORT_LEVEL);
        if ($level === null) {
            $level = E_ALL;
        }
        error_reporting($level);

        //初始化配置Logger
        $logger = Di::getInstance()->get(Constant::LOGGER_HANDLER);
        if (!$logger instanceof LoggerInterface) {
            $logger = new Logger(WEB_LOG_PATH);
            Di::getInstance()->set(Constant::LOGGER_HANDLER, $logger);
        }
        //初始化追追踪器
        $trigger = Di::getInstance()->get(Constant::TRIGGER_HANDLER);
        if (!$trigger instanceof TriggerInterface) {
            $trigger = new Trigger($logger);
            Di::getInstance()->set(Constant::TRIGGER_HANDLER, $trigger);
        }

        //在没有配置自定义错误处理器的情况下，转化为trigger处理
        $errorHandler = Di::getInstance()->get(Constant::ERROR_HANDLER);
        if (!is_callable($errorHandler)) {
            $errorHandler = function ($errorCode, $description, $file = null, $line = null) use ($trigger) {
                $l = new Location();
                $l->setFile($file);
                $l->setLine($line);
                $trigger->error($description, $errorCode, $l);
            };
        }
        set_error_handler($errorHandler);

        $func = Di::getInstance()->get(Constant::SHUTDOWN_FUNCTION);
        if (!is_callable($func)) {
            $func = function () use ($trigger) {
                $error = error_get_last();
                if (!empty($error)) {
                    $l = new Location();
                    $l->setFile($error['file']);
                    $l->setLine($error['line']);
                    $trigger->error($error['message'], $error['type'], $l);
                }
            };
        }
        register_shutdown_function($func);
    }

    //启动swoole
    private static function startServer(string $progress_name, string $action, array $options = [])
    {
        switch ($progress_name) {
            case 'server':
                # code...
                $servers = Config::getInstance()->get("servers");
                $daemonize = false;
                if (in_array('-d', $options)) {
                    $daemonize = true;
                }
                $servers['protocol'][\Pingo\Swoole\Constant::SWOOLE_WEBSOCKET_SERVER]['setting']['daemonize'] = $daemonize;
                Manager::getInstance()->setSetting($servers);
                if ($action === "start") {
                    //WELLCOME
                    self::banner();
                    Manager::getInstance()->createSwooleServer();
                    Manager::getInstance()->start();
                } elseif ($action === "stop") {
                    # code...
                    Manager::getInstance()->stop();
                } elseif ($action === "restart") {
                    # code...
                    Manager::getInstance()->reload();
                } else {
                }
                break;
            case 'task':
                # code...
                break;
            case 'process':
                # code...
                break;
            case 'crontab':
                # code...
                break;
            case 'config':
                # code...
                break;
            case 'migration':
                 
                break;
            default:
                # code...
                ConsoleTools::echoError("Please use server:start | task | process | config | crontab | migration:create|reset");
                exit();
                break;
        }
    }
}
