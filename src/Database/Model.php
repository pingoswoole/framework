<?php

declare(strict_types=1);
 
namespace Pingo\Database;

use Exception;
use InvalidArgumentException;
use PDO;
use RuntimeException;
use Swoole\Coroutine;
use Swoole\Database\PDOStatementProxy;

use Pingo\Database\QueryBuilder\Builder;

/*!
 * Medoo database framework
 * https://medoo.in
 * Version 1.7.10
 *
 *  避免单例实例化，造成协程混乱
 *  
 */

class Raw
{
    public $map;

    public $value;
}

//abstract class Model implements Arrayable, ArrayAccess, Jsonable, JsonSerializable, QueueableEntity, UrlRoutable

class  Model
{
    protected $pool;

    /** @var PDO */
    protected $pdo;

    protected $statement;

    protected $logs = [];

    protected $logging = false;

    protected $debug_mode = false;

    protected $guid = 0;

    protected $errorInfo;


    protected $last_sql = ''; //最后执行sql

    private   $in_transaction = false;

    protected $table = '';
    
    protected $attributes = []; //属性

    protected $builder;

    protected $is_connect = false; //是否获取连接

    protected $casts = []; //integer，float，double，，string，boolean，object，array， datetime 
    
    protected $with = [];
    /**
     * Indicates if the model exists.
     *
     * @var bool
     */
    public $exists = false;

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * The "type" of the primary key ID.
     *
     * @var string
     */
    protected $keyType = 'int';
     /**
     * The name of the "created at" column.
     *
     * @var string|null
     */
    const CREATED_AT = 'created_at';

    /**
     * The name of the "updated at" column.
     *
     * @var string|null
     */
    const UPDATED_AT = 'updated_at';

    //const DELETED_AT = 'deleted_at'; //软删除

    protected $timestamps = true; //新增自动添加 created_at、修改增加 updated_at

    protected $_result = []; //最后查询结果集

    protected $_sql = []; //执行sql语句

    protected $appends = []; //追加属性

    /**
     * 
     *  架构方法
     * @author pingo
     * @created_at 00-00-00
     * @param boolean $get_pool 是否获取连接池
     * @param array $config 连接池配置
     */
    public function __construct($get_pool = true, $config = [])
    {
        
        if($get_pool){
             
            if (! empty($config)) {
                $this->pool = \Pingo\Database\PDOPool::getInstance($config);
            } else {
                $this->pool = \Pingo\Database\PDOPool::getInstance();
            }
        }

        if(empty($this->table)){
            $model_name = (new \ReflectionClass(get_called_class()))->getShortName();
            $this->table = \hump_toline($model_name);
        }
         
        $this->builder = (new Builder)->table($this->table);

    }

    public function beginTransaction()
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

    public function action($actions)
    {
        if (is_callable($actions)) {
            $this->beginTransaction();

            try {
                $result = $actions($this);

                if ($result === false) {
                    $this->rollBack();
                } else {
                    $this->commit();
                }
            } catch (Exception $e) {
                $this->rollBack();

                throw $e;
            }

            return $result;
        }

        return false;
    }

    public function query($query, $bindings = [])
    {
        $this->realGetConn();

        $statement = $this->pdo->prepare($query);
        
        if($bindings) $this->bindValues($statement, $bindings);
        
        $statement->execute();

        $ret = $statement->fetchAll();

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

    public function exec($query, $map = [])
    {
        $this->realGetConn();

        $this->statement = null;

        if ($this->debug_mode) {
            echo $this->generate($query, $map);

            $this->debug_mode = false;

            $this->release();

            return false;
        }

        if ($this->logging) {
            $this->logs[] = [$query, $map];
        } else {
            $this->logs = [[$query, $map]];
        }
        $statement = $this->pdo->prepare($query);

        if (! $statement) {
            $this->errorInfo = $this->pdo->errorInfo();
            $this->statement = null;

            $this->release();

            return false;
        }

        $this->statement = $statement;

        foreach ($map as $key => $value) {
            $statement->bindValue($key, $value[0], $value[1]);
        }

        $execute = $statement->execute();

        $this->errorInfo = $statement->errorInfo();

        if (! $execute) {
            $this->statement = null;
        }

        $lastId = $this->pdo->lastInsertId();

        if ($lastId != '0' && $lastId != '') {
            $this->release();

            return $lastId;
        }

        $this->release();

        return $statement;
    }

    public static function raw($string, $map = [])
    {
        $raw = new Raw();

        $raw->map = $map;
        $raw->value = $string;

        return $raw;
    }


    public function quote($string)
    {
        $this->realGetConn();
        $ret = $this->pdo->quote($string);
        $this->release();
        return $ret;
    }

    public function _sql()
    {
        return $this->_sql;
    }
    

    public function drop($table)
    {
        $tableName = $table;

        return $this->exec("DROP TABLE IF EXISTS {$tableName}");
    }
 

    public function debug()
    {
        $this->debug_mode = true;

        return $this;
    }

    public function error()
    {
        return $this->errorInfo;
    }
  

    private function realGetConn()
    {
        if (! $this->in_transaction) {
            $this->pdo = $this->pool->getConnection();
            $this->pdo->exec('SET SQL_MODE=ANSI_QUOTES');
            $this->is_connect = true;
        }
    }

    private function release()
    {
        if (! $this->in_transaction) {
            $this->pool->close($this->pdo);
            $this->is_connect = false;
        }
        $this->builder = (new Builder)->table($this->table);
        //写日记
        app_log( implode('##', $this->_sql) );
    }
 
    /**
     * 格式化数据
     *
     * @author pingo
     * @created_at 00-00-00
     * @param array $data 二维数组
     * @param int $flow 类型，1 正向新增， 0 反向查询
     * @return array
     */
    public function _casts(array $data, int $flow = 0):array
    {
         //默认时间转换
         if($flow == 0){
             if(isset($data[self::CREATED_AT]) && is_integer($data[self::CREATED_AT])){
                $data[self::CREATED_AT] = date("Y-m-d H:i:s", $data[self::CREATED_AT]);
             }
             if(isset($data[self::UPDATED_AT]) && is_integer($data[self::UPDATED_AT])){
                $data[self::UPDATED_AT] = date("Y-m-d H:i:s", $data[self::UPDATED_AT]);
             }
         }
         //已配置转换
         if($this->casts){
             foreach ($this->casts as $key => $type) {
                 # code...integer，float，double，，string，boolean，object，array， datetime 
                 if(isset($data[$key])){
                     switch ($type) {
                        case 'integer':
                            # code...
                            $data[$key] = intval($data[$key]);
                            break;
                        case 'float':
                             # code...
                             $data[$key] = floatval($data[$key]);
                             break;
                         case 'double':
                             # code...
                             $data[$key] = doubleval($data[$key]);
                             break;
                         case 'string':
                             # code...
                             $data[$key] = "" . $data[$key];
                             break;
                         case 'boolean':
                             # code... tinyint(1) 1代表TRUE,0代表FALSE
                             if($flow == 1){
                                $data[$key] =  $data[$key] === true ? 1 : 0;
                             }else{
                                $data[$key] =  $data[$key] == 1 ? true : false;
                             }
                             break;
                         case 'object':
                            # code...
                            if($flow == 1){
                                $data[$key] =  serialize($data[$key]);
                             }else{
                                $data[$key] =  unserialize($data[$key]);
                             }
                            break;
                         case 'array':
                         case 'json':
                             # code...
                             if($flow == 1){
                                $data[$key] =  json_encode($data[$key]);
                             }else{
                                $data[$key] =  json_decode($data[$key], true);
                             }
                             break;
                         case 'datetime':
                            # code...
                            if($flow == 1){
                                $data[$key] =  strtotime($data[$key]);
                             }else{
                                $data[$key] =  date("Y-m-d H:i:s" , $data[$key]);
                             }
                            break;
                            
                         default:
                             # code...
                             break;
                     }
                 }
             }
         }

         return $data;
    }

    public function appends(array $data)
    {
        $this->appends = array_merge($this->appends, $data);
         
    }

    /**
     * 新增或修改
     *
     * @author pingo
     * @created_at 00-00-00
     * @return void
     */
    public function save()
    {
        try {
            //修改
            $this->realGetConn();
            
            if($this->exists){
                //是否存在主键
                if(isset($this->attributes[$this->primaryKey])){
                    $this->last_sql = $this->builder->update($this->attributes)->toSQL(TRUE);
                }
                throw new \Exception("主键不存在：{$this->primaryKey}");
            }else{
                //新增
                if(empty($this->attributes)) throw new \Exception("新增数据不能为空");
                $this->last_sql = $this->builder->insert($this->attributes)->toSQL(TRUE);
            }
            $statement = $this->pdo->prepare($this->last_sql);
            $execute = $statement->execute();
            $this->errorInfo = $statement->errorInfo();

            if($this->exists){
                $result = $this->pdo->lastInsertId();
            }else{
                $result = $statement->rowCount();
            }

            $this->release();
            return $result;

        } catch (\Throwable $th) {
            //throw $th;
            if($this->is_connect) $this->release();
            throw new \Exception($th->getMessage());
        }

    }
    /**
     * 新增
     *
     * @author pingo
     * @created_at 00-00-00
     * @param array $data
     * @return void
     */
    public function create(array $data = [])
    {
        try {
            //code...
            if(empty($data)) throw new \Exception("新增数据不能为空");
            $data = $this->_casts($data, 1);
            if($this->timestamps) $data[self::CREATED_AT] = time();
            $sql = $this->builder->insert($data)->toSQL(true);
            $this->_sql[] = $sql;
            $this->realGetConn();
            $statement = $this->pdo->prepare($sql);
            //$this->bindValues($statement, $bindings);
            $statement->execute();

            $ret = (int) $this->pdo->lastInsertId();

            $this->release();

            return $ret;
            
        } catch (\Throwable $th) {
            //throw $th;
            if($this->is_connect) $this->release();
            throw new \Exception($th->getMessage());
        }
        
    }

    public function insert(array $data = [])
    {
        return $this->create($data);
    }

    /**
     * 更新
     *
     * @author pingo
     * @created_at 00-00-00
     * @param array $data
     * @return void
     */
    public function update(array $data = [])
    {
        try {
            if(empty($data)) throw new \Exception("新增数据不能为空");
            if($this->timestamps) $data[self::UPDATED_AT] = time();
            $data = $this->_casts($data, 1);
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
                
                //转换格式
                $this->_result = $this->_casts($this->_result, 0);
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
                
                //转换格式
                foreach ($this->_result as $key => &$row) {
                    # code...
                    $row = $this->_casts($row, 0);
                }
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


    public function first()
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
            if($this->_result){

                 //关联查询
                if($this->with){
                    foreach ($this->with as $key => $method) {
                        # code...
                        if(is_callable($method)) $method = $key;
                        
                        if(\method_exists($this, $method)){
                            $this->{$method}();
                        }
                    }
                }
                //转换格式
                $this->_result = $this->_casts($this->_result, 0);
                $this->_result = $this->_appends($this->_result);
            }
            
            $this->release();
            
            return $this->_result;
        } catch (\Throwable $th) {
            //throw $th;
            if($this->is_connect) $this->release();
            throw new \Exception($th->getMessage());
        }

    }

    public function get()
    {
        try {
            //code...
            $this->realGetConn();
            
            $sql = $this->builder->toSQL(true);
            $this->_sql[] = $sql;
            $statement = $this->pdo->prepare($sql);
            //$this->bindValues($statement, $bindings);
            $statement->execute();
            
            $this->_result = $statement->fetchAll();
            if($this->_result){
                 //关联查询
                if($this->with){
                    foreach ($this->with as $key => $method) {
                        # code...
                        if(is_callable($method)) $method = $key;
                        
                        if(\method_exists($this, $method)){
                            $this->{$method}();
                        }
                    }
                }
                //转换格式
                foreach ($this->_result as $key => &$row) {
                    # code...
                    $row = $this->_appends($row);
                    $row = $this->_casts($row, 0);
                }

            }
            
            $this->release();
            
            return $this->_result;
        } catch (\Throwable $th) {
            //throw $th;
            if($this->is_connect) $this->release();
            throw new \Exception($th->getMessage());
        }
    }

    public function with(array $relations = [])
    {
        $this->with = $relations;
        return $this;
    }

    /**
     * 数据项追加处理
     *
     * @author pingo
     * @created_at 00-00-00
     * @param array $data
     * @return void
     */
    public function _appends(array $data = [])
    {
        if($data && $this->appends){
            foreach ($this->appends as $name) {
                # code...
                $get_method_name =  "get" . \ucfirst(line_tohump($name)) . "Attribute";
                if( \method_exists($this, $get_method_name) ) {
                    $default_val = $data[$name]?? null;
                    $data[$name] = \call_user_func_array([$this, $get_method_name], [$default_val, $data]);
                } 
            }
        }
        return $data;
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
     * 一对一
     *
     * @author pingo
     * @created_at 00-00-00
     * @param [type] $relationClass
     * @param [type] $foreign_key
     * @param [type] $local_key
     * @return boolean
     */
    protected function hasOne($relationClass, $foreign_key, $local_key, $relation_name = '')
    {   
        
        $relationClass = new $relationClass;
        $table = $relationClass->table;
        $builder = (new Builder)->table($table);
        if(isset($this->with[$table]) && is_callable($this->with[$table])){
            $call = $this->with[$table];
            $call($builder);
        }
        //关联条件
        
        $local_keys = [];
        if(is_assoc_array($this->_result)){
            if(isset($this->_result[$local_key])) $local_keys[] = $this->_result[$local_key];
        }else{
            foreach ($this->_result as $key => $row) {
                # code...
                if(isset($row[$local_key])) $local_keys[] = $row[$local_key];
            }
        }
        $builder->whereIn($foreign_key, $local_keys);
        $sql = $builder->toSQL(true);
        $this->_sql[] = $sql;
        $statement = $this->pdo->prepare($sql);
        //$this->bindValues($statement, $bindings);
        $statement->execute();
        $result = $statement->fetchAll();
        
        $relation_name = $relation_name ?? $table;
        if(is_assoc_array($this->_result)){
            $this->_result[$relation_name] = [];
            if($result){
                $result = $relationClass->_appends($result[0]);
                $this->_result[$relation_name] = $relationClass->_casts($result);
            }
        }else{
            
            if($result) $result = $this->_array_columns($result, null, $foreign_key);
            
            foreach ($this->_result as $key => &$row) {
                # code...
                if($result && isset($result[$row[$local_key]])){
                    $item = array_shift($result[$row[$local_key]]);
                    if($item){
                        $item = $relationClass->_appends($item);
                        $row[$relation_name] =  $relationClass->_casts($item);
                    }else{
                        $row[$relation_name] =  null;
                    }
                }else{
                    $row[$relation_name] =  null;
                }
                 
            }
        }
         
    }
    
    /**
     * 一对多
     *
     * @author pingo
     * @created_at 00-00-00
     * @param [type] $relationClass
     * @param [type] $foreign_key
     * @param [type] $local_key
     * @return boolean
     */
    protected function hasMany($relationClass, $foreign_key, $local_key)
    {   
        $relationClass = new $relationClass;
        $table = $relationClass->table;
        $builder = (new Builder)->table($table);
        if(isset($this->with[$table]) && is_callable($this->with[$table])){
            $call = $this->with[$table];
            $call($builder);
        }
        //关联条件
        
        $local_keys = [];
        if(is_assoc_array($this->_result)){
            if(isset($this->_result[$local_key])) $local_keys[] = $this->_result[$local_key];
        }else{
            foreach ($this->_result as $key => $row) {
                # code...
                if(isset($row[$local_key])) $local_keys[] = $row[$local_key];
            }
        }
        $builder->whereIn($foreign_key, $local_keys);
        $sql = $builder->toSQL(true);
        $this->_sql[] = $sql;
        $statement = $this->pdo->prepare($sql);
        //$this->bindValues($statement, $bindings);
        $statement->execute();
        $result = $statement->fetchAll();

        $relation_name = $relation_name ?? $table;
        if(is_assoc_array($this->_result)){
            $this->_result[$relation_name] =  [];
            if($result){
                foreach ($result as $key => $row) {
                    # code...
                    $row = $relationClass->_appends($row);
                    $this->_result[$relation_name][] = $relationClass->_casts($row);
                }
            }
        }else{
            if($result) $result = $this->_array_columns($result, null, $foreign_key);
            foreach ($this->_result as $key => &$row) {
                # code...
                if($result && isset($result[$row[$local_key]])){
                    foreach ($result[$row[$local_key]] as $key => $item) {
                        # code...
                        $item = $relationClass->_appends($item);
                        $row[$relation_name][]  = $relationClass->_casts($item);
                    }
                }else{
                    if(!isset($row[$relation_name])) $row[$relation_name] =  [];
                }
                 
            }
        }
    }

    /**
     * 反向  一对一 一对多
     *
     * @author pingo
     * @created_at 00-00-00
     * @param [type] $relationClass
     * @param [type] $foreign_key
     * @param [type] $other_key
     * @return void
     */
    protected function belongsTo($relationClass, $foreign_key, $other_key = '', $relation_name = '')
    {   
        //return $this->belongsTo('App\Models\User', 'foreign_key', 'other_key');
        $relationClass = new $relationClass;
        $table = $relationClass->table;
        $builder = (new Builder)->table($table);
        if(isset($this->with[$table]) && is_callable($this->with[$table])){
            $call = $this->with[$table];
            $call($builder);
        }
        //关联条件
        
        $local_keys = [];
        if(is_assoc_array($this->_result)){
            if(isset($this->_result[$foreign_key])) $local_keys[] = $this->_result[$foreign_key];
        }else{
            foreach ($this->_result as $key => $row) {
                # code...
                if(isset($row[$foreign_key])) $local_keys[] = $row[$foreign_key];
            }
        }
        $other_key = empty($other_key)? $relationClass->primaryKey : $other_key;
        $builder->whereIn($other_key, $local_keys);
        $sql = $builder->toSQL(true);
        $this->_sql[] = $sql;
        $statement = $this->pdo->prepare($sql);
        //$this->bindValues($statement, $bindings);
        $statement->execute();
        $result = $statement->fetchAll();
        
        $relation_name = empty($relation_name) ? $table : $relation_name;
        if(is_assoc_array($this->_result)){
            $this->_result[$relation_name] = [];
            if($result){
                $result = $relationClass->_appends($result[0]);
                $this->_result[$relation_name] = $relationClass->_casts($result);
            }
        }else{
            
            if($result) $result = $this->_array_columns($result, null, $other_key);
            
            foreach ($this->_result as $key => &$row) {
                # code...
                if($result && isset($result[$row[$foreign_key]])){
                    $item = array_shift($result[$row[$foreign_key]]);
                    if($item){
                        $item = $relationClass->_appends($item);
                        $row[$relation_name] =  $relationClass->_casts($item);
                    }else{
                        $row[$relation_name] =  null;
                    }
                }else{
                    $row[$relation_name] =  null;
                }
                 
            }
        }

    }

    /**
     * 多对多
     *
     * @author pingo
     * @created_at 00-00-00
     * @param [type] $relationClass
     * @param [type] $piovtClass 中间表类
     * @param [type] $pivot_left_id  左表在中间表的外键ID
     * @param [type] $pivot_right_id 右表在中间表的外键ID
     * @return void
     */
    protected function belongsToMany($relationClass, $piovtClass, $pivot_left_id, $pivot_right_id, $relation_name)
    {   

        // 
        /* # 查询 ID 为 42115 的用户使用过的优惠券
        SELECT
            *
        FROM
            `z_user`
        WHERE
            `z_user`.`id` = 412115;

        LIMIT 1 
        
        SELECT
            `z_voucher`.*, `z_voucher_record`.`uid` AS `pivot_uid` ,
            `z_voucher_record`.`vid` AS `pivot_vid`
        FROM
            `z_voucher`
        INNER JOIN `z_voucher_record` ON `z_voucher`.`id` = `z_voucher_record`.`vid`
        WHERE
            `z_voucher_record`.`uid` = 412115;

        # 查询 ID 为 395 的优惠券被哪些用户使用过

        SELECT
            *
        FROM
            `z_voucher`
        WHERE
            `z_voucher`.`id` = 395;

        LIMIT 1 
        
        SELECT
            `z_user`.*, `z_voucher_record`.`vid` AS `pivot_vid` ,
            `z_voucher_record`.`uid` AS `pivot_uid`
        FROM
            `z_user`
        INNER JOIN `z_voucher_record` ON `z_user`.`id` = `z_voucher_record`.`uid` */
        $relationClass = new $relationClass;
        $piovtClass = new $piovtClass;
        $table = $relationClass->table;
        $builder = (new Builder)->table($table);
        if(isset($this->with[$table]) && is_callable($this->with[$table])){
            $call = $this->with[$table];
            $call($builder);
        }
        //关联条件
        
        $local_keys = [];
        if(is_assoc_array($this->_result)){
            if(isset($this->_result[$this->primaryKey])) $local_keys[] = $this->_result[$this->primaryKey];
        }else{
            foreach ($this->_result as $key => $row) {
                # code...
                if(isset($row[$this->primaryKey])) $local_keys[] = $row[$this->primaryKey];
            }
        }
        $builder->rightJoin($piovtClass->table, "{$table}.{$relationClass->primaryKey}", '=', "{$piovtClass->table}.{$pivot_right_id}")->whereIn("{$piovtClass->table}.{$pivot_left_id}", $local_keys);
        $sql = $builder->toSQL(true);
        $this->_sql[] = $sql;
        $statement = $this->pdo->prepare($sql);
        //$this->bindValues($statement, $bindings);
        $statement->execute();
        $result = $statement->fetchAll();
        
        if($result){
            $result = $this->_array_columns($result, null, $pivot_left_id);
            foreach ($this->_result as $key => &$row) {
                # code...
                if(isset($row[$this->primaryKey]) && isset($result[$row[$this->primaryKey]])){
                    $relation_item = array_values($result[$row[$this->primaryKey]]);
                    if(empty($relation_item)) continue;
                    foreach ($relation_item as $key => $relation) {
                        # code...
                        $relation = $relationClass->_appends($relation);
                        $row[$relation_name][] = $relationClass->_casts($relation);
                    }
                     
                }
            }
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

    
    public function __call($method, $arguments)
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

    /**
     * 字段属性获取器
     *
     * @author pingo
     * @created_at 00-00-00
     * @param string $name
     * @return void
     */
    public function __get(string $name)
    {
        if(isset($this->attributes[$name])){
            $value = $this->attributes[$name];
            //是否有获取器
            $get_method_name =  "get" . \ucfirst(line_tohump($name)) . "Attribute";
            if( \method_exists($this, $get_method_name) )  $value = \call_user_func_array([$this, $get_method_name], [$value, $this->attributes]);
            return $value;
        }
        return null;
    }

    /**
     * 字段属性设置
     *
     * @author pingo
     * @created_at 00-00-00
     * @param string $name
     * @param [type] $value
     */
    public function __set(string $name, $value)
    {
        //是否有修改器
        $set_method_name =  "set" . \ucfirst(line_tohump($name)) . "Attribute";
        if( \method_exists($this, $set_method_name) )  $value = \call_user_func([$this, $set_method_name], $value);
        $this->attributes[$name] = $value;
    }


}
