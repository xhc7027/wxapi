<?php

return [
    'class' => 'yii\db\Connection',
    //配置主数据库
    'dsn' => 'mysql:host=127.0.0.1;dbname=weixinapi;charset=UTF8',
    'username' => 'root',
    'password' => '1q2w3e4r5t',
    'charset' => 'utf8',
    //配置从数据库
    'slaveConfig' => [
        'username' => 'root',
        'password' => '1q2w3e4r5t',
        'attributes' => [
            PDO::ATTR_TIMEOUT => 10,
        ]
    ],
    'slaves' => [
        ['dsn' => 'mysql:host=127.0.0.1;dbname=weixinapi;charset=UTF8']
    ]
];
