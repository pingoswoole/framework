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

