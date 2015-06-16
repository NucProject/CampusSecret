<?php
/**
 * Created by PhpStorm.
 * User: yuzhongmin
 * Date: 14-6-4
 * Time: 下午2:50
 */

class School extends \Phalcon\Mvc\Model
{
    public static function findByName($name)
    {
        $condition = "name = '$name'";
        return School::findFirst(array($condition));
    }

    public static function getacademy($secretId)
    {
        $secret = Cache::getSecret($secretId);
        if ($secret !== false)
        {
            return $secret;
        }
        return Secret::findById($secretId);
    }
}