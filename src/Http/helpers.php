<?php

if (! function_exists('random_str')) {
    /**
     * 随机返回字符串
     * @param number 返回字符串长度
     * @param string 从哪些字符串中随机返回，已设置默认字符串，可空
     * @param boolean 是否需要特殊字符
     * @return string 返回随机字符串
     */
    function random_str($length = 8, $chars = null, $special = false)
    {
        $s = "";
        if (empty($chars)) {
            $chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789" . ($special ? "~!#$%^&*()_+{<>?.}": "");
        }
        while (strlen($s) < $length) {
            $s .= substr($chars, rand(0, strlen($chars) - 1), 1);
        }
        return $s;
    }
}
if (! function_exists('trimall')) {
    /**
     * 字符串替换所有特定字符
     *
     * @author pingo
     * @created_at 00-00-00
     * @param [type] $str
     * @param string $replace
     * @return void
     */
    function trimall($str, $replace = ' ')
    {
        return preg_replace("#{$replace}#", '', $str);
    }
}

if (! function_exists('config')) {

    /**
     * 获取配置项
     *
     * @author pingo
     * @created_at 00-00-00
     * @param [type] $key
     * @param [type] $default
     * @return void
     */
    function config($key = null, $default = null)
    {
        return \Pingo\Config\Config::getInstance()->get($key, $default);
    }

}

if(!function_exists("cache")){
    /**
     * Redis缓存操作
     *
     * @author pingo
     * @created_at 00-00-00
     * @param [type] $key
     * @param [type] $value
     * @return void
     */
    function cache($key = null, $value = null)
    {
        $RedisHandler = (new \Pingo\Database\Redis);
        if(is_null($key)) return $RedisHandler;
        if(!is_null($value)){
            return $RedisHandler->set($key, $value);
        }
        return $RedisHandler->get($key);
    }
}

if(!function_exists("db")){
    /**
     * DB
     *
     * @author pingo
     * @created_at 00-00-00
     * @param [type] $config
     * @return void
     */
    function db($config = null)
    {
        return (new \Pingo\Database\DB($config));
    }
}

 

if(!function_exists('line_tohump'))
{
    /*
	 * 下划线转驼峰
	 */
    function line_tohump($str = '')
	{
		 return  preg_replace_callback('/_+([a-z])/',function($matches){
			  return strtoupper($matches[1]);
			}, $str);
	}

}

if(!function_exists('hump_toline'))
{
    /*
	 * 驼峰转下划线
	 */
    function hump_toline($str = '')
    {
        return  strtolower(preg_replace('/(?<=[a-z])([A-Z])/', '_$1', $str));
    }
}

/**
 * 是否关联数组
 *
 * @author pingo
 * @created_at 00-00-00
 * @param array $var
 * @return boolean
 */
function is_assoc_array(array $var)  
{  
    return array_diff_assoc(array_keys($var), range(0, sizeof($var))) ? TRUE : FALSE;  
}


if (!function_exists('env_get')) {

    /**
     * 获取根目录.env配置项
     *
     * @author pingo
     * @created_at 00-00-00
     * @param [type] $name
     * @param [type] $default
     * @return void
     */
    function env_get($name, $default = null)
    {
        $file = PINGSWOOLE_WEB_ROOT . '/.env';
        $configs = parse_ini_file($file, true);
        if (empty($configs)) {
            return $default;
        }
         // 判断 是否加了注释 # 并且判断是否设置【章节】即是否是二维数组
         foreach ($configs as $key => $val) {
            if (is_array($val)) {
                foreach ($val as $k => $v) {
                    if (substr($k, 0, 1) == '#' || substr($key, 0, 2) == '//') {
                    unset($configs[$key][$k]);
                    }
                    // 如果传了$name 字段，那么根据name获取指定的字段值
                    if ($k == $name && !empty($name)) {
                        return isset($configs[$key][$k]) ? $configs[$key][$k] : null;
                    }
                }
            } else {
                if (substr($key, 0, 1) == '#' || substr($key, 0, 2) == '//') {
                unset($configs[$key]);
                }
            // 如果传了$name 字段，那么根据name获取指定的字段值
            if ($key == $name && !empty($name)) {
                return isset($configs[$key]) ? $configs[$key] : null;
            }
         }
      }
      return ($default) ;

    }
}


if(!function_exists('app_log'))
{
    function app_log($msg = '')
    {
        (new \Pingo\Log\Logger(WEB_LOG_PATH))->log($msg);
    }
}