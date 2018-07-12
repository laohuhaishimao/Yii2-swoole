<?php
/**
 * websocket服务器swoole入口脚本(兼容websocket和http响应)
 * @author      老虎还是猫
 * @property    $ContentType    文件类型与Content-Type的对应关系
 * @property    $downloadType   定义可下载文件类型
 * @property    $config         http server的配置信息
 * @property    $server_config  swoole服务配置
 * @property    $web_socket     swoole实例
 * @date   2018/06/21
 */ 
 //根据实际情况，定义站点根目录
define('ROOT_DIR', '/root/advanced/frontend/web');
defined('YII_DEBUG') or define('YII_DEBUG', true);
defined('YII_ENV') or define('YII_ENV', 'dev');

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../vendor/yiisoft/yii2/Yii.php';
require __DIR__ . '/../common/config/bootstrap.php';
require __DIR__ . '/config/bootstrap.php';

class YiiWebSocket{
    
    //定义文件类型和Content-Type对应关系
    private static $ContentType = array(
                    'xml'   => 'application/xml,text/xml,application/x-xml',
                    'json'  => 'application/json,text/x-json,application/jsonrequest,text/json',
                    'js'    => 'text/javascript,application/javascript,application/x-javascript',
                    'css'   => 'text/css',
                    'rss'   => 'application/rss+xml',
                    'yaml'  => 'application/x-yaml,text/yaml',
                    'atom'  => 'application/atom+xml',
                    'pdf'   => 'application/pdf',
                    'text'  => 'text/plain',
                    //'image' => 'image/png,image/jpg,image/jpeg,image/pjpeg,image/gif,image/webp,image/*',
                    'png'   => 'image/png',
                    'jpg'   => 'image/jpg',
                    'jpeg'  => 'image/jpeg',
                    'pjpeg' => 'image/pjpeg',
                    'gif'   => 'image/gif',
                    'webp'  => 'image/webp',
                    'csv'   => 'text/csv',
                    'html'  => 'text/html,application/xhtml+xml,*/*',
                    );
                    
    //定义可下载文件
    private static $downloadType = array(
                    'xls'   => 'application/x-xls,application/vnd.ms-excel',
                    'tgz'   => '',
                    'zip'   => ''
                    );
                    
    //定义允许跨域请求的域名
    private static $accessArr = array(
                    'http://localhost:8080',
                    );
    
    //声明配置信息
    private static $config = null;
    
    //yii2配置信息
    private static $yiiconfig = null;
    
    //配置选项（https://wiki.swoole.com/wiki/page/276.html）
    private static $server_config = array(
                    'log_file' => './log/swoole.log',   //指定swoole错误日志文件
                    //'daemonize' => 1    //设置进程为守护进程，当使用systemd管理的时候，一定不要设置此参数，或者设置为0
                    );
    
    //swoole的web soccket server对象
    private static $web_socket = null;
    
    //入口方法(外部唯一调用方法)
    public static function run(){
        
        //获取配置信息。由于没有运行thinkphp的App逻辑，无法自动加载配置文件。所以需要手动引入
        if(empty(self::$config)){
            self::$config = include(__DIR__ . '/config/ServerConfig.php');
        }
        
        //获取yii2配置信息。
        if(empty(self::$yiiconfig)){
            self::$yiiconfig = yii\helpers\ArrayHelper::merge(
                                require __DIR__ . '/../common/config/main.php',
                                require __DIR__ . '/../common/config/main-local.php',
                                require __DIR__ . '/config/main.php',
                                require __DIR__ . '/config/main-local.php'
                            );
        }
        
        if(empty(self::$web_socket)){
            self::$web_socket = new swoole_websocket_server(self::$config['swoole_server_ip'], self::$config['swoole_http_server_port']);
        }
        
        self::$web_socket->set(self::$server_config);
        self::$web_socket->listen(self::$config['swoole_server_ip'], self::$config['swoole_socket_server_port'], SWOOLE_SOCK_TCP);
        
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
            if(array_key_exists($path_arr['extension'],self::$ContentType)){
                try{
                    $response->header("Content-Type", self::$ContentType[$path_arr['extension']]);
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
            if(array_key_exists($path_arr['extension'],self::$downloadType)){
                try{
                    $response->header("Content-Type", self::$downloacType[$path_arr['extension']]);
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
        if(!empty($request->header['origin'])and in_array($request->header['origin'],self::$accessArr)){
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
            echo $e->getTraceAsString();
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
            echo $exception->getMessage().' Error on line '.$exception->getLine().' in '.$exception->getFile();
        }
        $res = ob_get_contents();
        ob_end_clean();       
        
        return $res;
    }
}

YiiWebSocket::run();



