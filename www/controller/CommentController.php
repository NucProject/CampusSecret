<?php

class CommentController extends ApiController
{
    public function initialize()
    {
        parent::initializeAction();
        parent::startSession();
    }

    // Fetch all comments in a secret, with count of each comment, floor, and whether I liked.
    // Also hide the comments removed by secret owner.
    public function fetchAction($secretId)
    {
        if (!$this->request->isGet())
        {
            return parent::error(Error::BadHttpMethod, '');
        }

        if (Cache::isUserRemovedSecret($secretId, $this->curUserId) ||
            Secret::isDeleted($secretId))
        {
            return parent::error(Error::FetchFailedForSecretDeleted, 'Secret deleted');
        }

        $items = Cache::getComments($secretId);
        if ($items !== false)
        {
            $secretOwnerId = Secret::getUserId($secretId);
            $comments = array();
            foreach ($items as $item)
            {
                $comment = json_decode($item);
                if ($comment->removed)
                    continue;
                self::assembleComment(&$comment, $secretId, $this->curUserId, $secretOwnerId);
                array_push($comments, $comment);
            }

            NoSql::setMyVisit($this->curUserId, $secretId, count($items));
            return parent::result(array('items' => $comments));
        }
        return parent::error(Error::FetchCommentsFailed, '');
    }

    // Post a comment in a secret.
    // 
    public function postAction($secretId)
    {
    	if (!$this->request->isPost())
        {
            return parent::error(Error::BadHttpMethod, "");
        }

        $payload = $this->request->getPost();
        $content = $payload['content'];
        $bottom = $payload['bottom'];

        if (trim($content) == "")
        {
            return parent::error(Error::SecretPostInvalidContent, "The content is empty");
        }

        $items = Comment::getComments($secretId);
        if ($items === false)
        {
            return parent::error(Error::BadRecord, "");
        }

        $comments = array();
        foreach ($items as &$item)
        {
            $comment = json_decode($item);
            array_push($comments, $comment);
        }

        $time = date('Y-m-d H:i:s', time());
        $userId = $this->curUserId;

        $results = self::postComment($userId, $secretId, &$comments, $content, $time, $this->curSchoolId, $this->curAcademyId, $this->curGrade, $bottom);

        if ($results !== false)
        {
            NoSql::setMyVisit($userId, $secretId, $results['floor']);

            return parent::result($results);
        }
        else
        {
            return parent::error(Error::BadRecord, '');
        }
    }

    public static function postComment($userId, $secretId, &$comments, $content, $time, $schoolId, $academyId, $grade, $bottom = 0)
    {

        $avatarId = self::getRandomAvatarId($secretId, $userId, &$comments);

        $floor = Cache::getNewFloor($secretId);

        $newComment = new Comment();

        $newComment->user_id = $userId;
        $newComment->secret_id = $secretId;
        $newComment->avatar_id = $avatarId;
        $newComment->floor = $floor;
        $newComment->time = $time;
        $newComment->content = $content;
        $newComment->removed = 0;
        $newComment->school_id = $schoolId;
        $newComment->academy_id = $academyId;
        $newComment->grade = $grade;

        if ($newComment->save() != false)
        {
            Cache::setComment($secretId, $newComment, $floor);

            // 提醒和推送的users $noticeArray
            $noticeArray = Cache::getSecretMark($secretId);
            $secretOwnerId = Secret::getUserId($secretId);
            if ($userId != $secretOwnerId)
            {
                array_push($noticeArray, $secretOwnerId);   //Notice Secret owner (If I'm not the owner)
            }
            $noticeArray = array_unique($noticeArray);

            // 评论既关注
            SecretController::mark($secretId, $userId);

            // 提醒相关
            NoSql::addNotices($secretId, $noticeArray, time());

            // 推送相关
            /*
            $time = time();
            $push = "$secretId:$time";
            foreach ($noticeArray as $pushedUserId)
            {
                $pushType = 4;          //"你关注的秘密有了新评论"
                if ($pushedUserId == $secretOwnerId)
                {
                    if($secretOwnerId !== $userId)
                    {
                        $pushType = 2;  //"有人评论了你的秘密"
                        Push::singlePushPerform($pushedUserId, $pushType, $push);
                    }
                }
                else
                {
                    if($pushedUserId !== $userId)
                    {
                        Push::singlePushPerform($pushedUserId, $pushType, $push);
                    }
                }
            }*/

            $items = array();
            array_push($comments, $newComment);
            $bottom = intval($bottom);
            foreach ($comments as &$comment)
            {
                // Pack the comments between bottom and new comment.
                if (intval($comment->floor) > $bottom)
                {
                    if (self::assembleComment(&$comment, $secretId, $userId, $secretOwnerId))
                    {
                        array_push($items, $comment);
                    }
                }
            }
            return array('items' => $items, 'floor' => $floor, 'post' => true);
        }
        else
        {
            Cache::discardComment($secretId, $floor);
            return false;
        }
    }

    public function likeAction($secretId, $commentId)
    {
        if (!$this->request->isGet())
        {
            return parent::error(Error::BadHttpMethod, '');
        }

        $floor = $this->request->getQuery('floor');
        if (!isset($floor))
        {
            return parent::error(Error::BadArguments, 'floor is required');
        }

        $comment = Comment::getComment($secretId, $commentId, $floor);
        if ($comment !== false)
        {
            $commentLiked = new CommentLiked();
            $commentLiked->comment_id = $commentId;
            $commentLiked->user_id = $this->curUserId;
            if ($commentLiked->save() != false)
            {
                Cache::addUserLikedComment($commentId, $this->curUserId);

                if ($this->curUserId != $comment->user_id)
                {
                    NoSql::updateCommentLikedNotice($comment->user_id, $secretId, true);
                }

                return parent::result(array('liked' => true, 'comment_id' => $commentId));
            }
            else
            {
                return parent::error(Error::BadRecord, '');
            }
        }
        else
        {
            return parent::error(Error::LikedCommentTwice, 'Duplicated like one same comment');
        }
    }


    public function cancelLikeAction($secretId, $commentId)
    {
        if (!$this->request->isGet())
        {
            return parent::error(Error::BadHttpMethod, "");
        }

        $userId = $this->curUserId;
        $condition = "comment_id = $commentId and user_id = $userId";
        $commentLiked = CommentLiked::find(array($condition));

        if (count($commentLiked) > 0 && $commentLiked->delete() != false)
        {
            Cache::delUserLikedComment($commentId, $userId);
            return parent::result(array("liked" => false, "comment_id" => $commentId));
        }
        else
        {
            return parent::error(Error::BadRecord, "Can NOT found liked to this comment.");
        }
    }


    public function deleteAction($secretId, $commentId)
    {
        if (!$this->request->isGet())
        {
            return parent::error(Error::BadHttpMethod, "");
        }

        $ret = self::deleteComment($secretId, $commentId, $this->curUserId, false);
        if ($ret === Error::BadRecord)
        {
            return parent::error(Error::BadRecord, "");
        }
        else if ($ret === Error::DeleteCommentFailedForOwnership)
        {
            return parent::error(Error::DeleteCommentFailedForOwnership, "Only comment or secret owner can delete");
        }
        else
        {
            if ($ret['deleted'] == true)
            {
                return parent::result($ret);
            }
        }
    }

    // For Admin user please provide the right $curUserId
    public static function deleteComment($secretId, $commentId, $curUserId, $delByAdmin = false)
    {
        $comment = Comment::findById($commentId);
        if ($comment === false || $comment->secret_id != $secretId)
        {
            return Error::BadRecord;
        }

        if ($curUserId == $comment->user_id || $curUserId == Secret::getUserId($secretId) || $delByAdmin)
        {
            $comment->removed = 1;
            if ($comment->save())
            {
                Cache::deleteComment($secretId, $comment->floor);
                return array('deleted' => true, 'comment_id' => $commentId);
            }
            else
            {
                return Error::BadRecord;
            }
        }
        return Error::DeleteCommentFailedForOwnership;
    }

    public function reportAction($secretId, $commentId)
    {
        if (!$this->request->isPost())
        {
            return parent::error(Error::BadHttpMethod, '');
        }

        $reason = $this->request->getPost('reason');

        $userId = $this->curUserId;

        $commentReport = new CommentReport();
        $commentReport->comment_id = $commentId;
        $commentReport->user_id = $userId;
        $commentReport->reason = $reason;

        if ($commentReport->save() != false)
        {
            return parent::result(array('reported' => true));
        }
        else
        {
            return parent::error(Error::BadRecord, '');
        }
    }

    // If I have comment(s) in $comments, use that avatar;
    // Otherwise generate a random one.
    private static function getRandomAvatarId($secretId, $userId, &$comments)
    {
        $avatarId = self::getUsedAvatarId(&$comments, $userId);
        if ($avatarId === false)
        {
            $avatarId = Cache::getRandomAvatarId($secretId, &$comments);
        }

        return $avatarId;
    }

    private static function assembleComment(&$comment, $secretId, $curUserId, $secretOwnerId)
    {
        if ($comment->removed)
        {
            return false;
        }

        if ($secretOwnerId == $comment->user_id)
        {
            $comment->secret_owner = 1;
        }

        if ($curUserId == $comment->user_id)
        {
            $comment->mine = 1;
        }

        $comment->liked_count = 0;
        $commentId = $comment->comment_id;
        $usersLikedComments = Cache::getUsersLikedComment($commentId);
        if ($usersLikedComments !== false)
        {
            $comment->liked_count = count($usersLikedComments);
            if (array_search($curUserId, $usersLikedComments) !== false)
            {
                $comment->iliked = 1;
            }
        }

        // Server would NOT calc the relative time.
        // $comment->rel_time = self::relativeTime(date("Y-m-d H:i:s", time()), $comment->time);
        $comment->floor = intval($comment->floor);
        $comment->avatar = parent::convert($comment->avatar_id);

        unset($comment->avatar_id);
        unset($comment->user_id);
        unset($comment->removed);
        unset($comment->secret_id);
        unset($comment->school_id);
        unset($comment->academy_id);
        unset($comment->grade);
        return true;
    }

    private static function getUsedAvatarId(&$comments, $userId)
    {
        foreach ($comments as &$comment)
        {
            if ($comment->user_id == $userId)
            {
                return $comment->avatar_id;
            }
        }
        return false;
    }

}
