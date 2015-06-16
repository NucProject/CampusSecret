<?php
/**
 * Created by PhpStorm.
 * User: healer
 * Date: 14-6-27
 * Time: 11:06
 * Redis is storage for saving relationships of users and secrets, notice.
 */

class NoSql
{
    private static $redis = null;

    private static $modelsManager = null;

    // select database 1 as default, database 0 is for sessions only
    public static function initialize($modelsManager)
    {
        self::$modelsManager = $modelsManager;
        self::$redis = new Redis();
        if (Config::$env == 'PROD')
        {
            // Have not changed to Unix socket.
            self::$redis->connect('127.0.0.1', 6379);
            self::$redis->select(1);
        }
        else
        {
            self::$redis->connect('127.0.0.1', 6379);
            self::$redis->select(1);
        }
    }

    public static function getCacheObject()
    {
        return self::$redis;
    }

    public static function getAllSessions()
    {
        self::$redis->select(0);
        $keys = self::$redis->getKeys("PHPREDIS_SESSION:*");
        self::$redis->select(1);
        return $keys;
    }

    /*
    public static function clear()
    {
        self::$redis->select(0);
        self::$redis->flushDB();
        self::$redis->select(1);
        self::$redis->flushDB();
        return true;
    }
    */

    ///////////////////////////////////////////////////////////////////////////////
    // Visit
    public static function setMyVisit($userId, $secretId, $floor)
    {
        $key = Key::NoticeSecretFloorHash . $userId;
        self::$redis->hSet($key, $secretId, $floor);

        // Clear Notice about.
        self::clearSecretLikedNotice($userId, $secretId);
        self::clearCommentLikedNotice($userId, $secretId);
        self::clearCommentNewNotice($userId, $secretId);
    }

    public static function getMyVisits($userId)
    {
        $key = Key::NoticeSecretFloorHash . $userId;
        return self::$redis->hGetAll($key);
    }

    // Notice
    // Notice about Secret Liked
    public static function updateSecretLikedNotice($userId, $secretId, $senderId, $add = true)
    {
        $hash = Key::NoticeSecretLiked . $userId;
        $key = "$secretId:$senderId";
        if ($add)
        {
            self::$redis->hSet($hash, $key, time());
        }
        else
        {
            self::$redis->hDel($hash, $key);
        }
    }

    public static function getNoticeSecretLiked($userId)
    {
        $hash = Key::NoticeSecretLiked . $userId;
        $array = self::$redis->hGetAll($hash);
        $ret = array();
        foreach ($array as $key => $time)
        {
            $parts = explode(':', $key);
            $secretId = $parts[0];
            if (array_key_exists($secretId, $ret))
            {
                if ($time > $ret[$secretId])
                {
                    $ret[$secretId] = $time;
                }
            }
            else
            {
                $ret[$secretId] = $time;
            }
        }
        return $ret;
    }

    public static function clearSecretLikedNotice($userId, $secretId)
    {
        $hash = Key::NoticeSecretLiked . $userId;
        $ret = self::$redis->hKeys($hash);
        foreach ($ret as &$item)
        {
            if (strpos($item, "$secretId:") === 0)
            {
                self::$redis->hDel($hash, $item);
            }
        }
    }

    // Notice about Comment Liked
    public static function updateCommentLikedNotice($userId, $secretId, $senderId, $add = true)
    {
        $hash = Key::NoticeCommentLiked . $userId;
        $key = "$secretId:$senderId";
        if ($add)
        {
            self::$redis->hSet($hash, $key, time());
        }
        else
        {
            self::$redis->hDel($hash, $key);
        }
    }

    public static function getNoticeCommentLiked($userId)
    {
        $hash = Key::NoticeCommentLiked . $userId;
        $array = self::$redis->hGetAll($hash);
        $ret = array();
        foreach ($array as $key => $time)
        {
            $parts = explode(':', $key);
            $secretId = $parts[0];
            if (array_key_exists($secretId, $ret))
            {
                if ($time > $ret[$secretId])
                {
                    $ret[$secretId] = $time;
                }
            }
            else
            {
                $ret[$secretId] = $time;
            }
        }
        return $ret;
    }

    public static function clearCommentLikedNotice($userId, $secretId)
    {
        $hash = Key::NoticeCommentLiked . $userId;
        $ret = self::$redis->hKeys($hash);
        foreach ($ret as &$item)
        {
            if (strpos($item, "$secretId:") === 0)
            {
                self::$redis->hDel($hash, $item);
            }
        }
    }

    // extractSecretId would NOT perform array_unique.
    private static function extractSecretId($noticeArray)
    {
        $ret = array();
        foreach ($noticeArray as &$notice)
        {
            $parts = explode(':', $notice);
            array_push($ret, $parts[0]);
        }
        return $ret;
    }

    // Notice about Comment New
    public static function getNoticeCommentNew($userId)
    {
        $hash = Key::NoticeUserSecrets . $userId;
        $ret = self::$redis->hGetAll($hash);
        return $ret;
    }

    public static function addNotices($secretId, $userIdArray, $time)
    {
        $m = self::$redis->multi(Redis::PIPELINE);
        foreach ($userIdArray as $userId)
        {
            $hash = Key::NoticeUserSecrets . $userId;
            $m->hSet($hash, $secretId, $time);
        }
        $m->exec();
    }

    //得到同级或者同系的推送用户
    public static function getType6PushUser($schoolId, $academyId, $grade)
    {
        $pushUserArray = array();
        $ret = self::$redis->sMembers($academyId);
        foreach($ret as $item)
        {
            array_push($pushUserArray, $item);
        }
        $ret = self::$redis->sMembers($schoolId . $grade);
        foreach($ret as $item)
        {
            array_push($pushUserArray, $item);
        }
        array_unique($pushUserArray);
        return $pushUserArray;
    }

    //将已经推送过的push记录起来（时间怎么办）
    public static function addHadPush($userId, $type, $secretId)
    {

    }

    public static function clearCommentNewNotice($userId, $secretId)
    {
        $hash = Key::NoticeUserSecrets . $userId;
        $ret = self::$redis->hDel($hash, $secretId);
    }

    public static function get_iOSVersions()
    {
        return self::$redis->hGetAll("vp:i");
    }

    public static function getAndroidVersions()
    {
        return self::$redis->hGetAll("vp:a");
    }

    public static function get_iOSVersionPolicy($version)
    {
        return self::$redis->hGet(Key::iOSVerPolicy, $version);
    }

    public static function getAndroidVersionPolicy($version)
    {
        return self::$redis->hGet(Key::AndroidVerPolicy, $version);
    }

    public static function set_iOSVersionPolicy($version, $policy)
    {
        self::$redis->hSet(Key::iOSVerPolicy, $version, $policy);
    }

    public static function clearAndroidVersionPolicy()
    {
        self::$redis->del(Key::AndroidVerPolicy);
    }

    public static function setAndroidVersionPolicy($version, $policy)
    {
        self::$redis->hSet(Key::AndroidVerPolicy, $version, $policy);
    }
}