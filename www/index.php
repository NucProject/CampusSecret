<?php

use Phalcon\Loader,
    Phalcon\DI\FactoryDefault,
    Phalcon\Mvc\Application,
    Phalcon\Mvc\View;

// Session store using Redis:6379
ini_set('session.save_handler', 'redis');
ini_set('session.save_path', 'tcp://127.0.0.1:6379');

// Phalcon starts
$loader = new Loader();

$loader->registerDirs(
    array(
        './controller',
        './model',
    )
)->register();

// Determine Env and Config.
$csEnv = $_SERVER['CSENV'];

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

$di = new FactoryDefault();


// Registering the view component

$di->set('voltService', function($view, $di) {
    $volt = new \Phalcon\Mvc\View\Engine\Volt($view, $di);

    $volt->setOptions(array(
        'compiledPath' => __DIR__ . '/view/compiled/',
        'compiledExtension' => '.compiled'
    ));

    return $volt;
});

$di->set('view', function() {
    $view = new \Phalcon\Mvc\View();
    $view->setViewsDir(__DIR__ . '/view');
    $view->registerEngines(array(
        '.phtml' => 'voltService'
    ));

    return $view;
});

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

$di->set('modelsManager', function() {
    return new Phalcon\Mvc\Model\Manager();
});

try {

    ini_set('date.timezone', 'Asia/Shanghai');
    $application = new Application($di);
    echo $application->handle()->getContent();

} catch (\Exception $e) {
    echo $e->getMessage();
}