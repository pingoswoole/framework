<?php
namespace Pingo\Traits;
/**
 * 单例模式
 * @author pingo <pingstrong@163.com>
 */

trait Singleton
{
    private static $instance;

    static function getInstance(...$args)
    {
        if(!isset(self::$instance)){
            self::$instance = new static(...$args);
        }
        return self::$instance;
    }
}