<?php

return [
    //swoole服务配置信息
    'Server'        => [
        // swoole服务ip
        'swoole_server_ip'          => '192.168.1.113',
        // swoole服务http监听端口
        'swoole_http_server_port'   => '9501',
        // swoole服务socket监听端口
        'swoole_socket_server_port' => '9502',
        //redis服务使用ip
        'redis_ip'                  => '127.0.0.1',
        //redis服务监听端口
        'redis_port'                => '6379',
    ],
    //swoole配置参数
    'SwooleConfig'  => [
        'log_file' => './log/swoole.log',   //指定swoole错误日志文件
        //'daemonize' => 1    //设置进程为守护进程，当使用systemd管理的时候，一定不要设置此参数，或者设置为0
    ],
    //定义文件类型和Content-Type对应关系
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
    //定义可下载文件
    'DownloadType'  => [
        'xls'   => 'application/x-xls,application/vnd.ms-excel',
        'tgz'   => '',
        'zip'   => '',
    ],
    //定义允许跨域请求的域名
    'AccessArr'     => [
        'http://localhost:8080',
    ]
];
