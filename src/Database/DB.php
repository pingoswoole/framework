<?php

declare(strict_types=1);
 
namespace Pingo\Database;

use PDO;
use Pingo\Pool\PoolManager;
use RuntimeException;
use Swoole\Coroutine;
use Swoole\Database\PDOStatementProxy;

class DB
{
    protected $pool;

    /** @var PDO */
    protected $pdo;

    private $in_transaction = false;

    public function __construct($config = null)
    {
        if (! empty($config)) {
            //$this->pool = \Pingo\Database\PDOPOOL::getInstance($config);
        } else {
            //$this->pool = \Pingo\Database\PDOPOOL::getInstance();
        }
        $this->pool  = PoolManager::getInstance()->getConnectionPool('mysql');
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

    public function insert(string $query, array $bindings = []): int
    {
        $this->realGetConn();

        $statement = $this->pdo->prepare($query);

        $this->bindValues($statement, $bindings);

        $statement->execute();

        $ret = (int) $this->pdo->lastInsertId();

        $this->release();

        return $ret;
    }

    protected function bindValues(PDOStatementProxy $statement, array $bindings): void
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
        try {
            //code...
            if (! $this->in_transaction) {
                $this->pdo = $this->pool->borrow();
            }
        } catch (\Throwable $th) {
            //throw $th;
            if(strpos($th->getMessage(), 'MySQL server has gone away')!==false){
                throw new \Exception('MySQL server has gone away');
              }
            $this->realGetConn();
        }
    }

    private function release()
    {
        if (! $this->in_transaction) {
            $this->pool->return($this->pdo);
            //$this->pool->close($this->pdo);
        }
    }
}
