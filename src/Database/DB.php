<?php

declare(strict_types=1);
 
namespace Pingo\Database;

use PDO;
use Pingo\Database\QueryBuilder\Builder;
use RuntimeException;
use Swoole\Coroutine;
use Swoole\Database\PDOStatementProxy;

class DB
{
    protected $pool;

    /** @var PDO */
    protected $pdo;

    protected static $builder;

    private $in_transaction = false;

    protected static $table = '';

    public function __construct($config = null)
    {
        if (! empty($config)) {
            $this->pool = \Pingo\Database\PDOPOOL::getInstance($config);
        } else {
            $this->pool = \Pingo\Database\PDOPOOL::getInstance();
        }
        //$this->builder = new Builder;
    }

    public static function getInstance($config = null)
    {
        return new static($config);
    }

    public static function table(string $table)
    {
        self::$table = $table;
        self::$builder = Builder::table($table);
        return self::getInstance();
    }

    public function quote(string $string, int $parameter_type = PDO::PARAM_STR)
    {
        $this->realGetConn();
        $ret = $this->pdo->quote($string, $parameter_type);
        $this->release();
        return $ret;
    }

    public function beginTransaction(): void
    {
        if ($this->in_transaction) { //嵌套事务
            throw new RuntimeException('do not support nested transaction now');
        }
        $this->realGetConn();
        $this->pdo->beginTransaction();
        $this->in_transaction = true;
        Coroutine::defer(function () {
            if ($this->in_transaction) {
                $this->rollBack();
            }
        });
    }

    public function commit(): void
    {
        $this->pdo->commit();
        $this->in_transaction = false;
        $this->release();
    }

    public function rollBack(): void
    {
        $this->pdo->rollBack();
        $this->in_transaction = false;
        $this->release();
    }

    public function query(string $query, array $bindings = []): array
    {
        $this->realGetConn();

        $statement = $this->pdo->prepare($query);

        $this->bindValues($statement, $bindings);

        $statement->execute();

        $ret = $statement->fetchAll();

        $this->release();

        return $ret;
    }

    public function fetch(string $query, array $bindings = [])
    {
        $records = $this->query($query, $bindings);

        return array_shift($records);
    }

    public function execute(string $query, array $bindings = []): int
    {
        $this->realGetConn();

        $statement = $this->pdo->prepare($query);

        $this->bindValues($statement, $bindings);

        $statement->execute();

        $ret = $statement->rowCount();

        $this->release();

        return $ret;
    }

    public function exec(string $sql): int
    {
        $this->realGetConn();

        $ret = $this->pdo->exec($sql);

        $this->release();

        return $ret;
    }

    public function insert(array $fields): int
    {
        $this->realGetConn();
        $query = self::$builder->insert($fields);
        $statement = $this->pdo->prepare($query);

        //$this->bindValues($statement, $bindings);

        $statement->execute();

        $ret = (int) $this->pdo->lastInsertId();

        $this->release();

        return $ret;
    }

    protected function bindValues(\PDOStatement $statement, array $bindings): void
    {
        foreach ($bindings as $key => $value) {
            $statement->bindValue(
                is_string($key) ? $key : $key + 1,
                $value,
                is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR
            );
        }
    }

    private function realGetConn()
    {
        if (! $this->in_transaction) {
            $this->pdo = $this->pool->getConnection();
        }
    }

    private function release()
    {
        if (! $this->in_transaction) {
            $this->pool->close($this->pdo);
        }
    }


    /**
     * Tunnel any non-existent static calls as object calls on the Connection object.
     *
     * @param $method
     * @param $arguments
     *
     * @throws \PDOException
     *
     * @return mixed
     */
    /* public static function __callStatic($method, $arguments)
    {
        try {
            return static::getConnection()->$method(...$arguments);
        } catch (\PDOException $e) {
            die($e->getMessage());
        }
    } */
    
    public  function __call($method, $arguments)
    {
        try {
            self::$builder->{$method}(...$arguments);
            return $this;
        } catch (\Exception $e) {
             //($e->getMessage());
             return false;
        }
    }
    

}
