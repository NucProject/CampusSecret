<?php

class Comment extends \Phalcon\Mvc\Model
{
    const LocalAvatarCount = 100;

    const AllAvatarCount = 662;

	public static function findById($commentId)
	{
		return Comment::findFirst($commentId);
	}

    public static function getComment($secretId, $commentId, $floor)
    {
        $comment = Cache::getComment($secretId, $floor);
        if ($comment !== false)
        {
            return $comment;
        }
        return Comment::findFirst($commentId);
    }

    public static function getComments($secretId)
    {
        return Cache::getComments($secretId);
    }

    public static function getCount($secretId)
    {
        $ret = Cache::getCommentCount($secretId);
        if ($ret !== false)
        {
            if ($ret == Key::Deleted)
            {
                return 0;
            }
            return (int)$ret;
        }
        else
        {
            $count = Comment::count(array("secret_id=$secretId"));
            // TODO: Check the secret whether deleted.
            return $count;
        }
    }
}

