<?php

class Secret extends \Phalcon\Mvc\Model
{
	public static function findById($secretId)
	{
		return Secret::findFirst($secretId);
	}

    public static function getSecret($secretId)
    {
        $secret = Cache::getSecret($secretId);
        if ($secret !== false)
        {
            return $secret;
        }
        return Secret::findById($secretId);
    }

    public static function getUserId($secretId)
    {
        $secret = self::getSecret($secretId);
        if ($secret !== false)
        {
            return $secret->user_id;
        }
        return false;
    }

    public static function getUsersLikedSecret($secretId)
    {
        return Cache::getUsersLikedSecret($secretId);
    }

    public static function isDeleted($secretId)
    {
        $ret = Cache::getCommentCount($secretId);
        if ($ret !== false)
        {
            if ($ret == Key::Deleted)
            {
                return true;
            }
            return false;
        }
        else
        {
            $secret = Secret::findFirst($secretId);
            $deleted = $secret->status == 2 || $secret->status == 3;
            if ($deleted)
            {
                Cache::setCommentCount($secretId, Key::Deleted);
            }
            return $deleted;
        }
    }
}