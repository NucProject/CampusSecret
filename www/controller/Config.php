<?php

class Config
{
    const StreamMaxCount = 120;

    const SecretsCount = 20;

    public static $env;

    public static $store;

    public static $mysqlUserName;

    public static $mysqlPassword;

    public static $mysqlHost;


    public static function hasWorker()
    {
        return self::$env == 'TEST' || self::$env == 'PROD';
    }
} 