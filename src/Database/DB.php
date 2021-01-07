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

    protected $builder;

    private $in_transaction = false;

    protected $table = '';

    protected $hasConnect = false; //是否获取连接

    public function __construct($config = null)
    {
        if (! empty($config)) {
            $this->pool = \Pingo\Database\PDOPOOL::getInstance($config);
        } else {
            $this->pool = \Pingo\Database\PDOPOOL::getInstance();
        }
        
    }

    public function table(string $table)
    {
        $this->table =  $table;
        $this->builder = (new Builder)->table($table);
        return $this;
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
        
        if($bindings) $this->bindValues($statement, $bindings);
        
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

    /**
     * 添加数据
     *
     * @author pingo
     * @created_at 00-00-00
     * @param array $fields
     * @return integer
     */
    public function insert(array $fields): int
    {
        try {
            //code...
            $this->realGetConn();
            $query = $this->builder->insert($fields)->toSQL(TRUE);
             
            $statement = $this->pdo->prepare($query);
            //$this->bindValues($statement, $bindings);
            $statement->execute();

            $ret = (int) $this->pdo->lastInsertId();

            $this->release();

            return $ret;

        } catch (\Throwable $th) {
            //throw $th;
            if($this->hasConnect) $this->release();
            throw new \Exception($th->getMessage());
            
        }
        
    }
    /**
     * 更新
     *
     * @author pingo
     * @created_at 00-00-00
     * @param array $data
     * @return void
     */
    public function update(array $data)
    {
        try {
            $sql = $this->builder->update($data)->toSQL(TRUE);
            $this->realGetConn();
            $statement = $this->pdo->prepare($sql);
            $statement->execute();
            $ret = $statement->rowCount();
            $this->release();

            return $ret;
            //code...
        } catch (\Throwable $th) {
            //throw $th;
            if($this->hasConnect) $this->release();
            throw new \Exception($th->getMessage());
        }

    }
    /**
     * 删除
     *
     * @author pingo
     * @created_at 00-00-00
     * @return void
     */
    public function delete()
    {
        try {
            $sql = $this->builder->delete()->toSQL(TRUE);
            $this->realGetConn();
            $statement = $this->pdo->prepare($sql);
            $statement->execute();
            $ret = $statement->rowCount();
            $this->release();

            return $ret;
            //code...
        } catch (\Throwable $th) {
            //throw $th;
            if($this->hasConnect) $this->release();
            throw new \Exception($th->getMessage());
        }
    }
    /**
     * 查询第一条记录
     *
     * @author pingo
     * @created_at 00-00-00
     * @return void
     */
    public function first()
    {
        try {
             
            $sql = $this->builder->first()->toSQL(TRUE);
            $this->realGetConn();

            $statement = $this->pdo->prepare($sql);

            //$this->bindValues($statement, $bindings);

            $statement->execute();

            $ret = $statement->fetch();

            $this->release();

            return $ret;
            
        } catch (\Throwable $th) {
            //throw $th;
            if($this->hasConnect) $this->release();
            throw new \Exception($th->getMessage());
        }
    }
    /**
     * 查询所有记录
     *
     * @author pingo
     * @created_at 00-00-00
     * @return void
     */
    public function get()
    {
        try {
            $sql = $this->builder->toSQL(TRUE);
            $this->realGetConn();

            $statement = $this->pdo->prepare($sql);

            //$this->bindValues($statement, $bindings);

            $statement->execute();

            $ret = $statement->fetchAll();

            $this->release();

            return $ret;
            //code...
        } catch (\Throwable $th) {
            //throw $th;
            if($this->hasConnect) $this->release();
            throw new \Exception($th->getMessage());
        }
    }
    /**
     * 字段递增
     * 
     * @author pingo
     * @created_at 00-00-00
     *  参数  数组形式、字符串    ['money' => 1, 'score' => 2] ('score', 100)
     * @return void
     */
    public function increment()
    {
        try {

            $params = func_get_args();
            switch (count($params)) {
                case 1:
                    if (gettype($params[0])=="array") {
                        foreach ($params[0] as $key => $val) {
                            $this->builder->increment($key, $val);
                        }
                    }else{
                        throw new \Exception('increment params is error');
                    }
                break;
                case 2:
                    $this->builder->increment($params[0], $params[1]);
                    break;
                case 3:
                case 4:
                case 5:
                     throw new \Exception('increment params is error');
                break;
            }
            
            $sql = $this->builder->toSQL(TRUE);
             
            $this->realGetConn();
            $statement = $this->pdo->prepare($sql);
            $statement->execute();
            $ret = $statement->rowCount();

            $this->release();

            return $ret;
            //code...
        } catch (\Throwable $th) {
            //throw $th;
            if($this->hasConnect) $this->release();
            throw new \Exception($th->getMessage());
        }
    }

    /**
     * 递减字段
     *
     * @author pingo
     * @created_at 00-00-00
     * @return void
     */
    public function decrement()
    {
        try {
            $params = func_get_args();
            switch (count($params)) {
                case 1:
                    if (gettype($params[0])=="array") {
                        foreach ($params[0] as $key => $val) {
                            $this->builder->decrement($key, $val);
                        }
                    }else{
                        throw new \Exception('decrement params is error');
                    }
                break;
                case 2:
                    $this->builder->decrement($params[0], $params[1]);
                    break;
                case 3:
                case 4:
                case 5:
                     throw new \Exception('decrement params is error');
                break;
            }
            
            $sql = $this->builder->toSQL(TRUE);
             
            $this->realGetConn();
            $statement = $this->pdo->prepare($sql);
            $statement->execute();
            $ret = $statement->rowCount();

            $this->release();

            return $ret;
            //code...
        } catch (\Throwable $th) {
            //throw $th;
            if($this->hasConnect) $this->release();
            throw new \Exception($th->getMessage());
        }
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
        if (! $this->in_transaction) {
            $this->pdo = $this->pool->getConnection();
            $this->hasConnect = true;
        }
    }

    private function release()
    {
        if (! $this->in_transaction) {
            $this->pool->close($this->pdo);
            $this->hasConnect = false;
        }
    }

    /**
     * 统计
     *
     * @author pingo
     * @created_at 00-00-00
     * @param string $method
     * @param string $field
     * @return void
     */
    public function aggregate(string $method, string $field = '')
    {
        try {
            $sql = $this->builder->toSQL(TRUE);
            
            $this->realGetConn();
            
            $statement = $this->pdo->prepare($sql);

            //$this->bindValues($statement, $bindings);

            $statement->execute();

            $ret = $statement->fetch();

            $this->release();

            $value = array_pop($ret);
            return $value ? floatval($value) : 0;
            //code...
        } catch (\Throwable $th) {
            //throw $th;
            if($this->hasConnect) $this->release();
            throw new \Exception($th->getMessage());
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
            if(in_array($method, ['count', 'sum', 'avg', 'max', 'min'])){
                $this->builder->{$method}(...$arguments);
                return $this->aggregate($method, ...$arguments);
            }else{
                $this->builder->{$method}(...$arguments);
            }
            return $this;
        } catch (\Exception $e) {
             //($e->getMessage());
             return false;
        }
    }
    

}
