<?php

class NoticeController extends ApiController
{
    public function initialize()
    {
        parent::initializeAction();
        parent::startSession();
    }

    // Keep this a light interface.
    public function queryAction()
    {
        $curUserId = $this->curUserId;

        $likedCount = 0;
        $secretLikedArray = NoSql::getNoticeSecretLiked($curUserId);
        $commentLikedArray = NoSql::getNoticeCommentLiked($curUserId);
        $secretLikedArray = array_unique(array_merge(array_keys($secretLikedArray), array_keys($commentLikedArray)));
        foreach ($secretLikedArray as $secretId)
        {
            if (Cache::isUserRemovedSecret($secretId, $userId) || Secret::isDeleted($secretId))
                continue;
            $likedCount++;
        }

        $secretFloors = self::calcNoticeCommentNewFloor($curUserId);
        $commentCount = 0;
        foreach ($secretFloors as $secretId => $item)
        {
            if (Cache::isUserRemovedSecret($secretId, $userId) || Secret::isDeleted($secretId))
                continue;
            $commentCount += $item['diff'];
        }

        return parent::result(array(
            'new_comments' => (int)$commentCount,
            'new_liked' => (int)$likedCount,
            'array' => $secretFloors));
    }

    private static function getSecrets($array)
    {
        $ret = array();
        foreach ($array as $secretId => $time)
        {
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

    private static function filterFloor($array)
    {
        $ret = array();
        foreach ($array as $secretId => $item)
        {
            $ret[$secretId] = $item['time'];
        }
        return $ret;
    }

    public function fetchAction($all)
    {
        $curUserId = $this->curUserId;

        // array1. Array of my secrets has been liked from last notification.
        $array1 = NoSql::getNoticeSecretLiked($curUserId);

        // array2. Array of my comments has been liked from last notification.
        $array2 = NoSql::getNoticeCommentLiked($curUserId);

        $arrayLiked = $array1 + $array2;
        $arrayLikedKeys = array_keys($arrayLiked);

        // array3. Array of my secret (or comment) has new comments from last notification.
        $secretFloors = self::calcNoticeCommentNewFloor($curUserId);
        $array3 = self::filterFloor($secretFloors);
        $array3Keys = array_keys($array3);

        $array = array();
        if ($all == 'all')
        {
            $arrayAll = self::getAllNoticeSecrets($this->modelsManager, $curUserId);
            $array = self::getSecrets($array3 + $arrayLiked + $arrayAll);
        }
        else
        {
            $array = self::getSecrets($array3 + $arrayLiked);
        }

        $secrets = array();
        $secretsDeleted = Cache::takeSecretMarkDeleted($curUserId);

        // Now client would sort also.
        arsort($array);
        foreach ($array as $secretId => $time)
        {
            if (Cache::isUserRemovedSecret($secretId, $curUserId) || Secret::isDeleted($secretId))
            {
                array_push($secretsDeleted, (string)$secretId);
                continue;
            }

            if (array_search($secretId, $secretsDeleted) !== false)
            {
                continue;
            }

            $secret = Secret::getSecret($secretId);
            if ($secret !== false)
            {
                if (SecretController::assembleSecret($curUserId, &$secret))
                {
                    if (array_search($secretId, $arrayLikedKeys) !== false)
                    {
                        $secret->others_liked = 1;
                    }

                    if (array_search($secretId, $array3Keys) !== false)
                    {
                        $secret->others_unread = $secretFloors[$secretId]['diff'];
                    }

                    $secret->last_access = $time;
                    array_push($secrets, $secret);
                }
            }
        }

        return parent::result(array('items' => $secrets, 'deleted' => $secretsDeleted, 'array' => $array));
    }

    // Only a test action
    public function fetchAllAction()
    {
        $items = self::getAllNoticeSecrets($this->modelsManager, $this->curUserId);
        return parent::result(array('items' => $items));
    }

    public static function calcNoticeCommentNewFloor($userId)
    {
        $floors = NoSql::getMyVisits($userId);
        $secrets = NoSql::getNoticeCommentNew($userId);
        $ret = array();
        foreach ($secrets as $secretId => $time)
        {
            $count = Comment::getCount($secretId);
            if ($count > 0)
            {
                $diff = $count - $floors[$secretId];
                if ($diff < 0)
                    $diff = 0;
                $ret[$secretId] = array('time' => $time, 'diff' => $diff);
            }
            else
            {
                $ret[$secretId] = array('time' => $time, 'diff' => 0);
            }
        }
        return $ret;
    }

    public static function getAllNoticeSecrets($modelsManager, $userId)
    {
        $ret = array();

        //
        $phql1 = "SELECT DISTINCT C.secret_id, C.user_id FROM CommentLiked as CL LEFT JOIN Comment as C ON CL.comment_id=C.comment_id GROUP BY C.secret_id HAVING C.user_id=$userId";
        $items1 = $modelsManager->executeQuery($phql1);

        foreach ($items1 as $item)
        {
            $ret[$item->secret_id] = 0;
        }
        //
        $phql2 = "select distinct S.secret_id, S.user_id from SecretLiked as SL left join Secret as S on SL.secret_id=S.secret_id group by S.secret_id having S.user_id=$userId";
        $items2 = $modelsManager->executeQuery($phql2);
        foreach ($items2 as $item)
        {
            $ret[$item->secret_id] = 0;
        }
        //
        $phql3 = "select S.secret_id, S.user_id from Comment as C left join Secret as S on S.secret_id=C.secret_id group by S.secret_id having S.user_id=$userId";
        $items3 = $modelsManager->executeQuery($phql3);
        foreach ($items3 as $item)
        {
            $ret[$item->secret_id] = 0;
        }

        $phql4 = "select  c1.secret_id, c1.user_id, c1.floor, c2.floor from comment as c1, comment as c2 where c1.secret_id=c2.secret_id and c1.user_id=$userId and c1.floor < c2.floor group by c1.secret_id";
        $items4 = $modelsManager->executeQuery($phql4);
        foreach ($items4 as $item)
        {
            $ret[$item->secret_id] = 0;
        }
        return $ret;
    }
}
