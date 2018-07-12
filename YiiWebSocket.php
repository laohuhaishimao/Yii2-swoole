<?php
/**
 * websocket服务器swoole入口脚本(兼容websocket和http响应)
 * @author      老虎还是猫
 * @property    ROOT_DIR        根据实际情况，定义站点根目录。用于清晰脚本存放位置(比如本项目的站点根目录为/root/advanced/frontend/web)
 * @property    YII_ROOT        定义yii2 advanced的根目录，便于引用
 * @date        2018/06/21
 */ 
 
define('THIS_APP','frontend');
//根据实际情况，定义站点根目录。
define('ROOT_DIR', '/root/advanced/'.THIS_APP.'/web');
//定义yii2 advanced的根目录，便于引用
define('YII_ROOT','/root/advanced');

defined('YII_DEBUG') or define('YII_DEBUG', true);
defined('YII_ENV') or define('YII_ENV', 'dev');

require YII_ROOT . '/vendor/autoload.php';
require YII_ROOT . '/vendor/yiisoft/yii2/Yii.php';
require YII_ROOT . '/common/config/bootstrap.php';
require YII_ROOT . '/'.THIS_APP.'/config/bootstrap.php';

class YiiWebSocket{
    
    //声明配置信息
    private static $config = null;
    
    //yii2配置信息
    private static $yiiconfig = null;
    
    //swoole的web soccket server对象
    private static $web_socket = null;
    
    //入口方法(外部唯一调用方法)
    public static function run(){
        
        //获取配置信息。
        if(empty(self::$config)){
            self::$config = include(YII_ROOT . '/'.THIS_APP.'/config/ServerConfig.php');
        }
        
        //获取yii2配置信息。
        if(empty(self::$yiiconfig)){
            self::$yiiconfig = yii\helpers\ArrayHelper::merge(
                                require YII_ROOT . '/common/config/main.php',
                                require YII_ROOT . '/common/config/main-local.php',
                                require YII_ROOT . '/'.THIS_APP.'/config/main.php',
                                require YII_ROOT . '/'.THIS_APP.'/config/main-local.php'
                            );
        }
        
        if(empty(self::$web_socket)){
            self::$web_socket = new swoole_websocket_server(self::$config['Server']['swoole_server_ip'], self::$config['Server']['swoole_http_server_port']);
        }
        
        self::$web_socket->set(self::$config['SwooleConfig']);
        self::$web_socket->listen(self::$config['Server']['swoole_server_ip'], self::$config['Server']['swoole_socket_server_port'], SWOOLE_SOCK_TCP);
        
        //websocket通信建立时触发
        self::$web_socket->on('open', function (swoole_websocket_server $server, $request) {
            echo "server: handshake success with fd{$request->fd}\n";
        });
        
        //websocket client发送请求时触发
        self::$web_socket->on('message', function (swoole_websocket_server $server, $frame) {
            self::onMessage($server, $frame);
        });
        
        //websocket通信关闭时触发
        self::$web_socket->on('close', function ($ser, $fd) {
            //echo "client {$fd} closed\n";
        });
        
        //websocket继承httpserver，注册onRequest方法后可以响应http请求。否则报404
        self::$web_socket->on('request', function($request, $response){
            $return = self::onRequest($request, $response);
        });
        
        self::$web_socket->start();
    }
    
    
    //request方法，响应http请求
    private static function onRequest($request, $response){
        //阻止favicon.ico 进入
        if ($request->server['request_uri']=='/favicon.ico'){
            $response->header("Content-Type", "text/html; charset=utf-8");
            $response->status(404);
            $response->end();
            return ;
        }
        
        //动态设置$response的header
        $path_arr = pathinfo($request->server['request_uri']);
        if(!empty($path_arr['extension'])){
            
            //判断是否为脚本
            if(array_key_exists($path_arr['extension'],self::$config['ContentType'])){
                try{
                    $response->header("Content-Type", self::$config['ContentType'][$path_arr['extension']]);
                    $response->status(200);
                    $response->end(file_get_contents(ROOT_DIR . $request->server['request_uri']));
                }catch(\Exception $e){
                    $response->header("Content-Type", "text/html; charset=utf-8");
                    $response->status(404);
                    $response->end();
                    throw $e;
                }
                return;
            }
            //判断是否为下载文件
            if(array_key_exists($path_arr['extension'],self::$config['DownloadType'])){
                try{
                    $response->header("Content-Type", self::$config['DownloadType'][$path_arr['extension']]);
                    $response->sendfile(urldecode(ROOT_DIR . $request->server['request_uri']));
                }catch(\Exception $e){
                    $response->header("Content-Type", "text/html; charset=utf-8");
                    $response->status(404);
                    $response->end();
                    throw $e;
                }
                return;
            }
        }
        
        //设置跨域请求
        if(!empty($request->header['origin']) and in_array($request->header['origin'],self::$config['AccessArr'])){
            header('Access-Control-Allow-Origin:'.$request->server['remote_addr']);  
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Allow-Headers: TOKEN,Content-Type');
            header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        }
        
        //即时更新$_SERVER
        foreach ($request->server as $k => $v) {
            $_SERVER[strtoupper($k)] = $v;
        }
        foreach ($request->header as $k => $v) {
            $_SERVER[strtoupper($k)] = $v;
        }
        $_SERVER['SCRIPT_FILENAME'] = ROOT_DIR;
        $_SERVER['SCRIPT_NAME']     = '';
        //获取$_GET
        $_GET = [];
        if (isset($request->get)) {
            foreach ($request->get as $k => $v) {
                $_GET[$k] = $v;
            }
        }
        //获取$_POST
        $_POST = [];
        if (isset($request->post)) {
            foreach ($request->post as $k => $v) {
                $_POST[$k] = $v;
            }
        }
        
        //捕获异常，防止服务器持续等待
        try{              
            $res = self::YiiLogick();
            $response->header("Content-Type", "text/html; charset=utf-8");
            $response->end($res);
        }catch(Exception $e){
            throw $e;
            $response->header("Content-Type", "text/html; charset=utf-8");
            $response->status(500);
            $response->end();
        }
        return;
    }
    
    
    //websocket请求返回，使用第三方类库处理(\extend\)。上传信息使用json格式，其中route为路由
    private static function onMessage(swoole_websocket_server $server, $frame){
        try{
            //解析数据
            $received_data = json_decode($frame->data,1);
    
            //获取路由信息
            $route = $received_data['route'];
            if(empty($received_data)){
                $server->push($frame->fd, "Routing information must be required !!");
                return;
            }
            $routeArr = explode('/',$received_data['route']);
            $functionid = sizeof($routeArr)-1;
            $functionname = $routeArr[$functionid];
            unset($routeArr[$functionid]);
            $classname = implode('\\',$routeArr);
            
            $class = new $classname;
            $class->init($frame->fd,$received_data);
            $res = $class->$functionname();
            $server->push($frame->fd, $res);
        }catch(Exception $e){
            echo $e->getTraceAsString();
            $server->push($frame->fd, 'Websocket server error !!');
        }
        return;
    } 
    
    //使用thinkphp5，运行系统业务逻辑
    private static function YiiLogick(){
        // 执行应用
        ob_start();
        try {
            (new yii\web\Application(self::$yiiconfig))->run();
        } catch (\Exception $exception) {
            throw $exception;
            return;
        }
        $res = ob_get_contents();
        ob_end_clean();       
        
        return $res;
    }
}

YiiWebSocket::run();



