<?php
namespace Pingo\Component;

use Pingo\Traits\Singleton;

class Di
{
    use Singleton;
    private $container = array();

    /**
     * 注入对象、类、变量、数组、回调
     *
     * @author pingo
     * @created_at 00-00-00
     * @param [type] $key
     * @param [type] $obj
     * @param [type] ...$arg
     * @return void
     */
    public function set($key, $obj,...$arg):void
    {
        $this->container[$key] = array(
            "obj"=>$obj,
            "params"=>$arg,
        );
    }

    function delete($key):void
    {
        unset( $this->container[$key]);
    }

    function clear():void
    {
        $this->container = array();
    }

    /**
     * @param $key
     * @return null
     * @throws \Throwable
     */
    function get($key)
    {
        if(isset($this->container[$key])){
            $obj = $this->container[$key]['obj'];
            $params = $this->container[$key]['params'];
            if(is_object($obj) || is_callable($obj)){
                return $obj;
            }else if(is_string($obj) && class_exists($obj)){
                try{
                    $this->container[$key]['obj'] = new $obj(...$params);
                    return $this->container[$key]['obj'];
                }catch (\Throwable $throwable){
                    throw $throwable;
                }
            }else{
                return $obj;
            }
        }else{
            return null;
        }
    }
}