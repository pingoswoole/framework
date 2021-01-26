<?php

declare(strict_types=1);
 
namespace Pingo\Database;

use RuntimeException;
use Swoole\Database\RedisConfig;
use Swoole\Database\RedisPool as SwooleRedisPool;

class RedisPool
{
    protected $pools;

    protected $config = [
        'host'      => 'localhost',
        'port'      => 6379,
        'auth'      => '',
        'db_index'  => 0,
        'time_out'  => 2,
        'size'      => 60,
    ];

    private static $instance;

    private function __construct(array $config)
    {
        if (empty($this->pools)) {
            $this->config = array_replace_recursive($this->config, $config);
            $this->pools = new SwooleRedisPool(
                (new RedisConfig())
                    ->withHost($this->config['host'])
                    ->withPort($this->config['port'])
                    ->withAuth($this->config['auth'])
                    ->withDbIndex($this->config['db_index'])
                    ->withTimeout($this->config['time_out']),
                $this->config['pool_size']
            );
        }
    }

    public static function getInstance($config = null)
    {
        if (empty(self::$instance)) {
            if (empty($config)) {
                throw new RuntimeException('redis config empty');
            }
            $config['pool_size'] = $config['pool_size'] ?? 10;
            if (empty($config['pool_size'])) {
                throw new RuntimeException('the size of redis connection pools cannot be empty');
            }
            self::$instance = new static($config);
        }

        return self::$instance;
    }

    public function getConnection()
    {
        return $this->pools->get();
    }

    public function close($connection = null)
    {
        $this->pools->put($connection);
    }

    public function getConfig(): array
    {
        return $this->config;
    }
}
