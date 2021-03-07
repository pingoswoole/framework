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
            $with_route = config("app.with_route");
            if ($with_route) {
                self::loadRoute();
                self::$dispatcher = simpleDispatcher(
                    function (\FastRoute\RouteCollector $routerCollector) {
                        foreach (self::$config as $key => $routerDefine) {
                            if (!isset($routerDefine['namespace']) || !isset($routerDefine['list'])) {
                                continue;
                            }
                            $route_list = $routerDefine['list'];
                            $routerCollector->addGroup($routerDefine['namespace'], function (\FastRoute\RouteCollector $r) use ($route_list) {
                                foreach ($route_list as $route) {
                                    # code...
                                    if (count($route) !== 3) {
                                        continue;
                                    }
                                    $r->addRoute($route[0], $route[1], $route[2]);
                                }
                            });
                        }
                    }
                );
            }
        }
        return self::$instance;
    }
 
    /**
    * @param $request
    * @param $response
    * @throws \Exception
    * @return mixed|void
    */
    public function dispatch(\Pingo\Http\Request $request, \Pingo\Http\Response $response)
    {
        $request_server = $request->getServer();
        $method = $request_server['request_method'] ?? 'GET';
        $uri    = $request_server['request_uri'] ?? '/';

        $with_route = config("app.with_route");
        if (!$with_route) {
            //关闭路由
            
            $mvc = array_filter(explode("/", $uri));
            if (count($mvc) < 3) {
                return $this->defaultRouter($request, $response, $uri, "methodNotFound");
            }
            if (false !== strstr($uri, "?")) {
                $mvc = array_slice($mvc, 0, count($mvc) - 1);
            }
            $mvc = array_map(function ($item) {
                return ucfirst(line_tohump($item)) ;
            }, $mvc);

            $func =   lcfirst(array_pop($mvc));
            $mvc = array_values($mvc);
             
            //禁用Admin路由访问
            if ($mvc[0] === 'Admin') {
                throw new RuntimeException("Route {$uri} defined is not permit");
            }
            //是否为后端访问，转换模块别名
            $admin_route_alias = config('app.admin_route_alias');
            if ($admin_route_alias === lcfirst($mvc[0])) {
                $mvc[0] = 'Admin';
            }
            $className = self::$controller_namespace . '\\' . implode("\\", $mvc) . "Controller";
            if (! class_exists($className)) {
                throw new RuntimeException("Route {$uri} defined '{$className}' Class Not Found");
            }

            try {
                //code...
                $RefClass = new \ReflectionClass($className);
                $public_methods = $RefClass->getMethods(\ReflectionMethod::IS_PUBLIC);
                $allow_methods = [];
                foreach ($public_methods as $item) {
                    array_push($allow_methods, $item->getName());
                }
                if (in_array($func, $allow_methods)) {
                    $RefClassObj = $RefClass->newInstanceArgs([$request, $response, Manager::getInstance()->getSwooleServer()]);
                    $befor_method = "onRequest";
                    if ($RefClass->hasMethod($befor_method)) {
                        $befor_method_handler = $RefClass->getMethod($befor_method);
                        $befor_method_handler->setAccessible(true);
                        $before_res = $befor_method_handler->invokeArgs($RefClassObj, [$func]);
                        if (false === $before_res) {
                            return;
                        }
                        $action_handler = $RefClass->getMethod($func);
                        $action_handler->invokeArgs($RefClassObj, [$request, $response, []]);
                    }
                    //
                } else {
                    return $this->defaultRouter($request, $response, $uri, "methodNotFound");
                }
            } catch (\Throwable $th) {
                //throw $th;
            }
            
            return $this->defaultRouter($request, $response, $uri, "controllerNotFound");
        }

        $routeInfo = self::$dispatcher->dispatch($method, $uri);
         
        switch ($routeInfo[0]) {
            case Dispatcher::NOT_FOUND:
                return $this->defaultRouter($request, $response, $uri, "controllerNotFound");
            case Dispatcher::METHOD_NOT_ALLOWED:
                return $this->defaultRouter($request, $response, $uri, "requestMethodForbid");
            case Dispatcher::FOUND:
                $handler = $routeInfo[1];
                $vars    = $routeInfo[2];//路由参数 /api/member{id:\d}  [id=1]
                $request->setRouteParams($vars);

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
                    
                    try {
                        //code...
                        $RefClass = new \ReflectionClass($className);
                        $public_methods = $RefClass->getMethods(\ReflectionMethod::IS_PUBLIC);
                        $allow_methods = [];
                        foreach ($public_methods as $item) {
                            array_push($allow_methods, $item->getName());
                        }
                        if (in_array($func, $allow_methods)) {
                            $RefClassObj = $RefClass->newInstanceArgs([$request, $response, Manager::getInstance()->getSwooleServer()]);
                            $befor_method = "onRequest";
                            if ($RefClass->hasMethod($befor_method)) {
                                $befor_method_handler = $RefClass->getMethod($befor_method);
                                $befor_method_handler->setAccessible(true);
                                $before_res = $befor_method_handler->invokeArgs($RefClassObj, [$func]);
                                if (false === $before_res) {
                                    return;
                                }
                                $action_handler = $RefClass->getMethod($func);
                                $action_handler->invokeArgs($RefClassObj, [$request, $response, $vars]);
                            }
                            //
                        } else {
                            return $this->defaultRouter($request, $response, $uri, "methodNotFound");
                        }
                    } catch (\Throwable $th) {
                        //throw $th;
                        return $this->defaultRouter($request, $response, $uri, "controllerNotFound");
                    }
                    
                    return ;
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
        foreach ($dirFiles as $FileIterator) {
            if ($FileIterator->isFile()) {
                $key = substr($FileIterator->getFileName(), 0, strpos($FileIterator->getFileName(), "."));
                self::$config[$key] = include $FileIterator->getPath() . DIRECTORY_SEPARATOR . $FileIterator->getFileName();
            }
        }
    }
}
