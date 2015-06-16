<?php

class SecretController extends ApiController
{
    //
    public function initialize()
    {
        parent::initializeAction();
        parent::startSession();
    }

    // Return Config::SecretsCount latest secrets.
    // Principle: Return Config::SecretsCount latest secrets, no matter what got last time.
    public function latestAction()
    {
        if (!$this->request->isGet())
        {
            return parent::error(Error::BadHttpMethod, 'Http method should be GET');
        }

        $items = $this->fetchLatestSecrets($this->curSchoolId);
        $extraItems = array();
        $count = count($items);
        //less than Config::SecretsCount,fetch extra secrets from recommended secret cache
        if($count < Config::SecretsCount)
        {
            $extraCount = Config::SecretsCount - $count;
            $extraSecrets =  $this->fetchExtraSecrets($extraCount);
            foreach($extraSecrets as $secret)
            {
                $userId = $this->curUserId;
                $item = json_decode($secret);
                self::assembleSecret($userId, &$item);
                array_push($extraItems, $item);
            }
        }
        $allItems = array_merge($items, $extraItems);
        Push::updateUserLastAccess($this->curUserId);
        $question = Cache::getRandomQuestion();

        $array = $this->sortSecrets(&$allItems);

        return parent::result(array('items' => $array, 'question' => $question));
    }

    public function secretAction($secretId)
    {
        if (!$this->request->isGet())
        {
            return parent::error(Error::BadHttpMethod, 'Http method should be GET');
        }

        if (Cache::isUserRemovedSecret($secretId, $this->curUserId) ||
            Secret::isDeleted($secretId))
        {
            return parent::error(Error::FetchFailedForSecretDeleted, 'Secret deleted');
        }

        $userId = $this->curUserId;
        $secret = Secret::getSecret($secretId);
        if (self::assembleSecret($userId, &$secret))
        {
            return parent::result(array('item' => $secret));
        }
        else
        {
            return parent::error(Error::BadArguments, '');
        }
    }

    public function pastAction($minSecretId)
    {
        if (!$this->request->isGet())
        {
            return parent::error(Error::BadHttpMethod, 'Http method should be GET');
        }

        if (!isset($minSecretId))
        {
            return parent::error(Error::BadArguments, 'c_secret_id is required');
        }

        $items = $this->fetchPastSecrets($this->curSchoolId, $minSecretId);
        $array = $this->sortSecrets(&$items);
        return parent::result(array('items' => $array));
    }

    public function postAction()
    {
        if (!$this->request->isPost())
        {
            return parent::error(Error::BadHttpMethod, '');
        }

        $payload = $this->request->getPost();

        $content = $payload['content'];
        if (trim($content) == '')
        {
            return parent::error(Error::SecretPostInvalidContent, 'The content is empty');
        }

        $imageKey = $payload['image-key'];
        $backgroundIndex = $payload['background-index'];
        $ret = self::postSecret($this->curUserId, $content, time(), $this->curSchoolId, $this->curAcademyId, $this->curGrade, $backgroundIndex, $imageKey, null);
        if ($ret !== false)
        {
            $secretId = $ret['secret']->secret_id;

            NoSql::setMyVisit($this->curUserId, $secretId, 0);
            return parent::result($ret);
        }
        else
        {
            return parent::error(Error::BadRecord, 'Save secret failed');
        }
    }

    public static function postSecret($userId, $content, $time, $schoolId, $academyId, $grade, $backgroundIndex, $imageKey = null, $secretKey = null)
    {
        $secret = new Secret();

        $secret->user_id = $userId;
        $secret->time = date('Y-m-d H:i:s', $time);    // Server time
        $secret->content = $content;
        $secret->school_id = $schoolId;
        $secret->academy_id = $academyId;
        $secret->grade = $grade;
        $secret->report = 0;
        $secret->status = 0;
        $secret->background = 1;    // Default is 1, NOT Zero
        $secret->secret_key = $secretKey;
        if (isset($imageKey))
        {
            $secret->background_image = $imageKey;
        }
        else
        {
            $secret->background = $backgroundIndex;
        }

        if ($secret->save() != false)
        {
            Cache::updateSchoolSecretStream($schoolId, $secret);

            // Close Push when v1.0
            /*
            //Push::updateAcademyGradeLatest($academyId, $grade);
            $time = time();
            $secretId = $secret->secret_id;
            $push = "$secretId:$time";


            Push::groupPushPerform($userId, $schoolId, $academyId, $grade, 7, $push);
            Push::groupPushPerform($userId, $schoolId, $academyId, $grade, 6, $push);
            */

            self::assembleSecret($userId, &$secret);
            return array('post' => true, 'secret' => $secret);
        }
        else
        {
            return false;
        }
    }

    public function questionAction($currentQuestionId)
    {
        if (!$this->request->isGet())
        {
            return parent::error(Error::BadHttpMethod, 'Http method should be GET');
        }

        $question = Cache::getRandomQuestion($currentQuestionId);
        return parent::result(array("question" => $question));
    }

    // Hide the secret for the current user
    // Just the user can NOT see it.
    public function removeAction($secretId)
    {
        if (!$this->request->isGet())
        {
            return parent::error(Error::BadHttpMethod, '');
        }

        $userId = $this->curUserId;
        if (self::removeSecret($userId, $secretId))
        {
            return parent::result(array('removed' => true, 'secret_id' => $secretId));
        }
        else
        {
            return parent::error(Error::BadRecord, '');
        }
    }

    // Remove [NOT delete]
    private static function removeSecret($userId, $secretId)
    {
        $secretRemoved = new SecretRemoved();
        $secretRemoved->secret_id = $secretId;
        $secretRemoved->user_id = $userId;
        $ret = $secretRemoved->save();
        if ($ret)
        {
            Cache::addUserRemovedSecret($secretId, $userId);
            return true;
        }
        return false;
    }

    // Delete the secret for all the users
    public function deleteAction($secretId)
    {
        if (!$this->request->isGet())
        {
            return parent::error(Error::BadHttpMethod, '');
        }

        $ret = self::deleteSecret($secretId, $this->curUserId, true);
        if ($ret == Error::None)
        {
            parent::result(array('deleted' => true, 'secret_id' => $secretId));
        }
        else
        {
            return parent::error($ret, '');
        }
    }

    public static function deleteSecret($secretId, $curUserId, $checkOwner)
    {
        // Gte secret from MySQL (for saving status in DB)
        $secret = Secret::findById($secretId);
        if ($secret === false)
        {
            return Error::BadRecord;
        }

        if ($checkOwner)
        {
            if ($secret->user_id != $curUserId)
            {
                return Error::DeleteSecretFailedForOwnership;
            }
            $secret->status = 2;
        }
        else
        {
            $secret->status = 3;
        }

        if ($secret->save() === false)
        {
            return Error::BadRecord;
        }

        $imageKey = $secret->background_image;
        if (isset($imageKey) && strpos($imageKey, '__') === 0)
        {
            // Now, only delete the image uploaded by dev.
            ImageController::deleteImage($imageKey);
        }

        $comments = Comment::getComments($secretId);
        $noticeArray = array();
        foreach ($comments as &$comment)
        {
            array_push($noticeArray, $comment->user_id);
        }

        // TODO: Do notice for deletion

        Cache::removeSecretFromSchoolSecretStream($secret->school_id, $secretId);
        return Error::None;
    }

    private static function reportSecret($userId, $secretId, $reason)
    {
        $secretReport = new SecretReport();
        $secretReport->secret_id = $secretId;
        $secretReport->user_id = $userId;
        $secretReport->reason = $reason;
        $ret = $secretReport->save();
        if ($ret)
        {
            return true;
        }
        return false;
    }

    // Like
    public function likeAction($secretId)
    {
        if (!$this->request->isGet())
        {
            return parent::error(Error::BadHttpMethod, '');
        }

        $userId = $this->curUserId;
        $secretLiked = new SecretLiked();
        $secretLiked->secret_id = $secretId;
        $secretLiked->user_id = $userId;

        if ($secretLiked->save() != false)
        {
            Cache::addUserLikedSecret($secretId, $userId);

            $secretOwnerId = Secret::getUserId($secretId);
            if ($userId != $secretOwnerId)
            {
                NoSql::updateSecretLikedNotice($secretOwnerId, $secretId, $userId, true);
            }

            return parent::result(array('liked' => true, 'secret_id' => $secretId));
        }
        else
        {
            return parent::error(Error::BadRecord, '');
        }
    }

    // Cancel like
    public function cancelLikeAction($secretId)
    {
        if (!$this->request->isGet())
        {
            return parent::error(Error::BadHttpMethod, "");
        }

        $userId = $this->curUserId;

        $condition = "secret_id = $secretId and user_id = $userId";
        $secretLiked = SecretLiked::find(array($condition));
        
        if ($secretLiked->delete() != false)
        {
            Cache::delUserLikedSecret($secretId, $userId);

            $secretOwnerId = Secret::getUserId($secretId);
            NoSql::updateSecretLikedNotice($secretOwnerId, $secretId, $userId, false);

            return parent::result(array('liked' => false, 'secret_id' => $secretId));
        }
        else
        {
            return parent::error(Error::BadRecord, '');
        }
    }

    // Report a secret!
    public function reportAction($secretId)
    {
        if (!$this->request->isPost())
        {
            return parent::error(Error::BadHttpMethod, '');
        }

        $secret = Secret::findById($secretId);
        if ($secret === false)
        {
            return parent::error(Error::BadRecord, 'Can NOT find the secret');
        }

        $reason = $this->request->getPost('reason');
        $userId = $this->curUserId;
        self::removeSecret($userId, $secretId);
        if (self::reportSecret($userId, $secretId, $reason))
        {
            $secret->report += 1;
            if ($secret->save() !== false)
            {
                return parent::result(array('report' => true, 'secret_id' => $secretId));
            }
        }

        return parent::error(Error::BadRecord, "Can NOT save the secret");
    }

    public function markAction($secretId)
    {
        if (!$this->request->isGet())
        {
            return parent::error(Error::BadHttpMethod, '');
        }

        $ret = self::mark($secretId, $this->curUserId);
        return parent::result(array('mark' => 1, 'ret' => $ret));
    }

    public static function mark($secretId, $userId)
    {
        $ret = Cache::addSecretMark($secretId, $userId);

        if ($ret)
        {
            $ret = Batch::addSecretMark($secretId, $userId);
        }
        return $ret;
    }

    public function unmarkAction($secretId)
    {
        if (!$this->request->isGet())
        {
            return parent::error(Error::BadHttpMethod, '');
        }
        $userId = $this->curUserId;
        Cache::delSecretMark($secretId, $userId);
        Batch::delSecretMark($secretId, $userId);

        $condition = "secret_id=$secretId and user_id=$userId";
        $mark = SecretMark::findFirst(array($condition));
        if ($mark !== false)
        {
            $mark->delete();
        }
        return parent::result(array('mark' => 0));
    }

    // Latest secrets
    private function fetchLatestSecrets($schoolId)
    {
        $userId = $this->curUserId;

        $ret = array();
        $count = 0;
        $secretIdArray = Cache::getSchoolSecretsStream($schoolId);

        foreach ($secretIdArray as $secretId)
        {
            $secret = Secret::getSecret($secretId);
            if ($secret === false)
            {
                continue;
            }

            if (self::assembleSecret($userId, &$secret))
            {
                array_push($ret, $secret);

                $count++;
                if ($count >= Config::SecretsCount)
                {
                    break;
                }
            }
        }

        return $ret;
    }

    private function fetchExtraSecrets($count)
    {
        $ret = Cache::getRecommendedSecretStream($count);
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
                if ($secret !== false && self::assembleSecret($userId, &$secret))
                {
                    array_push($ret, $secret);

                    $count++;
                    if ($count >= Config::SecretsCount)
                    {
                        break;
                    }
                }
            }
        }
        else
        {
            $secrets = Cache::fetchSecretsBefore($schoolId, $minSecretId);
            $ret = array();
            $count = 0;
            foreach ($secrets as $secret)
            {
                $secretId = $secret->secret_id;
                if (self::assembleSecret($userId, &$secret))
                {
                    array_push($ret, $secret);
                    $count++;
                }

                if ($count >= Config::SecretsCount)
                {
                    break;
                }
            }
            return $ret;
        }

        return $ret;
    }

    // $secrets is an array of json strings
    // and return an array of PHP objects
    public static function assembleSecret($curUserId, &$secret)
    {
        $secretId = $secret->secret_id;
        $removed = Cache::isUserRemovedSecret($secretId, $curUserId);
        if ($removed)
        {
            return false;
        }

        $imageKey = $secret->background_image;
        if (isset($imageKey))
        {
            $secret->background_image = ImageController::getDownloadUrl($imageKey);
            unset($secret->background);
        }
        else
        {
            $secret->background = (int)$secret->background;
            unset($secret->background_image);
        }

        if ($curUserId == $secret->user_id)
        {
            $secret->secret_owner = 1;
        }

        $secret->liked_count = 0;
        $usersLikedSecret = Secret::getUsersLikedSecret($secretId);
        if ($usersLikedSecret != false)
        {
            // For the array contains a user_id == 0 as placeholder.
            $secret->liked_count = count($usersLikedSecret) - 1;
            if (array_search($curUserId, $usersLikedSecret) !== false)
            {
                $secret->iliked = 1;
            }
        }

        if ($secret->school_id == parent::getSchoolId())
        {
            $academyName = Cache::getAcademyName($secret->academy_id);
            $secret->academy = $academyName;
        }
        else
        {
            $schoolName = Cache::getSchoolName($secret->school_id);
            $secret->school = $schoolName;
        }

        $secret->comment_count = Comment::getCount($secretId);

        if (Cache::isSecretMark($secretId, $curUserId))
        {
            $secret->mark = 1;
        }

        unset($secret->user_id);
        unset($secret->secret_key);
        unset($secret->status);
        unset($secret->report);
        unset($secret->time);
        return true;
    }

    // TODO:
    private function sortSecrets(&$allItems, $schoolId, $academyId, $grade)
    {
        $array1 = array();
        $array2 = array();
        $array3 = array();
        $array4 = array();
        foreach ($allItems as &$item)
        {
            if ($item->academy_id == $academyId && $item->grade == $grade)
            {
                array_push($array1, $item);
            }
            else if ($item->achool_id != $schoolId)
            {
                array_push($array2, $item);
            }
            else if ($item->academy_id == $academyId || $item->grade == $grade)
            {
                array_push($array3, $item);
            }
            else if ($item->academy_id != $academyId && $item->grade != $grade)
            {
                array_push($array4, $item);
            }
        }

        if (count($array1) > 0)
        {
            return array_merge($array1, $array2, $array3, $array4);
        }
        else
        {
            return array_merge($array3, $array4, $array2);
        }
    }


}
