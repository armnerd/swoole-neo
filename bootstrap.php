<?php
// 设置报错级别
ini_set('display_errors', 'on');
error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);

// 设置时区及运行时间
date_default_timezone_set('Asia/Shanghai');
define('TIMESTART', microtime());
define('TIMENOW', time());

// composer autoload
require_once __DIR__ . '/vendor/autoload.php';

// PHP原生Redis、PDO、MySQLi协程化的支持
Swoole\Runtime::enableCoroutine();

// 创建http服务
$http = new swoole_http_server("0.0.0.0", 9501);

// 设置启动参数
$http->set([
    'worker_num'       => 4,
    'task_worker_num'  => 10,
    'daemonize'        => 0,
    'enable_coroutine' => true,
]);

// 服务启动时
$http->on('start', function ($serv){
    Log::info("neo begin to work");
    Log::info("master pid is " . $serv->master_pid);
    Log::info("manager pid is " . $serv->manager_pid);
    file_put_contents(__DIR__."/runtime/pid", $serv->master_pid);
});

// worker启动时
$http->on('workerstart', function ($serv, $worker_id){
    Log::info("worker start and it's pid is " . $worker_id);
});

// worker关闭时
$http->on('workerstop', function ($serv, $worker_id){
    Log::info("worker stop and it's pid is " . $worker_id);
});

// 服务关闭时
$http->on('shutdown', function ($serv){
    Log::info("neo shutdow");
});

// 请求响应
$http->on('request', function ($request, $response) use ($http) {
    // 获取路径
    $path_info  = isset($request->server['path_info']) ?
                  strval($request->server['path_info']) : '/';
    // 浏览器的多余访问的过滤
    if($path_info === '/favicon.ico'){
        return true;
    }
    // 设置响应头部
    $response->header("Content-Type", "text/html; charset=utf-8");

    // 根路径提示
    if($path_info === '/'){
    	$whoami = "<h1>NeoSwoole</h1>";
        $response->end($whoami);
        return true;
    }
    
    // 匹配路由
    $matched = preg_match("#^/(\w+)/(\w+)/(\w+)$#", $path_info, $match) ? true : false;
    // 未匹配的返回
    if (!$matched) {
        return error($response, 'api not found');
    }
    // 若匹配，解析出控制器和方法
    $misson     = $match[1];
    $controller = $match[2];
    $action     = $match[3];

    // 请求数据
    $data = [
        'path'    => $path_info,
        'post'    => $request->post,
        'get'     => $request->get,
        'files'   => $request->files,
        'cookie'  => $request->cookie,
        'handler' => $controller,
        'action'  => $action,
    ];

    if ($misson === 'api') {
        $data['mission'] = 'api';
        try {
            return success($response, handler($controller, $action, $data));
        } catch (\Exception $e) {
            return error($response, $e->getMessage());
        }
    } elseif ($misson === 'task') {
        $data['mission'] = 'task';
        // 异步任务投递
        $http->task($data);
        return success($response, [], 'task send');
    } else {
        return error($response, 'whoops');
    }
    
});

// 任务开始
$http->on('task', function ($serv, $task_id, $from_id, $data) {
    echo "task start\n";

    $controller = $data['handler'];
    $action     = $data['action'];

    try {
        handler($controller, $action, $data);
    } catch (\Exception $e) {
        Log::error($e->getMessage());
    }
    return true;
});

// 任务完成
$http->on('finish', function ($serv, $task_id, $data) {
    echo "task completed\n";
});

// 启动服务
$http->start();

// 初始化
function handler($controller, $action, $data){
    // 检查类的存在
    $class_name = "App\Controllers\\" . $controller . "Controller";
    if (class_exists($class_name) === false) {
        throw new \Exception('class ' . $class_name . ' not exist');
    }

    // 实例化类
    $class = new $class_name($data);
    if($class === false){
        throw new \Exception('class ' . $class_name . ' new fail');
    }

    // 检查方法的存在
    if(method_exists($class, $action.'Action') === false){
        throw new \Exception('action ' . $action . ' not exist');
    }

    // 调用对应的方法
    $data = $class->{$action.'Action'}();
    return $data ?? [];
}

// 正确返回
function success($response, $data, $msg = ''){
	$res = [
        'code' => 200,
        'data' => $data,
        'msg'  => $msg,
    ];
    $response->end(json_encode($res));
    return true;
}

// 错误返回
function error($response, $msg){
	$res = [
        'code' => 500,
        'data' => [],
        'msg'  => $msg,
    ];
    $response->end(json_encode($res));
    return true;
}
