<?php

namespace Pingo\Cache;

use One\ConfigTrait;

class File implements CacheInterface
{
    
    public function get($key, $default = null, \Closure $closure = null, $ttl = 0);

    public function set($key, $val, $ttl = 0);

    public function del($key);

    public function mget($keys);

    public function batchDel():bool;
    
    public function increment($key, $value = 1): bool;

    public function decrement($key, $value = 1): bool;

    public function has($key):bool;
    
    public function flush();
}