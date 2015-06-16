<?php
/**
 * Created by PhpStorm.
 * User: yuzhongmin
 * Date: 14-6-16
 * Time: 下午12:03
 */
class Push
{
    // Push
    public static function updateUserLastAccess($userId)
    {
        $redis = Cache::getCacheObject();
        $redis->hSet(Key::PushUserLastAccess, $userId, time());
    }

    public static function updateAcademyGradeLatest($academyId, $grade)
    {
        $key = "$academyId:$grade";
        $redis = Cache::getCacheObject();
        $redis->hSet(Key::PushAcademyGradeLatestTime, $key, time());
    }

    public static function addPushUser($userId, $platform, $schoolId, $academyId, $grade)
    {
        $redis = Cache::getCacheObject();
        $redis->sAdd("p:$platform", $userId); //查询是否登录,全部可以推送的用户
        $redis->sAdd("a:$academyId", $userId); //把同学院的用户都放进同一集合
        $redis->sAdd("s:$schoolId" . "g:$grade", $userId); //把同一个学校同级的用户都放入同一集合
    }

    public static function delPushUser($userId, $platform)
    {
        $redis = Cache::getCacheObject();
        $redis->sRem("p:$platform", $userId);
    }

    public static function getPushUsers($platform)
    {
        $redis = Cache::getCacheObject();
        return $redis->sMembers("p:$platform");
    }

    public static function singlePushPerform($userId, $type, $push)
    {
        if (Config::hasWorker())
        {
            $client = new GearmanClient();
            $client->addServer('127.0.0.1', 4730);
            $taskId = $client->doBackground('singlePush', json_encode(array('userId' => $userId, 'type' => $type, 'push' => $push)));
            return $taskId;
        }
    }

    public static function groupPushPerform($userId, $schoolId, $academyId, $grade, $type, $push)
    {
        if (Config::hasWorker())
        {
            $client = new GearmanClient();
            $client->addServer('127.0.0.1', 4730);
            $taskId = $client->doBackground('groupPush', json_encode(array('userId' => $userId, 'schoolId' => $schoolId, 'academyId' => $academyId, 'grade' => $grade, 'type' => $type, 'push' => $push)));
            return $taskId;
        }
    }

    //给某一个特定的用户添加某一秘密的推送,在这里修改push,用分隔号隔开
    public static function singlePush($userId, $type, $push)
    {
        $parts = explode(':', $push);
        $secretId = $parts[0];
        $time = $parts[1];
        //"预留"
        if($type == 1)
        {
            $key = Key::pushType1;
            if(self::goSingleFilter($userId, $type, $push))
            {
               return true;
            }
        }
        //"有人评论了你的秘密"
        if($type == 2)
        {
            $typeKey = Key::pushType2;
            if(self::goSingleFilter($userId, $type, $push))
            {
                $ret = self::commentOwnSecretPush($userId, $secretId);
                self::saveSinglePush($userId, $typeKey, $time);
                return $ret;
            }
        }
        //"你关注的秘密有了新评论"
        if($type == 4)
        {
            $typeKey = Key::pushType4;
            if(self::goSingleFilter($userId, $type, $push))
            {
                $ret = self::commentFollowSecretPush($userId, $secretId);
                self::saveSinglePush($userId, $typeKey, $time);
                return $ret;
            }

        }
    }

    //使用tag过滤用户，组播push
    public static function groupPush($userId, $schoolId, $academyId, $grade, $type, $push)
    {
        $parts = explode(':', $push);
        $secretId = $parts[0];
        $time = $parts[1];
        if($type == 6)
        {
            if(self::goGroupFilter($schoolId, $academyId, $grade, $time))
            {
                $data = self::sameAcademyOrGradeData($schoolId, $academyId, $grade, $secretId);
                $ret = self::sendPushPost($data);
                self::saveGroupPush($schoolId, $academyId, $grade, $time);
                return $ret;
            }
        }
        if($type == 7)
        {
            $data = self::sameAcademyAndGradeData($userId, $academyId, $grade, $secretId);
            $ret = self::sendPushPost($data);
            return $ret;
        }
    }

    public static function saveSinglePush($userId, $type, $time)
    {
        $key = Key::singlePush . $userId;
        $redis = NoSql::getCacheObject();
        $redis->hset($key, $type, $time);
    }

    public static function saveGroupPush($schoolId, $academyId, $grade, $time)
    {
        $key = Key::groupPush;
        $redis = NoSql::getCacheObject();
        $redis->hset($key, "$schoolId:$grade", $time);
        $redis->hset($key, $academyId, $time);
    }

    public static function goSingleFilter($userId, $type, $push)
    {
        if(self::pushGeneralFilter1($userId, $type, $push))
        {
            if(self::pushGeneralFilter2($userId, $type, $push))
            {
                if(self::pushFilter1($userId, $type, $push))
                {
                    return true;
                }
            }
        }
        else
        {
            return false;
        }
    }

    public static function goGroupFilter($schoolId, $academyId, $grade, $time)
    {
        $key = Key::groupPush;
        $redis = NoSql::getCacheObject();
        $lastGradeTime = $redis->hget($key, "$schoolId:$grade");
        $lastAcademyTime = $redis->hget($key, $academyId);
        if((($time - $lastGradeTime) < 900)&&(($time - $lastAcademyTime) < 900))  //15分钟
        {
            return false;
        }
        else
        {
            return true;
        }

    }

    //Push产生后、在推送之前，用户看过该秘密详情，未发送的Push不发
    public static function pushGeneralFilter1($userId, $type, $push)
    {
        return true;
    }

    //每天23:30-07:30全部类别都停止发送，且期间产生的push都舍弃
    public static function pushGeneralFilter2($userId, $type, $push)
    {
        $parts = explode(':', $push);
        $time = $parts[1];
        $dayTime = date("Y-m-d H:i:s" ,$time);
        $parseTime = date_parse_from_format("Y-m-d H:i:s", $dayTime);
        $hour = (int)$parseTime['hour'];
        $min = (int)$parseTime['minute'];
        if((($hour == 23) && ($min > 30))||($hour > 23)||($hour < 7)||(($hour == 7) && ($min < 30)))
        {
            return false;
        }
        else
        {
            return true;
        }
    }

    //15分钟之内全部类别只推送一条，凡未推送成功的同类push，都抛弃。15分钟之后，不同秘密，同样类别也需要推送
    public static function pushFilter1($userId, $type, $push)
    {
        $parts = explode(':', $push);
        $time = $parts[1];
        $key = Key::singlePush . $userId;
        $redis = NoSql::getCacheObject();
        $lastTime = $redis->hget($key, $type);

        if(($time - $lastTime) < 900)
        {
            return false;
        }
        else
        {
            return true;
        }
    }
    /*//若用户没有打开应用，push保留12小时，倒序排列，优先发送最近产生的,使用友盟的expire_time来实现
    public static function pushFilter2($userId, $type, $push)
    {

    }*/


    public static function commentOwnSecretPush($userId, $secretId)
    {
        $data = self::commentOwnSecretData($userId, $secretId);
        $ret = self::sendPushPost($data);
        return $ret;
    }

    public static function commentFollowSecretPush($userId, $secretId)
    {
        $data = self::commentFollowSecretData($userId, $secretId);
        $ret = self::sendPushPost($data);
        return $ret;
    }


    //有人评论你的秘密
    public static function commentOwnSecretData($userId, $secretId)
    {
        $time = strtotime("+12 hours");
        $expireTime = date('Y-m-d H:i:s', $time);
        $res = array(
            "appkey" => "538d857056240bf91c1a1077",
            "timestamp" => "201407301500",
            "validation_token" => "8cffd1351f1cda5743cd14f2580fe898",
            "type" => "customizedcast",
            "alias" => $userId,
            "payload" => array(
                "display_type" => "notification",
                "body" => array(
                    "ticker" => "有人评论了你的秘密",
                    "title" => "有人评论了你的秘密",
                    "text" => "校园秘密",
                    "play_vibrate" => "true" ,
                    "after_open" => "go_activity",
                    "activity" => "com.xiaoyuanmimi.campussecret.activitys.DetailActivity"
                ),
                "extra" => array(
                    "secret_id" => $secretId
                )
            ),
            "policy" => array(
                "expire_time" => $expireTime
            ),
            "description" => "有人评论了你的秘密"
        );
        $res = json_encode($res);
        return $res;
    }

    //你关注的秘密有了新评论
    public static function commentFollowSecretData($userId, $secretId)
    {
        $time = strtotime("+12 hours");
        $expireTime = date('Y-m-d H:i:s', $time);
        $res = array(
            "appkey" => "538d857056240bf91c1a1077",
            "timestamp" => "201407301500",
            "validation_token" => "8cffd1351f1cda5743cd14f2580fe898",
            "type" => "customizedcast",
            "alias" => $userId,
            "payload" => array(
                "display_type" => "notification",
                "body" => array(
                    "ticker" => "你关注的秘密有了新评论",
                    "title" => "你关注的秘密有了新评论",
                    "text" => "校园秘密",
                    "play_vibrate" => "true" ,
                    "after_open" => "go_activity",
                    "activity" => "com.xiaoyuanmimi.campussecret.activitys.DetailActivity"
                ),
                "extra" => array(
                    "secret_id" => $secretId
                )
            ),
            "policy" => array(
                "expire_time" => $expireTime
            ),
            "description" => "你关注的秘密有了新的评论"
        );
        $res = json_encode($res);
        return $res;
    }

    //同级且同系，你的同学发了一个秘密
    public static function sameAcademyAndGradeData($userId, $academyId, $grade, $secretId)
    {
        $time = strtotime("+12 hours");
        $expireTime = date('Y-m-d H:i:s', $time);
        $res = array(
            "appkey" => "538d857056240bf91c1a1077",
            "timestamp" => "201407301500",
            "validation_token" => "8cffd1351f1cda5743cd14f2580fe898",
            "type" => "groupcast",
            "filter" =>array(
                "where" => array(
                    "and" => array(array("tag" => "A" . $academyId), array("tag" => "G" . $grade),array(
                        "not" => array("tag" => "U" . $userId)
                    ))
                )
            ),
            "payload" => array(
                "display_type" => "notification",
                "body" => array(
                    "ticker" => "你的同学发了一个秘密",
                    "title" => "你的同学发了一个秘密",
                    "text" => "校园秘密",
                    "play_vibrate" => "true" ,
                    "after_open" => "go_activity",
                    "activity" => "com.xiaoyuanmimi.campussecret.activitys.DetailActivity"
                ),
                "extra" => array(
                    "secret_id" => $secretId
                )
            ),
            "policy" => array(
                "expire_time" => $expireTime
            ),
            "description" => "同级且同系"
        );
        $res = json_encode($res);
        return $res;

    }

    //同级或同系，你的同学发了一个秘密
    public static function sameAcademyOrGradeData($schoolId, $academyId, $grade, $secretId)
    {
        $time = strtotime("+12 hours");
        $expireTime = date('Y-m-d H:i:s', $time);
        $res = array(
            "appkey" => "538d857056240bf91c1a1077",
            "timestamp" => "201407301500",
            "validation_token" => "8cffd1351f1cda5743cd14f2580fe898",
            "type" => "groupcast",
            "filter" =>array(
                "where" => array(
                    "or" => array(
                        array(
                            "and" => array(
                                array(
                                    "tag" => "A" . $academyId
                                ),
                                array(
                                    "not" => array("tag" => "G" . $grade)
                                )
                            ),
                            array(
                                "and" => array(
                                    array(
                                        "not" => array("tag" => "A" . $academyId)
                                    ),
                                    array(
                                        "tag" => "G" . $grade
                                    ),
                                    array("tag" => "S" . $schoolId)
                                )
                            )
                        )
                    )
                )
            ),
            "payload" => array(
                "display_type" => "notification",
                "body" => array(
                    "ticker" => "你的同学发了一个秘密",
                    "title" => "你的同学发了一个秘密",
                    "text" => "校园秘密",
                    "play_vibrate" => "true" ,
                    "after_open" => "go_activity",
                    "activity" => "com.xiaoyuanmimi.campussecret.activitys.DetailActivity"
                ),
                "extra" => array(
                    "secret_id" => $secretId
                )
            ),
            "policy" => array(
                "expire_time" => $expireTime
            ),
            "description" => "同级或同系"
        );
        $res = json_encode($res);
        return $res;

    }


    public static function broadcastData()
    {

    }

    function sendPushPost($data)
    {
        $url = "http://msg.umeng.com/api/send";
        $ch = curl_init ();
        curl_setopt ( $ch, CURLOPT_URL, $url );
        curl_setopt ( $ch, CURLOPT_POST, 1 );
        curl_setopt ( $ch, CURLOPT_HEADER, 0 );
        curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, 1 );
        curl_setopt ( $ch, CURLOPT_POSTFIELDS, $data );
        $return = curl_exec ( $ch );
        curl_close ( $ch );
        return $return;
    }
}