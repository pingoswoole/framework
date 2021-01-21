<?php
namespace Pingo\Database\Exception;


class QueryException extends \Exception
{
     
    //查询条件语法错误
    const  WHERE_ERROR = 1;
    //字段错误
    const  FIELD_ERROR = 2;
    //查询条件内容为空
    const  WHERE_IS_EMPTY = 3;

    //添加数据为空
    const INSERT_EMPTY = 4;
    //错误sql语句
    private $sql = '';

    /**
     * Raw error info.
     *
     * @var array
     */
    public $raw;

    /**
     * Bootstrap.
     *
     * @author yansongda <me@yansonga.cn>
     *
     * @param string       $message
     * @param array|string $raw
     * @param int|string   $code
     */
    public function __construct($message = '', $code = self::WHERE_ERROR, string $sql = '')
    {
        $message = '' === $message ? 'Unknown Error' : $message;
        $this->sql = $sql;
        parent::__construct($message, intval($code));
    }

    public function __toString()
    {
        return __CLASS__ . ':[' .  $this->code . ']:' . $this->message . "[error_sql:{$this->sql}]" . '\n';
    }

    public function getSql()
    {
        return $this->sql;
    }

}
