<?php
/**
 * pingoswoole 操作swoole核心函数
 * @author pingo <pingstrong@163.com>
 */
use Pingo\Component\Di;
use Pingo\Config\Config;

if(! function_exists("kill")){
    /**
     * 发送进程信号器
     *
     * @author pingo
     * @created_at 00-00-00
     * @param integer $pid
     * @param integer $sig
     * @return void
     */
    function kill(int $pid, int $sig)
    {
        try {
            return \Swoole\Process::kill($pid, $sig);
        } catch (\Exception $e) {
            return false;
        }
    }
}

if(!function_exists("set_process_name")){
    /**
     * 设置进程名称
     *
     * @author pingo
     * @created_at 00-00-00
     * @param string $name
     * @return void
     */
    function set_process_name(string $name)
    {
        if (!in_array(PHP_OS, ['Darwin', 'CYGWIN', 'WINNT'])) {
            swoole_set_process_name($name);
        }
    }
}

if(!function_exists('add_task'))
{
    /**
     * 添加异步任务， 返回任务ID
     *
     * @author pingo
     * @created_at 00-00-00
     * @param string $type
     * @param [type] $data
     * @return void
     */
    function add_task(string $type, $data)
    {
        $task_data = [
            'type' => $type,
            'data' => $data,
        ];
        return \Pingo\Swoole\Manager::getInstance()->getSwooleServer()->task($task_data);
    }
}


if(!function_exists('swoole_table_obj')){
    /**
     * 内存表对象获取
     *
     * @author pingo
     * @created_at 00-00-00
     * @param [type] $table_name
     * @return void
     */
    function swoole_table_obj($table_name)
    {
       $swoole_table_setting = Config::getInstance()->get('swoole_table');
       $key = $swoole_table_setting['prefix'] . $table_name;
       return Di::getInstance()->get($key);
    }

}