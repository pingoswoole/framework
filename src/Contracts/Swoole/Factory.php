<?php
namespace Pingo\Contracts\Swoole;
/**
 * swoole 对象构造工厂接口
 *
 * @author pingo
 * @created_at 00-00-00
 */
interface Factory
{
    public function getSwooleServer(string $serverName = null);
    public function createSwooleServer(...$args): bool;
    public function addServer(string $serverName, int $port, int $type, string $address, array $setting, array $event_register): bool;
    public function registerEvent($server, array $event_register);
    public function start();
    public function isStart():bool;
    
}