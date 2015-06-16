<?php
/**
 * Created by PhpStorm.
 * User: zhuomuniao1
 * Date: 14-8-18
 * Time: 下午12:03
 */

class MySqlConn
{
    public static function getConnection()
    {
        return new Phalcon\Db\Adapter\Pdo\Mysql(array(
            'host' => Config::$mysqlHost,
            'username' => Config::$mysqlUserName,
            'password' => Config::$mysqlPassword,
            'dbname' => 'csdb',
            'options' => array(
                PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8'
            )
        ));
    }

} 