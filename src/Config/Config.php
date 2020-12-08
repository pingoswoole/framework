<?php
 
namespace Pingo\Config;


use Pingo\Traits\Singleton;

class Config
{
    private static $config = [];

    use Singleton;

    public function __construct()
    {
       
    }

    /**
     * 获取配置项
     * @param string $keyPath 配置项名称 支持点语法
     * @return array|mixed|null
     */
    public function get($keys, $default = null)
    {
        $keys = explode('.', strtolower($keys));
        if (empty($keys)) {
            return null;
        }

        $file = array_shift($keys);
        if (empty(self::$config[$file])) {

            if (! is_file(WEB_CONF_PATH . $file . '.php')) {
                return null;
            }
            self::$config[$file] = include WEB_CONF_PATH . $file . '.php';
        }

        $config = self::$config[$file];
        while ($keys) {
            $key = array_shift($keys);
            if (! isset($config[$key])) {
                $config = $default;
                break;
            }
            $config = $config[$key];
        }

        return $config;
    }
    
    /**
     * 加载配置目录文件
     *
     * @author pingo
     * @created_at 00-00-00
     * @param [type] $path
     * @return void
     */
    public function loadDir($path)
    {
        $dirFiles = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path), \RecursiveIteratorIterator::LEAVES_ONLY);
        foreach($dirFiles as $FileIterator)
        {
            if($FileIterator->isFile()){
                $key = substr($FileIterator->getFileName(), 0, strpos($FileIterator->getFileName(), "."));
                self::$config[$key] = include $FileIterator->getPath() . DIRECTORY_SEPARATOR . $FileIterator->getFileName();
            }
        }
    }



}
