<?php

declare(strict_types=1);
namespace Pingo\Database;

use RuntimeException;
use Swoole\Database\PDOConfig;
use Swoole\Database\PDOPool as SwoolePDOPool;

class PDOPool
{
    protected $pools;

    /**
     * @var array
     */
    protected $config = [
        'host'       => 'localhost',
        'port'       => 3306,
        'database'   => 'test',
        'username'   => 'root',
        'password'   => 'root',
        'charset'    => 'utf8mb4',
        'unixSocket' => null,
        'options'    => [],
        'size'       => 60,
    ];

    private static $instance;

    private function __construct(array $config)
    {
        if (empty($this->pools)) {
            $this->config = array_replace_recursive($this->config, $config);
            $this->pools = new SwoolePDOPool(
                (new PDOConfig())
                    ->withHost($this->config['host'])
                    ->withPort($this->config['port'])
                    ->withUnixSocket($this->config['unixSocket'])
                    ->withDbName($this->config['database'])
                    ->withCharset($this->config['charset'])
                    ->withUsername($this->config['username'])
                    ->withPassword($this->config['password'])
                    ->withOptions($this->config['options']),
                $this->config['pool_size']
            );
        }
    }

    public static function getInstance($config = null)
    {
        if (empty(self::$instance)) {
            if (empty($config)) {
                throw new RuntimeException('pdo config empty');
            }
            if (empty($config['pool_size'])) {
                throw new RuntimeException('the size of database connection pools cannot be empty');
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
}
