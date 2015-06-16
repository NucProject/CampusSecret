<?php


class ShareController extends ApiController
{

    public function initialize()
    {
        Cache::initialize($this->modelsManager);
        NoSql::initialize($this->modelsManager);
    }

    public function readmeAction()
    {
    }

    public function pledgeAction()
    {
    }

    public function verifyCodeQuestionAction()
    {
        $this->view->setVar('title', '验证码常见问题');
    }

    /* Retrieve shared web page */
    // http://127.0.0.1/share/secret/{secretId}
    public function secretAction($encryptedSecretId)
    {
        if (!$this->request->isGet())
        {
            return parent::error(Error::BadHttpMethod, '');
        }

        if (!isset($encryptedSecretId))
        {
            return parent::error(Error::BadArguments, '');
        }

        $secretId = $this->handleSharedSecret($encryptedSecretId, $this->request);

        $deleted = Secret::isDeleted($secretId);
        if($deleted)
        {
            $this->dispatcher->forward(array('controller' => 'index', 'action' => 'index' ));
        }

        $secret = Secret::getSecret($secretId);
        $this->view->setVar('title', '分享自校园秘密APP');
        $content = $secret->content;
        $content = str_replace("\n", '<br>', $content);
        $this->view->setVar('secret', $content);
        $schoolId = $secret->school_id;
        $schoolName = Cache::getSchoolName($schoolId);

        $this->view->setVar('school_name', $schoolName);

        $usersLikedSecret = Secret::getUsersLikedSecret($secretId);
        if ($usersLikedSecret === false)
            $usersLikedSecret = array();
        $this->view->setVar('liked_count', count($usersLikedSecret));

        $imageKey = $secret->background_image;
        if (isset($imageKey))
        {
            $background = ImageController::getDownloadUrl($imageKey);
        }
        else
        {
            $background = 'media/bg/' . $secret->background . '.jpg';
        }

        $this->view->setVar('background', $background);
        $comments = self::fetchComments($this->modelsManager, $secretId);

        $commentCount = count($comments);
        $commentCal = Comment::getCount($secretId);
        $this->view->setVar('comment_count', $commentCal);
        $count = 0;
        if ($commentCount > 0)
        {
            $time1 = date('Y-m-d H:i:s', time());
            $items = array();
            foreach ($comments as $comment)
            {
                if($comment->removed == 1)
                {
                    continue;
                }
                $comment->rel_time = self::relativeTime($time1, $comment->time);
                $comment->avatar = parent::convert($comment->avatar_id);
                array_push($items, $comment);
                $count +=1;
                if($count == 5)
                    break;
            }
            $this->view->setVar('comments', $items);

        }
    }

    private function handleSharedSecret($encryptedSecretId, $request)
    {
        if (is_numeric($encryptedSecretId))
        {
            $secretId = $encryptedSecretId;
        }
        else
        {
            $secretId = Crypt::decrypt($encryptedSecretId, 'xiaoyuanmimi.com');
        }

        $target = $request->getQuery('target');

        $s = new SecretShared();
        $s->secret_id = $secretId;
        $s->target = $target;
        $s->save();
        return $secretId;
    }

    public function latestAction($schoolId)
    {
        if (!$this->request->isGet())
        {
            return parent::error(Error::BadHttpMethod, 'Http method should be GET');
        }

        $items = $this->fetchLatestSecrets($schoolId);
        return parent::result(array('items' => $items));
    }

    public function pastAction($schoolId, $minSecretId)
    {
        if (!$this->request->isGet())
        {
            return parent::error(Error::BadHttpMethod, 'Http method should be GET');
        }

        if (!isset($minSecretId))
        {
            return parent::error(Error::BadArguments, 'c_secret_id is required');
        }

        $items = $this->fetchPastSecrets($schoolId, $minSecretId);
        return parent::result(array('items' => $items));
    }

    private function fetchLatestSecrets($schoolId)
    {
        $ret = array();
        $count = 0;
        $secretIdArray = Cache::getSchoolSecretsStream($schoolId);

        $size = count($secretIdArray);

        for ($i = 0; $i < $size; $i++)
        {
            $secretId = $secretIdArray[$i];

            $secret = Cache::getSecret($secretId);
            if ($secret === false)
            {
                continue;
            }

            if (SecretController::assembleSecret(Key::NonUser, &$secret))
            {
                self::fillSchoolInfo(&$secret);
                array_push($ret, $secret);

                $count++;
                if ($count >= 10)
                {
                    break;
                }
            }
        }

        return $ret;
    }

    // Secrets in the pass before $minSecretId
    private function fetchPastSecrets($schoolId, $minSecretId)
    {
        $userId = $this->curUserId;

        $secretIdArray = Cache::getSchoolSecretsStream($schoolId);
        $cacheSize = count($secretIdArray);
        $begin = parent::binarySearch($secretIdArray, $minSecretId);

        if ($begin !== false)
        {
            $ret = array();
            $count = 0;
            for ($i = $begin + 1; $i < $cacheSize; $i++)
            {
                $secretId = $secretIdArray[$i];

                $secret = Cache::getSecret($secretId);
                if ($secret !== false && SecretController::assembleSecret(Key::NonUser, &$secret))
                {
                    self::fillSchoolInfo(&$secret);
                    array_push($ret, $secret);

                    $count++;
                    if ($count >= 10)
                    {
                        break;
                    }
                }
            }
        }
        else
        {
            $secrets = SecretController::fetchSecretsBefore($this->modelsManager, $schoolId, $minSecretId);
            $ret = array();
            $count = 0;
            foreach ($secrets as $secret)
            {
                $secretId = $secret->secret_id;

                if (SecretController::assembleSecret(Key::NonUser, &$secret))
                {
                    self::fillSchoolInfo(&$secret);
                    array_push($ret, $secret);
                    $count++;
                }

                if ($count >= 10)
                {
                    break;
                }
            }
            return $ret;
        }

        return $ret;
    }

    public static function fillSchoolInfo(&$secret)
    {
        if (!isset($secret->school))
        {
            $schoolName = Cache::getSchoolName($secret->school_id);
            $secret->school = $schoolName;
        }

        if (!isset($secret->academy))
        {
            $academyName = Cache::getAcademyName($secret->academy_id);
            $secret->academy = $academyName;
        }
    }

    // Cache::getComments($secretId) is not suitable for this case.
    // Fetch 5 comments, 5 at most.
    public static function fetchComments($modelsManager, $secretId)
    {
        $redis = Cache::getCacheObject();
        $key = Key::Comments . $secretId;
        $items = array();
        if ($redis->exists($key))
        {
            $jsonItems = $redis->lRange($key, 0, -1);
            foreach ($jsonItems as $item)
            {
                array_push($items, json_decode($item));
            }
        }
        else
        {
            // In the shared page, we would give the comments *without* liked count.
            $phql = "SELECT C.comment_id, C.user_id, C.secret_id, C.content, C.removed, C.floor, C.time, C.avatar_id FROM Comment AS C where C.secret_id=$secretId";
            $comments = $modelsManager->executeQuery($phql);
            foreach ($comments as $comment)
            {
                array_push($items, $comment);
            }
        }
        return $items;
    }

}
