<?php

namespace Pingo\Http;

use Pingo\Exceptions\HttpException;

abstract class Controller
{

    /**
     * @var Request
     */
    protected $request = null;

    /**
     * @var \One\Swoole\Response
     */
    protected $response = null;

    protected $swoole_request = null;

    protected $swoole_response = null;
    /**
     * @var \App\Server\AppHttpServer
     */
    protected $server;

    protected $request_server = [];

    protected $middleware = [];
    /**
     * Controller constructor.
     * @param $request
     * @param $response
     */
    public function __construct(Request $request, Response $response, $server = null)
    {
        $this->request  = $request;
        $this->response = $response;
        $this->swoole_request  = $request->getSwooleRequest();
        $this->swoole_response = $response->getSwooleResponse();
        $this->server   = $server;
        $this->request_server = $request->getServer();
        $this->initialize(); 
    }

    public function initialize()
    {
        
    }

    public function isGet()
    {
        return $this->request_server['request_method'] === 'GET' ? true : false;
    }

    public function isPost()
    {
        return $this->request_server['request_method'] === 'POST' ? true : false;
    }

    /**
     * write
     *
     * @author pingo
     * @created_at 00-00-00
     * @param [type] $data
     * @return void
     */
    public function write($data)
    {
        if(!is_string($data)) $data = json_encode($data);
        $this->response->write($data);
    }

    public function writeJson(array $data)
    {
        if(!is_string($data)) $data = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $this->response->header('Content-type', 'application/json');
        $this->response->write($data);
    }
    /**
     * 异常处理
     * @param $msg
     * @param int $code
     * @throws HttpException
     */
    protected function error($msg, $code = 400)
    {
       // throw new HttpException($this->response, $msg, $code);
    }

   

    /**
     * @param $data
     * @param string $callback
     * @return string
     */
    protected function jsonP($data, $callback = 'callback')
    {
        return $this->response->json($data, 0, $callback);
    }

    /**
     * 检查必填字段
     * @param array $fields
     * @param array $data
     * @throws HttpException
     */
    protected function verify($fields, $data)
    {
        foreach ($fields as $v) {
            $val = array_get($data, $v);
            if ($val === null || $val === '') {
                $this->error("{$v}不能为空");
            }
        }
    }


}