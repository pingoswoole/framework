<?php
namespace Pingo\Component;

/**
 * 控制台工具
 *
 * @author pingo
 * @created_at 00-00-00
 */
class ConsoleTools
{
    /**
     * 打印内容到控制台
     *
     * @author pingo
     * @created_at 00-00-00
     * @param [type] $name
     * @param [type] $value
     * @return void
     */
    public static function echo($name, $value)
    {
        $str = "\e[32m". str_pad($name, 30, " ", STR_PAD_RIGHT) .  " \33[45m\33[37m  {$value}  \33[0m ";
        echo $str . PHP_EOL;
    }

    public static function echoSuccess($msg)
    {
        echo ('[' . date('Y-m-d H:i:s') . '] [INFO] ' . "\033[32m{$msg}\033[0m" . PHP_EOL);
    }

    public static function echoError($msg)
    {
        echo ('[' . date('Y-m-d H:i:s') . '] [ERROR] ' . "\033[31m{$msg}\033[0m" . PHP_EOL);
    }

    /**
     * 解析命令行参数
     *
     * @author pingo
     * @created_at 00-00-00
     * @return void
     */
    public static function parseArgs()
    {
        $args = WEB_CLI_ARGV;
        $argc = WEB_CLI_ARGC;
        if ($argc < 2) {
            # code...
            return false;
        }
        $shell_file = array_shift($args);
        $commond = array_shift($args);
        if(false !== strpos($commond, ":")){
            $commonds = explode(":", $commond);
            if(count($commonds) !== 2) return false;
            return [$commonds[0], $commonds[1], $args];
        }
        return false;
    }

    /**
     * 设置进程的名称
     * @param $name
     */
    static function setProcessName($name)
    {
        if (function_exists('cli_set_process_title'))
        {
            @cli_set_process_title($name);
        }
        else
        {
            if (function_exists('swoole_set_process_name'))
            {
                @swoole_set_process_name($name);
            }
            else
            {
                trigger_error(__METHOD__ . " failed. require cli_set_process_title or swoole_set_process_name.");
            }
        }
         
    }

    /**
     * opCacheClear function
     *
     * @author pingo
     * @created_at 00-00-00
     * @return void
     */
    public static function opCacheClear()
    {
        if(function_exists('apc_clear_cache')){
            apc_clear_cache();
        }
        if(function_exists('opcache_reset')){
            opcache_reset();
        }
    }

 

}