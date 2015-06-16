<?php

/**
 * Cache means data can be rebuild from MySQL database.
 */
class Cache
{
    private static $cache = null;

    private static $modelsManager = null;

    public static function initialize($modelsManager = null)
    {
        self::$modelsManager = $modelsManager;
        self::$cache = new Redis();
        if (Config::$env == 'PROD')
        {
            self::$cache->connect('/var/run/redis/cache.sock');
        }
        else
        {
            // For test and dev env, both connect to 6380 instead of UNIX socket.
            self::$cache->connect('127.0.0.1', 6380);
        }
    }

    public static function getCacheObject()
    {
        return self::$cache;
    }

    // Cache questions and return the first;
    private static function cacheQuestions()
    {
        $questions = Question::find();
        $question1 = false;
        foreach ($questions as $question)
        {
            if ($question1 === false)
            {
                $question1 = $question;
            }
            self::$cache->lPush(Key::Questions, json_encode($question));
        }
        return $question1;
    }

    public static function clearQuestions()
    {
        self::$cache->del(Key::Questions);
    }

    public static function getRandomQuestion($currentQuestionId)
    {
        $questions = self::$cache->lRange(Key::Questions, 0, -1);
        $count = count($questions);

        if ($count == 0)
        {
            $question = self::cacheQuestions();
        }
        else
        {
            $i = rand(0, $count - 1);
            $question = json_decode($questions[$i]);
        }

        if (isset($question->image_key) && strlen($question->image_key) > 0)
        {
            $question->background = ImageController::getDownloadUrl($question->image_key);
        }
        unset($question->image_key);
        return $question;
    }

    ///////////////////////////////////////////////////////////////////////////
    // User Cache
    // User Removed secrets
    public static function addUserRemovedSecret($secretId, $userId)
    {
        $key = Key::SecretRemoved . $secretId;
        self::$cache->sAdd($key, $userId);
    }

    private static function cacheUserRemovedSecrets($secretId)
    {
        $key = Key::SecretRemoved . $secretId;
        $secretsRemoved = SecretRemoved::find(array("secret_id=$secretId"));

        self::$cache->sAdd($key, 0);    // Make sure contains 1 item at least.
        if ($secretsRemoved !== false && count($secretsRemoved) > 0)
        {
            $ret = array();
            foreach ($secretsRemoved as $item)
            {
                if (self::$cache->sAdd($key, $item->user_id))
                {
                    array_push($ret, $item->user_id);
                }
            }
            return $ret;
        }
        return false;

    }

    public static function isUserRemovedSecret($secretId, $userId)
    {
        $key = Key::SecretRemoved . $secretId;
        $usersRemovedSecret = self::$cache->sMembers($key);
        $count = count($usersRemovedSecret);
        if ($count == 0)
        {
            $usersRemovedSecret = self::cacheUserRemovedSecrets($secretId);
            if ($usersRemovedSecret === false)
            {
                return false;
            }
            else
            {
                return array_search($userId, $usersRemovedSecret) !== false;
            }
        }
        return array_search($userId, $usersRemovedSecret) !== false;
    }

    ///////////////////////////////////////////////////////////////////////////
    // User liked Secrets
    public static function addUserLikedSecret($secretId, $userId)
    {
        $key = Key::SecretLiked . $secretId;
        self::$cache->sAdd($key, $userId);
    }

    public static function delUserLikedSecret($secretId, $userId)
    {
        $key = Key::SecretLiked . $secretId;
        self::$cache->sRem($key, $userId);
    }

    private static function cacheUserLikedSecrets($secretId)
    {
        $key = Key::SecretLiked . $secretId;
        $secretsLiked = SecretLiked::find(array("secret_id = $secretId"));

        self::$cache->sAdd($key, 0);    // Make sure contains 1 item at least.
        if ($secretsLiked !== false && count($secretsLiked) > 0)
        {
            $ret = array();
            foreach ($secretsLiked as $item)
            {
                if (self::$cache->sAdd($key, $item->user_id))
                {
                    array_push($ret, $item->user_id);
                }
            }
            return $ret;
        }
        return false;
    }

    public static function getUsersLikedSecret($secretId)
    {
        $key = Key::SecretLiked . $secretId;
        $usersLikedSecret = self::$cache->sMembers($key);
        $count = count($usersLikedSecret);
        if ($count > 0)
        {
            return $usersLikedSecret;
        }
        else
        {
            if (!self::$cache->exists($key))
            {
                $ret = self::cacheUserLikedSecrets($secretId);
                return $ret;
            }
            return array(0);
        }
    }

    ///////////////////////////////////////////////////////////////////////////
    // User liked Comments
    public static function addUserLikedComment($commentId, $userId)
    {
        $key = Key::CommentLiked . $commentId;
        self::$cache->sAdd($key, $userId);
    }

    public static function delUserLikedComment($commentId, $userId)
    {
        $key = Key::CommentLiked . $commentId;
        self::$cache->sRem($key, $userId);
    }

    private static function cacheUserLikedComments($commentId)
    {
        $key = Key::CommentLiked . $commentId;
        $commentsLiked = CommentLiked::find(array("comment_id = $commentId"));

        if ($commentsLiked !== false && count($commentsLiked) > 0)
        {
            $ret = array();
            foreach ($commentsLiked as $item)
            {
                if (self::$cache->sAdd($key, $item->user_id))
                {
                    array_push($ret, $item->user_id);
                }
            }
            return $ret;
        }
        return false;
    }

    public static function getUsersLikedComment($commentId)
    {
        $key = Key::CommentLiked . $commentId;
        if (!self::$cache->exists($key))
        {
            $ret = self::cacheUserLikedComments($commentId);
            return $ret;
        }
        return self::$cache->sMembers($key);
    }

    ///////////////////////////////////////////////////////////////////////////
    // Secret Stream for each school
    public static function updateSchoolSecretStream($schoolId, $secret)
    {
        $key = Key::SecretStream . $schoolId;
        $secretId = $secret->secret_id;

        self::$cache->lPush($key, $secretId);
        self::$cache->hSet(Key::SecretHash, $secretId, json_encode($secret));

        // Pop the old secret in the cache for GC.
        if (self::$cache->lSize($key) > Config::StreamMaxCount)
        {
            $removedSecretId = self::$cache->rPop($key);
            self::clearSecretCache($removedSecretId);
        }
    }

    //////////////////////////////////////////////////////////////////////////
    //add a recommended secret to Secret Stream
    public static function addRecommendToSchoolSecretStream($schoolId, $secret)
    {
        $key = Key::SecretStream . $schoolId;
        $secretId = $secret->secret_id;
        self::$cache->lPush($key, $secretId);

        self::cacheRecommendedSecrets($secret);
    }

    public static function getSchoolSecretsStream($schoolId)
    {
        $key = Key::SecretStream . $schoolId;

        $array = self::$cache->lRange($key, 0, -1);
        $count = count($array);
        if ($count == 0)
        {
            self::cacheSecrets($schoolId);
            $array = self::$cache->lRange($key, 0, -1);
        }

        return $array;
    }

    public static function removeSecretFromSchoolSecretStream($schoolId, $secretId)
    {
        // Set count = deleted
        self::$cache->hSet(Key::SecretComments, $secretId, Key::Deleted);
        // Clear secrets
        $key = Key::SecretStream . $schoolId;
        self::$cache->lRem($key, $secretId, 1);
        self::$cache->hDel(Key::SecretHash, $secretId);
        // Clear comments
        Cache::deleteComments($secretId);
    }

    private static function cacheSecrets($schoolId)
    {
        $key = Key::SecretStream . $schoolId;
        $secrets = self::fetchSecretsFromDatabase($schoolId, Config::StreamMaxCount);

        foreach ($secrets as $secret)
        {
            $secretId = $secret->secret_id;
            $commentCount = $secret->comment_count;
            $likedCount = $secret->liked_count;
            unset($secret->comment_count);
            unset($secret->liked_count);
            $json = json_encode($secret);
            self::$cache->rPush($key, $secretId);
            self::$cache->hSet(Key::SecretHash, $secretId, $json);
        }
    }

    //add a recommended secret to a cache-list.
    private static function cacheRecommendedSecrets($secret)
    {
        $key = Key::RecommendedSecrets;
        // Maybe NO need.
        $existItems = self::$cache->lRange($key, 0, -1);
        foreach($existItems as $item)
        {
            $s = json_decode($item);
            if($s->secret_id == $secret->secret_id)
            {
                return false;
            }
        }

        self::$cache->lPush($key, json_encode($secret));
        if (self::$cache->lSize($key) > 100)
        {
            self::$cache->rPop($key);
        }
        return true;
    }

    public static function getRecommendedSecretStream($count)
    {
        $key = Key::RecommendedSecrets;
        $array = self::$cache->lRange($key, 0, $count);
        return $array;
    }

    public static function getSecret($secretId)
    {
        $j = self::$cache->hGet(Key::SecretHash, $secretId);
        if ($j !== false)
        {
            return json_decode($j);
        }
        return false;
    }

    public static function getNewFloor($secretId)
    {
        $key = Key::Comments . $secretId;
        $floor = self::$cache->rPush($key, '{}');
        return $floor;
    }

    // MUST Optimize here!!!
    public static function getComments($secretId)
    {
        $key = Key::Comments . $secretId;
        if (self::$cache->exists($key))
        {
            $items = self::$cache->lRange($key, 0, -1);
            return $items;
        }
        else
        {
            $phql = "SELECT C.comment_id, C.user_id, C.secret_id, C.content, C.removed, C.floor, C.time, C.avatar_id, count(CL.user_id) as liked_count FROM Comment AS C LEFT JOIN CommentLiked AS CL ON C.comment_id=CL.comment_id GROUP BY C.comment_id HAVING C.secret_id=$secretId";
            $comments = self::$modelsManager->executeQuery($phql);

            $items = array();
            foreach ($comments as $comment)
            {
                $json = json_encode($comment);
                array_push($items, $json);
                self::$cache->rPush($key, $json);
            }
            return $items;
        }
    }

    public static function deleteComments($secretId)
    {
        $key = Key::Comments . $secretId;
        self::$cache->del($key);
    }

    public static function getComment($secretId, $floor)
    {
        $key = Key::Comments . $secretId;
        $commentJson = self::$cache->lGet($key, $floor - 1);
        if ($commentJson !== false)
        {
            return json_decode($commentJson);
        }
        return false;
    }

    public static function setComment($secretId, $comment, $floor)
    {
        $key = Key::Comments . $secretId;
        $commentJson = json_encode($comment);
        self::$cache->lSet($key, $floor - 1, $commentJson);

        self::$cache->hIncrBy(Key::SecretComments, $secretId, 1);
    }

    public static function getCommentCount($secretId)
    {
        return self::$cache->hGet(Key::SecretComments, $secretId);
    }

    public static function setCommentCount($secretId, $count)
    {
        return self::$cache->hSet(Key::SecretComments, $secretId, $count);
    }

    // TODO: Redis not support delete item by admin. We should provide a way to erase the item in the middle.
    public static function discardComment($secretId, $floor)
    {
        $key = Key::Comments . $secretId;
        if (self::$cache->lSize($key) == $floor)
        {
            self::$cache->rPop();
        }
        else
        {
            // TODO: provide a way to erase the item in the middle.
        }
    }

    public static function deleteComment($secretId, $floor)
    {
        $key = Key::Comments . $secretId;
        $index = $floor - 1;
        $commentJson = self::$cache->lGet($key, $index);
        $comment = json_decode($commentJson);
        if ($comment->floor == $floor)
        {
            $comment->removed = 1;
            $comment->floor = intval($floor);
            $commentJson = json_encode($comment);
            self::$cache->lSet($key, $index, $commentJson);

            self::$cache->hIncrBy(Key::SecretComments, $secretId, -1);
            return true;
        }
        return false;
    }

    public static function getRandomAvatarId($secretId, &$comments)
    {
        $key = Key::CommentsAvatarList . $secretId;
        $avatarId = false;
        if (self::$cache->exists($key))
        {
            $avatarId = self::$cache->lPop($key);
        }

        if ($avatarId === false)
        {
            $avatarId = self::rebuildRandomAvatarIdQueue($secretId, &$comments);
        }
        return $avatarId;
    }

    private static function rebuildRandomAvatarIdQueue($secretId, &$comments)
    {
        $key = Key::CommentsAvatarList . $secretId;
        $count = count($comments);
        $avatarIdArray = array();
        if ($count < 100)
        {
            $avatarIdArray = range(1, Comment::LocalAvatarCount);
        }
        else
        {
            $avatarIdArray = range(Comment::LocalAvatarCount + 1, Comment::AllAvatarCount);
        }

        foreach ($comments as &$comment)
        {
            unset($avatarIdArray[$comment->avatar_id]);
        }

        $avatarIdArray = array_values($avatarIdArray);

        shuffle($avatarIdArray);
        $avatarId = array_shift($avatarIdArray);
        foreach ($avatarIdArray as $i)
        {
            self::$cache->rPush($key, $i);
        }
        return $avatarId;
    }


    private static function fetchSecretsFromDatabase($schoolId, $count = 150)
    {
        $phql =
            'SELECT S.secret_id, S.user_id, S.content, S.time, S.school_id, S.academy_id, S.grade, S.status, S.background, S.background_image, count(distinct C.comment_id) as comment_count, count(distinct SL.user_id) as liked_count '.
            'FROM Secret AS S '.
            'LEFT JOIN Comment AS C ON C.secret_id=S.secret_id '.
            "LEFT JOIN SecretLiked AS SL ON S.secret_id=SL.secret_id ".
            'GROUP BY S.secret_id '.
            "HAVING S.school_id=$schoolId ORDER BY S.secret_id DESC limit $count";

        $secrets = self::$modelsManager->executeQuery($phql);
        return $secrets;
    }

    public static function fetchSecretsBefore($schoolId, $secretId)
    {
        $phql =
            'SELECT S.secret_id, S.user_id, S.content, S.time, S.school_id, S.academy_id, S.grade, S.status, S.background, S.background_image, count(distinct C.comment_id) as comment_count, count(distinct SL.user_id) as liked_count '.
            'FROM Secret AS S '.
            'LEFT JOIN Comment AS C ON C.secret_id=S.secret_id '.
            'LEFT JOIN SecretLiked AS SL ON S.secret_id=SL.secret_id '.
            'GROUP BY S.secret_id '.
            "HAVING S.school_id=$schoolId and S.secret_id < $secretId and S.status != 2 and S.status != 3 ORDER BY S.secret_id DESC limit 20";

        $secrets = self::$modelsManager->executeQuery($phql);
        return $secrets;
    }

    public static function getSchoolIdArray()
    {
        $array = self::$cache->hKeys(Key::SchoolHash);
        if (count($array) == 0)
        {
            $schools = School::find();
            foreach ($schools as $school)
            {
                self::$cache->hSet(Key::SchoolHash, $school->school_id, $school->name);
            }
            $array = self::$cache->hKeys(Key::SchoolHash);
        }
        return $array;
    }

    public static function getSchoolName($schoolId)
    {
        $name = self::$cache->hGet(Key::SchoolHash, $schoolId);
        if ($name === false)
        {
            if (!self::$cache->exists(Key::SchoolHash))
            {
                $schools = School::find();
                foreach ($schools as $school)
                {
                    self::$cache->hSet(Key::SchoolHash, $school->school_id, $school->name);
                }
            }
            return self::$cache->hGet(Key::SchoolHash, $schoolId);
        }
        else
        {
            return $name;
        }
    }

    public static function getAcademyName($academyId)
    {
        $name = self::$cache->hGet(Key::AcademyHash, $academyId);
        if ($name === false && !self::$cache->exists(Key::AcademyHash))
        {
            $academies = Academy::find();
            foreach ($academies as $academy)
            {
                self::$cache->hSet(Key::AcademyHash, $academy->academy_id, $academy->name);
            }
            return self::$cache->hGet(Key::AcademyHash, $academyId);
        }
        return $name;
    }

    public static function clearSecretCache($removedSecretId)
    {
        self::$cache->hDel(Key::SecretHash, $removedSecretId);
        Cache::deleteComments($removedSecretId);
    }

    public static function clear()
    {
        self::$cache->flushDB();
        return true;
    }

    public static function saveVerifyCode($phoneNum)
    {
        $verifyCtrlKey = Key::UserVerifyCtrl . $phoneNum;
        $verifyCodeKey = Key::UserVerifyCode . $phoneNum;

        if (self::$cache->get($verifyCtrlKey) !== false)
        {
            $verifyCode = self::$cache->get($verifyCodeKey);
            return $verifyCode;
        }

        $a = rand(10, 99);
        $b = rand(10, 99);
        $verifyCode = "$a$b";
        self::$cache->setex($verifyCodeKey, 1800, $verifyCode);
        self::$cache->setex($verifyCtrlKey, 120, 1);

        return $verifyCode;
    }

    public static function loadVerifyCode($username)
    {
        $verifyCodeKey = Key::UserVerifyCode . $username;
        $verifyCode = self::$cache->get($verifyCodeKey);
        return $verifyCode;
    }

    // Mark-----------------------------------------------------------------------------------------
    public static function addSecretMark($secretId, $userId)
    {
        $set = Key::MarkSet . $secretId;
        return self::$cache->sAdd($set, $userId);
    }

    public static function delSecretMark($secretId, $userId)
    {
        $set = Key::MarkSet . $secretId;
        self::$cache->sRem($set, $userId);

        $userMarkDeleted = Key::MarkSetDeleted . $userId;
        self::$cache->sAdd($userMarkDeleted, $secretId);
    }

    public static function takeSecretMarkDeleted($userId)
    {
        $userMarkDeleted = Key::MarkSetDeleted . $userId;
        $ret = self::$cache->sMembers($userMarkDeleted);

        self::$cache->del($userMarkDeleted);
        return $ret;
    }

    public static function getSecretMark($secretId)
    {
        $set = Key::MarkSet . $secretId;
        return self::$cache->sMembers($set);
    }

    public static function isSecretMark($secretId, $userId)
    {
        $set = Key::MarkSet . $secretId;
        return self::$cache->sContains($set, $userId);
    }

}