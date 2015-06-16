<?php

use Phalcon\Loader,
    Phalcon\DI\FactoryDefault,
    Phalcon\Mvc\Application,
    Phalcon\Mvc\View;

$loader = new Loader();

$loader->registerDirs(
    array(
        '../controller',
        '../model',
    )
)->register();

$csEnv = $_SERVER['CSENV'];
echo "ENV: $csEnv";

Config::$env = $csEnv;

if ($csEnv == 'DEV')
{
    //Config::$mysqlHost = '127.0.0.1'; // If you want to use local mysql.
    Config::$mysqlHost = '192.168.1.85';
    Config::$mysqlUserName = 'root';
    Config::$mysqlPassword = 'root';
}
else if ($csEnv == 'TEST')
{
    Config::$mysqlHost = '127.0.0.1';   // Test server IP is 192.168.1.85
    Config::$mysqlUserName = 'root';
    Config::$mysqlPassword = 'root';
}
else if ($csEnv == 'PROD')
{
    Config::$mysqlHost = 'rdsqeafyaqeafya.mysql.rds.aliyuncs.com';
    Config::$mysqlUserName = 'healer';
    Config::$mysqlPassword = '123456';
}

Cache::initialize();
NoSql::initialize(null);

$di = new FactoryDefault();

$di->set('db', function() {
    return new Phalcon\Db\Adapter\Pdo\Mysql(array(
        'host' => Config::$mysqlHost,
        'username' => Config::$mysqlUserName,
        'password' => Config::$mysqlPassword,
        'dbname' => 'csdb',
        'options' => array(
            PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8'
        )
    ));
});


try {
    // 
    ini_set('date.timezone', 'Asia/Shanghai');
    $application = new Application($di);

    $worker = new GearmanWorker();
    $worker->addServer('127.0.0.1', 4730);

    $worker->addFunction('singlePush', 'performSinglePush');
    $worker->addFunction('groupPush', 'performGroupPush');
    $worker->addFunction('test', 'testWorker');
    $worker->addFunction('async', 'doAsyncWork');

    while ($worker->work());

} catch (\Exception $e) {
    echo $e->getMessage();
}

$pushUrl = "http://msg.umeng.com/api/send";


/*
 *
 *
 * */
function doAsyncWork($job)
{
    $payload = json_decode($job->workload());
    $className = $payload->className;
    $method = $payload->method;
    if (isset($className) && isset($method))
    {
        $className::$method();
    }
}

function performSinglePush($job)
{
    $payload = json_decode($job->workload());
    $ret = Push::singlePush($payload->userId, $payload->type, $payload->push);
    $redis = Cache::getCacheObject();
    $time = date("Y-m-d h:i:s");
    $redis->set('singlePush', json_encode(array('time' => $time, 'result' => $ret)));

}

function performGroupPush($job)
{
    $payload = json_decode($job->workload());
    $ret = Push::groupPush($payload->userId, $payload->schoolId, $payload->academyId, $payload->grade, $payload->type, $payload->push);
    $redis = Cache::getCacheObject();
    $time = date("Y-m-d h:i:s");
    $redis->set('groupPush', json_encode(array('time' => $time, 'result' => $ret)));
}


function testWorker($job)
{
    $payload = json_decode($job->workload());
    $a = $payload -> a;
    $redis = Cache::getCacheObject();
    $time = date("Y-m-d h:i:s");
    $redis->set('worker', json_encode(array('time' => $time, 'a' => $a)));
}
