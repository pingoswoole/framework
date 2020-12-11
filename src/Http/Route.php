<?php

declare(strict_types=1);
 
namespace Pingo\Http;

use FastRoute\Dispatcher;
use RuntimeException;
use function FastRoute\simpleDispatcher;
use Pingo\Config\Config;
use Pingo\Swoole\Manager;

class Route
{
    private static $instance;

    private static $config;

    private static $dispatcher = null;

    private static $controller_namespace = "";

    private function __construct()
    {
        self::$controller_namespace = Config::getInstance()->get("app.controller_namespace");
    }

    public static function getInstance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
            self::loadRoute();
            self::$dispatcher = simpleDispatcher(
                function (\FastRoute\RouteCollector $routerCollector) {
                    foreach (self::$config as $key => $routerDefine) {
                        if(!isset($routerDefine['namespace']) || !isset($routerDefine['list'])) continue;
                        $route_list = $routerDefine['list'];
                        $routerCollector->addGroup($routerDefine['namespace'], function (\FastRoute\RouteCollector $r) use($route_list) {
                            foreach ($route_list as $route) {
                                # code...
                                if(count($route) !== 3) continue;
                                $r->addRoute($route[0], $route[1], $route[2]);
                            }
                        });
                        
                    }
                }
            );
        }
        return self::$instance;
    }
 
     /**
     * @param $request
     * @param $response
     * @throws \Exception
     * @return mixed|void
     */
    public function dispatch(\Swoole\Http\Request $request, \Swoole\Http\Response $response)
    {
        $method = $request->server['request_method'] ?? 'GET';
        $uri = $request->server['request_uri'] ?? '/';
        $routeInfo = self::$dispatcher->dispatch($method, $uri);
         
        switch ($routeInfo[0]) {
            case Dispatcher::NOT_FOUND:
                return $this->defaultRouter($request, $response, $uri, "controllerNotFound");
            case Dispatcher::METHOD_NOT_ALLOWED:
                return $this->defaultRouter($request, $response, $uri, "requestMethodForbid");
            case Dispatcher::FOUND:
                $handler = $routeInfo[1];
                $vars    = $routeInfo[2];//路由参数 /api/member{id:\d}  [id=1]
                if (is_string($handler)) {
                    $handler = explode('@', $handler);
                    if (count($handler) !== 2) {
                        throw new RuntimeException("Route {$uri} config error, Only @ are supported");
                    }

                    $className = self::$controller_namespace . $handler[0];
                    $func      = $handler[1];

                    if (! class_exists($className)) {
                        throw new RuntimeException("Route {$uri} defined '{$className}' Class Not Found");
                    }
                     
                    $Request = new Request($request, $vars);
                    $Response = new Response($response);
                    $controller = new $className($Request, $Response, Manager::getInstance()->getSwooleServer());

                    if (! method_exists($controller, $func)) {
                        //throw new RuntimeException("Route {$uri} defined '{$func}' Method Not Found");
                        return $this->defaultRouter($request, $response, $uri, "methodNotFound");
                    }
                    
                    try {
                        //code...
                        $RefClass = new \ReflectionClass($className);
                        $public_methods = $RefClass->getMethods(\ReflectionMethod::IS_PUBLIC);
                        $allow_methods = [];
                        foreach ($public_methods as $item){
                            array_push($allow_methods,$item->getName());
                        }
                        if(in_array($func,$allow_methods)){
                            $RefClassObj = $RefClass->newInstanceArgs([$Request, $Response, Manager::getInstance()->getSwooleServer()]);
                            $befor_method = "onRequest";
                            if($RefClass->hasMethod($befor_method)){
                                $befor_method_handler = $RefClass->getMethod($befor_method);
                                $befor_method_handler->setAccessible(true);
                                $before_res = $befor_method_handler->invokeArgs($RefClassObj, [$func]);
                                if(false === $before_res) return;
                                $action_handler = $RefClass->getMethod($func);
                                $action_handler->invokeArgs($RefClassObj, [$Request, $Response, $vars]);
                            }
                            //
                        }else{
                            return $this->defaultRouter($request, $response, $uri, "methodNotFound");
                        }
                    } catch (\Throwable $th) {
                        //throw $th;
                        return $this->defaultRouter($request, $response, $uri, "controllerNotFound");
                    }
                    
                    return ;
                    //
                    //去掉中间件、 
                    /* $middlewareHandler = function ($Request, $Response, $vars) use ($controller, $func) {
                        return $controller->{$func}($Request, $Response, $vars ?? null);
                    };

                    $middleware_key = 'middleware';
                    $reflectionClass = new \ReflectionClass ( $className );
                    if($reflectionClass->hasProperty($middleware_key)){
                        $reflectionProperty = $reflectionClass->getProperty($middleware_key);
                        $reflectionProperty->setAccessible(true);
                        $middleware_val = $reflectionProperty->getValue($controller);
                        if(!empty($middleware_val)){
                            $classMiddlewares = $controller->{$middleware_key}['__construct'] ?? [];
                            $methodMiddlewares = $controller->{$middleware_key}[$func] ?? [];
                            $middlewares = array_merge($classMiddlewares, $methodMiddlewares);
                            if ($middlewares) {
                                $middlewareHandler = $this->packMiddleware($middlewareHandler, array_reverse($middlewares));
                            }
                        }
                    }
                   
                    return $middlewareHandler($Request, $Response, $vars ?? null); */
                }

                if (is_callable($handler)) {
                    return call_user_func_array($handler, [$request, $response, $vars ?? null]);
                }

                throw new RuntimeException("Route {$uri} config error");
            default:
                return $this->defaultRouter($request, $response, $uri, "unedfined");
        }
    }

    /**
     * @param $request
     * @param $response
     * @param $uri
     */
    public function defaultRouter($request, $response, $uri, $method = "unedfined")
    {
        $uri = trim($uri, '/');
        $uri = explode('/', $uri);
        
        $className = '\\App\\Http\\Controllers\\DefaultController';
        if (class_exists($className) && method_exists($className, $method)) {
            return (new $className())->{$method}($request, $response, $uri, $method);
        }

        $response->withStatus(404);
        return $response->end("404");
    }

    /**
     * @param $handler
     * @param array $middlewares
     * @return mixed
     */
    public function packMiddleware($handler, $middlewares = [])
    {
        foreach ($middlewares as $middleware) {
            $handler = $middleware($handler);
        }
        return $handler;
    }

    /**
     * 加载配置目录文件
     *
     * @author pingo
     * @created_at 00-00-00
     * @param [type] $path
     * @return void
     */
    private static function loadRoute()
    {
        $path = WEB_ROUTE_PATH;
        $dirFiles = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path), \RecursiveIteratorIterator::LEAVES_ONLY);
        foreach($dirFiles as $FileIterator)
        {
            if($FileIterator->isFile()){
                $key = substr($FileIterator->getFileName(), 0, strpos($FileIterator->getFileName(), "."));
                self::$config[$key] = include $FileIterator->getPath() . DIRECTORY_SEPARATOR . $FileIterator->getFileName();
            }
        }
    }

}
