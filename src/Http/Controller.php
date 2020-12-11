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

    public function request()
    {
        return $this->request;
    }
    public function response()
    {
        return $this->response;
    }

    public function getSwooleServer()
    {
        return $this->server;
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
        $this->swoole_response->write($data);
    }

    public function writeJson(array $data)
    {
        if(!is_string($data)) $data = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $this->swoole_response->withHeader('Content-type', 'application/json');
        $this->swoole_response->write($data);
    }
    


}