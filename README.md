# Yii2AdvancedWebsocket
这是一个在centos7上，集成swoole+yii2 advanced的项目。同时运行了swoole的http服务和socket服务  
项目环境：php7+swoole2.0+yii2 advanced，在使用此项目前请确保php已经加载了swoole扩展。  
该项目和一般的yii2 advanced项目是一样的，只是多了2个php文件：YiiWebSocket.php和ServerConfig.php,然后修改了/vendor/yiisoft/yii2/web/Request.php中的一个方法    
下面为大家说明一下这3个文件  

# YiiWebSocket.php
**YiiWebSocket.php**位于根目录下，这是启动swoole服务的php脚本文件。您可以根据需要，将该文件放在任何地方但是需要修改相关配置参数。在yii2中，默认有2个站点（frontend和backend），这里我使用的是frontend为例。下面讲解下，该文件的重要代码。
1. 项目目录定义
```
//定义yii2的运行模块
define('THIS_APP','frontend');
//根据实际情况，定义站点根目录。
define('ROOT_DIR', '/root/advanced/frontend/web');
//定义yii2 advanced的根目录，便于引用
define('YII_ROOT','/root/advanced');
```
**THIS_APP**：默认的yii2 advanced有2个模块（fronted和backend），在这里定义您需要使用的哪个模块。  
**ROOT_DIR**：不像传统的web服务器，使用swoole运行项目的时候没有站点目录这样明确的定义。您可以通过引用，使用机器上任何地方的文件。所以在这里我使用    **ROOT_DIR**来定义站点目录，是为了便于清晰的引用相关资源文件。在这里我使用/root/advanced/frontend/web作为站点根目录，我们所有的资源文件（js、css、图片等），都应当放到该目录下或者其子目录下，这样我们才能正常的引用。    
**YII_ROOT**：由于我们使用yii2作为php的运行框架，而我们的swoole脚本可以放在任何地方。为了便于应用yii2的相关逻辑，所以在这里使用**YII_ROOT**定义了yii2项目的根目录，避免批量的修改代码。这里我使用/root/advanced/作为yii2的根目录，这样才能正常引用相关的逻辑和配置文件。
**若您的目录结构和我的不一样，请正确配置该参数**


2. 获取运行YiiWebSocket.php所需要的配置信息
```
//获取配置信息。
if(empty(self::$config)){
    self::$config = include(YII_ROOT . '/frontend/config/ServerConfig.php');
}
```
我将运行swoole服务所需要的所有配置信息都集中到了ServerConfig.php文件里面，并和yii2的配置文件放到了同一目录下。  

**其余代码，我都写上了相关注释。我就不再说明了**

# ServerConfig.php 
**ServerConfig.php** 位于/frontend/config/目录下。这里是运行swoole所需要的所有配置信息。
1. Server参数定义了swoole的服务信息（运行ip、端口号）
```
'Server'        => [
        // swoole服务ip
        'swoole_server_ip'          => '192.168.1.113',
        // swoole服务http监听端口
        'swoole_http_server_port'   => '9501',
        // swoole服务socket监听端口
        'swoole_socket_server_port' => '9502',
    ],
```
2. SwooleConfig参数是swoole的配置选项，可以参见官方文档（[https://wiki.swoole.com/wiki/page/276.html](https://wiki.swoole.com/wiki/page/276.html "官方文档")）进行设置  
```
//配置选项（https://wiki.swoole.com/wiki/page/276.html）
private static $server_config = array(
                'log_file' => './log/swoole.log',   //指定swoole错误日志文件
				//'daemonize' => 1    //设置进程为守护进程，当使用systemd管理的时候，一定不要设置此参数，或者设置为0
                );
```  
这里需要说明一点 daemonize 参数是设置swoole是否为守护进程。在我使用systemctl进行管理swoole的时候，我发现daemonize为真的时候，会无法运行。

3. ContentType参数定义了文件类型与Content-Type的对应关系。用于根据访问文件类型，设置response的Content-Type。可以根据自己的需要进行增删
```
'ContentType'   => [
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
    ],
```
4. DownloadType参数定义了哪些文件可以下载。可以根据自己的需要进行增删
```
'DownloadType'  => [
        'xls'   => 'application/x-xls,application/vnd.ms-excel',
        'tgz'   => '',
        'zip'   => '',
    ],
```  
5. AccessArr参数定义了运行哪些站点的跨域请求
```
'AccessArr'     => [
        'http://localhost:8080',
    ]
```

# 修改yii框架源码  
修改 \vendor\yiisoft\yii2\web 目录下的Request.php中的getScriptUrl方法，如下
```
public function getScriptUrl()
    {
        if ($this->_scriptUrl === null) {
            $this->_scriptUrl = '';
            /*
            $scriptFile = $this->getScriptFile();
            $scriptName = basename($scriptFile);file_put_contents('1111.txt',$_SERVER['SCRIPT_NAME'].'--'.$scriptName);
            if (isset($_SERVER['SCRIPT_NAME']) && basename($_SERVER['SCRIPT_NAME']) === $scriptName) {
                $this->_scriptUrl = $_SERVER['SCRIPT_NAME'];
            } elseif (isset($_SERVER['PHP_SELF']) && basename($_SERVER['PHP_SELF']) === $scriptName) {
                $this->_scriptUrl = $_SERVER['PHP_SELF'];
            } elseif (isset($_SERVER['ORIG_SCRIPT_NAME']) && basename($_SERVER['ORIG_SCRIPT_NAME']) === $scriptName) {
                $this->_scriptUrl = $_SERVER['ORIG_SCRIPT_NAME'];
            } elseif (isset($_SERVER['PHP_SELF']) && ($pos = strpos($_SERVER['PHP_SELF'], '/' . $scriptName)) !== false) {
                $this->_scriptUrl = substr($_SERVER['SCRIPT_NAME'], 0, $pos) . '/' . $scriptName;
            } elseif (!empty($_SERVER['DOCUMENT_ROOT']) && strpos($scriptFile, $_SERVER['DOCUMENT_ROOT']) === 0) {
                $this->_scriptUrl = str_replace([$_SERVER['DOCUMENT_ROOT'], '\\'], ['', '/'], $scriptFile);
            } else {
                throw new InvalidConfigException('Unable to determine the entry script URL.');
            }*/
        }

        return $this->_scriptUrl;
    }
```

# 问题
1. 为什么我获取不了post的参数
因为post的数据被拦截了，请在控制器中申明并定义如下变量
```
public $enableCsrfValidation = false;
```
2. 为什么使用yii自带的账号注册失败了  
在使用yii提供的表单的时候，需要指明提交地址 'action' => ['site/signup']
```
<?php $form = ActiveForm::begin(['id' => 'form-signup','action' => ['site/signup']]); ?>

<?= $form->field($model, 'username')->textInput(['autofocus' => true]) ?>

<?= $form->field($model, 'email') ?>

<?= $form->field($model, 'password')->passwordInput() ?>

<div class="form-group">
    <?= Html::submitButton('Signup', ['class' => 'btn btn-primary', 'name' => 'signup-button']) ?>
</div>

<?php ActiveForm::end(); ?>
```
3. 为什么js、css资源问价无法加载
需要在YiiWebSocket.php文件中正确定义项目根目录
```
//根据实际情况，定义站点根目录。用于清晰脚本存放位置
define('ROOT_DIR', '/root/advanced/frontend/web');
//定义yii2 advanced的根目录，便于引用
define('YII_ROOT','/root/advanced');
```
所有对资源文件的引用都是以 **ROOT_DIR** 为根目录进行的

