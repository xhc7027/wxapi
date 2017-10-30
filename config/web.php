<?php

//需要动态获取的配置
$dynamicConfig = [
    'id' => 'wxapi',//项目编号标识
    'basePath' => dirname(__DIR__),
];

//需要静态获取的配置
$staticConfig = Yaconf::get($dynamicConfig['id']);
if (!$staticConfig) {
    throw new Exception('不能加载配置文件:' . $dynamicConfig['id']);
}

$staticConfig['components']['db']['slaveConfig']['attributes'] = [PDO::ATTR_TIMEOUT => 10];
return array_merge($dynamicConfig, $staticConfig);