<?php
 

namespace Pingo\Http;
 
use \Swoole\Http\Request as SwRequest;
use \Swoole\Http\Response as SwResponse;

class Request
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

    protected $swoole_request;
    /**
     * @var Router
     */
    protected $router;

    /**
     * @var Response
     */
    protected $response;


    public function bootstrap()
    {
        $this->_get     = [];
        $this->_post    = [];
        $this->_cookies = [];
        $this->_files   = [];
        $this->_raw     = [];
        $this->_method  = [];
        $this->_headers = [];
        $this->_session = [];
        $this->_server  = null;
    }

    /**
     * @param SwRequest  $request
     */
    public function __construct(SwRequest $request)
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
        $this->swoole_request = $request;
        
    }

    public function getSwooleRequest()
    {
        return $this->swoole_request;
    }
    
    private function _filterParams($data, $filter)
    {
        //
        return $data;
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
        if(empty($key)) return $default;
        if(!is_array($key)) $key = [$key];
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
        if (is_null($key)) {
            return is_null($default) ?: $default;
        }
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
     * @param array $method
     */
    public function setMethod($method)
    {
        $this->_method = $method;
    }

    /**
     * @return array
     */
    public function getHeaders()
    {
        return $this->_headers;
    }

    /**
     * @param array $headers
     */
    public function setHeaders($headers)
    {
        $this->_headers = $headers;
    }

    /**
     * @return array
     */
    public function getSession()
    {
        return $this->_session;
    }

    /**
     * @param array $session
     */
    public function setSession($session)
    {
        $this->_session = $session;
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

    /**
     * @return Router
     */
    public function getRouter()
    {
        return $this->router;
    }

    /**
     * @param Router $router
     */
    public function setRouter($router)
    {
        $this->router = $router;
    }
}




















