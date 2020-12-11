<?php
 

namespace Pingo\Http;

use Pingo\Http\Message\ServerRequest;
use Pingo\Http\Message\Stream;
use Pingo\Http\Message\UploadFile;
use Pingo\Http\Message\Uri;


use \Swoole\Http\Request as SwRequest;
use \Swoole\Http\Response as SwResponse;

class Request extends ServerRequest
{

    protected $_get = [];

    protected $_post = [];

    protected $_cookies = [];

    protected $_files = [];

    protected $_raw = "";

    protected $_method = [];

    protected $_headers = [];

    protected $_session = [];

    protected $_server = null;

    protected $clientIP;

    protected $serverPort;

    private $request;
    /**
     * @var Router
     */
    protected $router;

    /**
     * @var Response
     */
    protected $response;

    protected $route_params = [];

     
    /**
     * @param SwRequest  $request
     */
    public function __construct(SwRequest $request, $vars = [])
    {
        isset($request->headers) && $this->_headers = $request->header;
        isset($request->get) && $this->_get = $request->get;
        isset($request->post) && $this->_post = $request->post;
        isset($request->files) && $this->_files = $request->files;
        isset($request->cookie) && $this->_cookies = $request->cookie;
        $this->_raw    = $request->rawContent();
        $this->_server = $request->server;
        $this->clientIP = $request->server["remote_addr"];
        $this->serverPort = $request->server["server_port"];
        $this->route_params = $vars;
        $this->request = $request;
        $this->initHeaders();
        $protocol = str_replace('HTTP/', '', $request->server['server_protocol']) ;
        //为单元测试准备
        if($request->fd){
            $body = new Stream($request->rawContent());
        }else{
            $body = new Stream('');
        }
        $uri = $this->initUri();
        $files = $this->initFiles();
        $method = $request->server['request_method'];
        parent::__construct($method, $uri, null, $body, $protocol, $request->server);
        $this->withCookieParams($this->initCookie())->withQueryParams($this->initGet())->withParsedBody($this->initPost())->withUploadedFiles($files);
        
        
    }

    public function getSwooleRequest()
    {
        return $this->request;
    }
    
    private function _filterParams($data, $filter)
    {
        //
        return $data;
    }
    /**
     * 获取路由参数
     *
     * @author pingo
     * @created_at 00-00-00
     * @param [type] ...$param
     * @return void
     */
    public function route(...$param)
    {
        switch (count($param)) {
            case 0:
                # code...
                return $this->route_params;
                break;
            case 1:
                return $this->route_params[array_shift($param)]?? null;
                break;
            default:
                $data = [];
                foreach ($param as $key => $name) {
                    # code...
                    $data[$name] = $this->route_params[$name]?? null;
                }
                # code...
                return $data;
                break;
        }
    }
    /**
     * @param null $key
     * @param null $default
     * @param bool $filter
     * @return mixed
     */
    public function get($key = null, $default = null, $filter = null)
    {
        return $this->_fetchParmas('get', $key, $default, $filter);
    }

    
    /**
     * @param null $key
     * @param null $default
     * @param bool $filter
     * @return mixed
     */
    public function post($key = null, $default = null, $filter = null)
    {
        return $this->_fetchParmas('post', $key, $default, $filter);
    }

    /**
     * 获取get . post 的所有参数
     *
     * @return array
     */
    public function all()
    {
        return array_merge($this->get(), $this->post());
    }

    public function input($key = null, $default = null, $filter = null)
    {
        return $this->_fetchParmas('all', $key, $default, $filter);
    }

    public function query($key = null, $default = null, $filter = null)
    {   
        return $this->_fetchParmas('get', $key, $default, $filter);
    }

    public function _fetchParmas($method = 'get', $key = null, $default = null, $filter = null)
    {
        
        $data   = [];
        $result = [];
        switch ($method) {
            case 'get':
                # code...
                $data = $this->_get;
                break;
            case 'post':
                # code...
                $data = $this->_post;
                break;
            default:
                # code...
                $data = array_merge($this->_get, $this->_post);
                break;
        }
        if(empty($key)) return $data;
        if(!is_array($key)) $key = [$key];
        foreach ($key as $name) {
            # code...
            $result[$name] = $this->_filterParams($data[$name]?? $default, $filter);
        }
        return count($result) === 1 ? array_shift($result) : $result;
    }
    /**
     * @param null $key
     * @return mixed|null'
     */
    public function file($key = null)
    {
        if (is_null($key)) {
            return ($this->_files);
        }

        if (isset($this->_files [ $key ])) {
            return ($this->_files[ $key ]);
        }

        return null;
    }

    public function cookie($key = null, $default = null)
    {
        if (is_null($key)) {
            return ($this->_cookies);
        }

        if (isset($this->_cookies[ $key ])) {
            return ($this->_cookies[ $key ]);
        }
        return $default;
    }

    /**
     * @return array
     */
    public function getGet()
    {
        return $this->_get;
    }

    /**
     * @param array $get
     */
    public function setGet($get)
    {
        $this->_get = $get;
    }

    /**
     * @return array
     */
    public function getPost()
    {
        return $this->_post;
    }

    /**
     * @param array $post
     */
    public function setPost($post)
    {
        $this->_post = $post;
    }

    /**
     * @return array
     */
    public function getCookies()
    {
        return $this->_cookies;
    }

    /**
     * @param array $cookies
     */
    public function setCookies($cookies)
    {
        $this->_cookies = $cookies;
    }

    /**
     * @return array
     */
    public function getFiles()
    {
        return $this->_files;
    }

    /**
     * @param array $files
     */
    public function setFiles($files)
    {
        $this->_files = $files;
    }

    /**
     * @return string
     */
    public function getRaw()
    {
        return $this->_raw;
    }

    /**
     * @param string $raw
     */
    public function setRaw($raw)
    {
        $this->_raw = $raw;
    }

    /**
     * @return array
     */
    public function getMethod()
    {
        return $this->_method;
    }
 

    /**
     * @return null
     */
    public function getServer()
    {
        return $this->_server;
    }

    /**
     * @param null $server
     */
    public function setServer($server)
    {
        $this->_server = $server;
    }
 

    function getRequestParam(...$key)
    {
        $data = $this->getParsedBody() + $this->getQueryParams();
        if(empty($key)){
            return $data;
        }else{
            $res = [];
            foreach ($key as $item){
                $res[$item] = isset($data[$item])? $data[$item] : null;
            }
            if(count($key) == 1){
                return array_shift($res);
            }else{
                return $res;
            }
        }
    }


    private function initUri()
    {
        $uri = new Uri();
        $uri->withScheme("http");
        $uri->withPath($this->request->server['path_info']);
        $query = isset($this->request->server['query_string']) ? $this->request->server['query_string'] : '';
        $uri->withQuery($query);
        //host与port以header为准，防止经过proxy
        if(isset($this->request->header['host'])){
            $host = $this->request->header['host'];
            $host = explode(":",$host);
            $realHost = $host[0];
            $port = isset($host[1]) ? $host[1] : null;
        }else{
            $realHost = '127.0.0.1';
            $port = $this->request->server['server_port'];
        }
        $uri->withHost($realHost);
        $uri->withPort($port);
        return $uri;
    }

    private function initHeaders()
    {
        $headers = isset($this->request->header) ? $this->request->header :[];
        foreach ($headers as $header => $val){
            $this->withAddedHeader($header,$val);
        }
    }

    private function initFiles()
    {
        if(isset($this->request->files)){
            $normalized = array();
            foreach($this->request->files as $key => $value){
                //如果是二维数组文件
                if(is_array($value) && empty($value['tmp_name'])){
                    $normalized[$key] = [];
                    foreach($value as $file){
                        if (empty($file['tmp_name'])){
                            continue;
                        }
                        $file = $this->initFile($file);
                        if($file){
                            $normalized[$key][] = $file;
                        }
                    }
                    continue;
                }else{
                    $file = $this->initFile($value);
                    if($file){
                        $normalized[$key] = $file;
                    }
                }
            }
            return $normalized;
        }else{
            return array();
        }
    }

    private function initFile(array $file)
    {
        if(empty($file['tmp_name'])){
            return null;
        }
        return new UploadFile(
            $file['tmp_name'],
            (int) $file['size'],
            (int) $file['error'],
            $file['name'],
            $file['type']
        );
    }

    private function initCookie()
    {
        return isset($this->request->cookie) ? $this->request->cookie : [];
    }

    private function initPost()
    {
        return isset($this->request->post) ? $this->request->post : [];
    }

    private function initGet()
    {
        return isset($this->request->get) ? $this->request->get : [];
    }

    final public function __toString():string
    {
        return Utility::toString($this);
    }

    public function __destruct()
    {
        $this->getBody()->close();
    }

}




















