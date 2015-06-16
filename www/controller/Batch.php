<?php
/**
 * Created by PhpStorm.
 * User: zhuomuniao1
 * Date: 14-8-18
 * Time: 上午11:38
 */

class Batch
{
    const MarkThreshold = 10;

    public static function addSecretMark($secretId, $userId)
    {
        // Add cache of works to do:
        $cache = Cache::getCacheObject();
        $cache->rPush(Key::BatchMarkWork, "$secretId:$userId");

        // do work if the cache enough
        // 1. return if not enough
        // 2. do work when enough, and clear the cache
        $size = $cache->lSize(Key::BatchMarkWork);
        if ($cache->lSize(Key::BatchMarkWork) > self::MarkThreshold)
        {
            if (Config::$env == 'TEST' || Config::$env == 'PROD')
            {
                // Linux has Worker support
                $client = new GearmanClient();

                $client->addServer('127.0.0.1', 4730);
                $taskId = $client->doBackground('async', json_encode(array('className' => 'Batch', 'method' => 'doAddSecretMarks')));
                return array('task' => $taskId);
            }
            else
            {
                $ret = self::doAddSecretMarks();
                return $ret;
            } // End

        }
        else
        {
            return $size;
        }
    }

    public static function delSecretMark($secretId, $userId)
    {
        // Add cache of works to do:
        $cache = Cache::getCacheObject();
        $item = "$secretId:$userId";
        $cache->lRem(Key::BatchMarkWork, $item, 1);
    }

    public static function doAddSecretMarks()
    {
        $cache = Cache::getCacheObject();
        $works = $cache->lRange(Key::BatchMarkWork, 0, -1);
        $cache->lTrim(Key::BatchMarkWork, count($works), -1);

        $conn = MySqlConn::getConnection();
        $conn->begin();

        $count = 0;
        foreach ($works as $work)
        {
            $parts = explode(':', $work);
            $secretId = $parts[0];
            $userId = $parts[1];
            self::doAddSecretMark($secretId, $userId);
            $count ++;
        }

        $conn->commit();
        $conn->close();
        return array('task' => 'direct', 'count' => $count);
    }

    private static function doAddSecretMark($secretId, $userId)
    {
        $condition = "secret_id=$secretId and user_id=$userId";

        $mark = SecretMark::findFirst(array($condition));
        if ($mark === false)
        {
            $mark = new SecretMark();
            $mark->secret_id = $secretId;
            $mark->user_id = $userId;
            $mark->save();
        }
    }
} 