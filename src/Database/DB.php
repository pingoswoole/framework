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

    protected $_sql = [];

    protected $_result = [];

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

    public function query(string $query, array $bindings = [])
    {
        $this->realGetConn();

        $statement = $this->pdo->prepare($query);
        $this->_sql[] = $query;
        if($bindings) $this->bindValues($statement, $bindings);
        
        $statement->execute();

        //$ret = $statement->fetchAll();

        $this->release();

        return $statement;
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
        * 返回数组中指定多列
        *
        * @param Array $input 需要取出数组列的多维数组
        * @param String $column_keys 要取出的列名，逗号分隔，如不传则返回所有列
        * @param String $index_key 作为返回数组的索引的列
        * @return Array
    */
    function _array_columns($input, $column_keys = null, $index_key = null)
    {
        $result = array();
        
        $keys =isset($column_keys)? explode(',', $column_keys) : array();
        
        if($input){
            foreach($input as $k=>$v){
            
                // 指定返回列
                if($keys){
                    $tmp = array();
                    foreach($keys as $key){
                        $tmp[$key] = $v[$key];
                    }
                }else{
                    $tmp = $v;
                }
                
                // 指定索引列
                if(isset($index_key)){
                    $result[$v[$index_key]][] = $tmp;
                }else{
                    $result[] = $tmp;
                }
                
            }
        }
        
        return $result;
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
            $this->_sql[] = $query; 
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
            $this->_sql[] = $sql; 
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
            $this->_sql[] = $sql; 
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


    protected function forPage($page, $count)
    {
        $number = ($page - 1) * $count;
        $this->builder->limit($count)->skip($number);
        return $this;
    }

    public function page(int $page = 1, int $page_size = 10)
    {
        if($page <= 0) $page = 1;
        if($page_size <= 0) $page_size = 10;
        $number = ($page - 1) * $page_size;
        $limit = "{$number}, {$page_size}";
        $this->builder->limit($limit);
        return $this;
    }

    public function chunk($count, callable $callback)
    {
        // 类似于limit,offset 实现数据分页查询   LIMIT 100 OFFSET 500
        $page = 1;
        $results = $this->forPage($page, $count)->get();
    
        while (count($results) > 0) {
            // On each chunk result set, we will pass them to the callback and then let the
            // developer take care of everything within the callback, which allows us to
            // keep the memory low for spinning through large result sets for working.
            // 如果用户回调中，更新的字段与查询的字段是一个条件，就会出现这样的问题             
            if (call_user_func($callback, $results) === false) {
                return false;
            }
            $page++;
            $results = $this->forPage($page, $count)->get();
        }
    
        return true;
    }


    public function value(string $name)
    {
        try {
            //code...
            $this->realGetConn();
            $this->builder->limit(1);
            $sql = $this->builder->toSQL(true);
            $this->_sql[] = $sql;
            $statement = $this->pdo->prepare($sql);
            //$this->bindValues($statement, $bindings);
            $statement->execute();
            $this->_result = $statement->fetch();
            $this->release();

            if($this->_result){
                
                //
                return $this->_result[$name]?? null;
            }
            return null;
        } catch (\Throwable $th) {
            //throw $th;
            //throw $th;
            if($this->hasConnect) $this->release();
            throw new \Exception($th->getMessage());
        }
    }
    /**
     * 获取一列多列
     *
     * @author pingo
     * @created_at 00-00-00
     * @return void
     */
    public function pluck()
    {
        try {
            //code...
            $params = func_get_args();
            $this->realGetConn();
            $sql = $this->builder->toSQL(true);
            $this->_sql[] = $sql;
            
            $statement = $this->pdo->prepare($sql);
            //$this->bindValues($statement, $bindings);
            $statement->execute();
            $this->_result = $statement->fetchAll();
            $this->release();

            if($this->_result){
                
                //
                switch (count($params)) {
                    case 0:
                        # code...
                        return $this->_result;
                        break;
                    case 1:
                        return array_column($this->_result, $params[0]);
                        break;
                    case 2:
                        if(is_array($params[0])){
                            return $this->_array_columns($this->_result, implode(',' , $params[0]), $params[1]);
                        }
                        return array_combine(array_column($this->_result, $params[1]), array_column($this->_result, $params[0]));
                        break;
                    default:
                        # code...
                        break;
                }
            }

            return [];

        } catch (\Throwable $th) {
            //throw $th;
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
            $this->_sql[] = $sql; 
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
            $this->_sql[] = $sql; 
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
            $this->_sql[] = $sql; 
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
            $this->_sql[] = $sql; 
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
        $this->builder = (new Builder)->table($this->table);
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
            $this->_sql[] = $sql; 
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
             throw new \Exception($e->getMessage());
        }
    }
    

}
