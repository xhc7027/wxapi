<?php
$db = require(__DIR__ . '/db.php');
// test database! Important not to run tests on production or development databases
$db['dsn'] = 'mysql:host=192.168.1.105;dbname=weixinapi_test';
$db['username'] = 'root';
$db['password'] = '123456';

return $db;