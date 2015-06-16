<?php
/**
 * Created by PhpStorm.
 * User: yuzhongmin
 * Date: 14-6-11
 * Time: 上午10:14
 */

class RedisController extends ApiController
{
    public function initialize()
    {
        parent::initializeAction();
        parent::startAdminSession();
    }

    public function saveAction()
    {
        // TODO:
    }

    public function memoryAction()
    {
        $info = $this->redis->info();

        foreach ($info as $k => $i)
        {
            if (strpos($k, '# Memory') !== false)
            {
                return parent::result(array('memory' => $i / 1024));
            }
        }
    }

    // Sorry for $securityCode;
    public function clearAction($object, $securityCode)
    {
        if ($securityCode == self::convert(time() / 23))
        {
            return parent::result(array('clear' => $object::clear()));
        }
        return parent::error(Error::BadArguments, 'Bad security code');
    }

    public function sessionAction($p1, $p2)
    {
        $redis = NoSql::getCacheObject();
        $keys = $redis->keys('PHPREDIS_SESSION:*');

        if ($p1 == "count")
        {
            return parent::result(array("count" => count($keys), "items" => $keys));
        }
        else if ($p1 == "uid")
        {
            if (!isset($p2))
            {
                return parent::error(Error::BadArguments, 'Please provide the Uid');
            }

            foreach ($keys as $key)
            {
                $content = $redis->Get($key);

                $l = count($p2);
                $parts = "uid|s:$l:\"$p2\"";
                $pos = strpos($content, $parts);
                if ($pos !== false)
                {
                    return parent::result(array("found" => true, "content" => $content, "pos" => $pos));
                }
            }
            return parent::result(array("found" => false));
        }
        else if ($p1 == "check")
        {
            foreach ($keys as $key)
            {
                if (strlen($key) < 20)
                {
                    $content = $redis->Get($key);
                    echo "INVALID SESSION: $key => $content";
                }
            }
        }
    }

}