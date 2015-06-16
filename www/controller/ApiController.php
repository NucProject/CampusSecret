<?php

class ApiController extends \Phalcon\Mvc\Controller
{
    protected $curUserId;

    protected $curSchoolId;

    protected $curAcademyId;

    protected $curGrade;

    protected $debug = false;

    private $beginTime;

    protected $attach;

    private static $s = 1;

	public function initialize()
    {
        $this->view->disable();
    }

    public function initializeAction()
    {
        Cache::initialize($this->modelsManager);
        NoSql::initialize($this->modelsManager);
        $this->view->disable();
    }

    public function startSession()
    {
        $sessionId = $_COOKIE['PHPSESSID'];
        if (!isset($sessionId) || strlen($sessionId) == 0)
        {
            $this->error(Error::BadSession, 'Bad session id');
            $this->exitScript();
        }

        session_start();

        $this->curUserId = self::getUserId();
        if ($this->curUserId === false)
        {
            session_destroy();
            $this->error(Error::BadSession, 'Bad session content');
            $this->exitScript();
        }

        $this->curSchoolId = self::getSchoolId();
        $this->curAcademyId = self::getAcademyId();
        $this->curGrade = self::getGrade();

        if ($_SESSION['debug'] == '1')
        {
            $this->debug = true;
            $this->beginTime = microtime(true);
        }
    }

    public function startAdminSession()
    {
        session_start();

        $this->curUserId = self::getUserId();
        $isAdmin = $_SESSION['admin'];

        if ($this->curUserId !== false && isset($isAdmin))
        {
            if ($_SESSION['debug'] == '1')
            {
                $this->debug = true;
                $this->beginTime = microtime(true);
            }
            return true;
        }
        $this->error(Error::BadSession, "Bad session");
        $this->exitScript();
    }

	// return results
	public function result($results)
	{
		$ret = array(
			"errorCode" => Error::None, 
			"results" => $results);

        if ($this->debug)
        {
            $timeConsuming = microtime(true) - $this->beginTime;
            $ret = array_merge($ret, array('consuming' => $timeConsuming, 'debug' => 1));

            // For extra echo messages ending
            echo '--END-- ';
        }
		echo json_encode($ret);
		return true;
	}

	// echo Error message
	public function error($errorCode, $errorMessage)
	{
		$ret = array(
			"errorCode" => $errorCode, 
			"errorMessage" => $errorMessage);
        if ($this->debug)
        {
            echo '--END--';
        }
		echo json_encode($ret);
		return true;
	}

    public static function code2userId($userCode, $secretId)
    {
        $key = '<:' . $secretId . '\'';
        return Crypt::decrypt($userCode, $key);
    }

    public function userId2code($userId, $secretId)
    {
        $key = '<:' . $secretId . '\'';
        return Crypt::encrypt(strval($userId), $key);
    }

	// Get user-id or die()
	public static function getUserId()
	{
		$userId = $_SESSION['uid'];
        if (!isset($userId))
        {
            return false;
        }
        return $userId;
	}

    public static function getSchoolId()
    {
        return $_SESSION['school_id'];
    }

    public static function getAcademyId()
    {
        return $_SESSION['academy_id'];
    }

    public static function getGrade()
    {
        return $_SESSION['grade'];
    }

    public function getAction($type)
    {
        if ($this->request->isGet())
        {
            if ($type == "phpinfo")
            {
                echo phpinfo();
            }
            else if ($type == "memory")
            {
                // TODO: Return the server memory status
            }
        }
    }

    public static function convert($a)
    {
        $v1 = 360 + intval($a);
        $v = "[$v1.0]";
        return md5($v);
    }

    public function codeAction()
    {
        $a = time() / 23;
        return self::result(array('code' => self::convert($a)));
    }

    public static function relativeTime($time1, $time2)
    {
        $p1 = date_parse_from_format("Y-m-d H:i:s", $time1);
        $p2 = date_parse_from_format("Y-m-d H:i:s", $time2);
        $t1 = mktime($p1['hour'], $p1['minute'], $p1['second'], $p1['month'], $p1['day'], $p1['year']);
        $t2 = mktime($p2['hour'], $p2['minute'], $p2['second'], $p2['month'], $p2['day'], $p2['year']);

        $minuted = (int)(($t1 - $t2) / 60);
        if ($minuted == 0)
        {
            return '刚刚';
        }
        else if ($minuted > 0 && $minuted < 60)
        {
            return $minuted . '分钟前';
        }
        else if ($minuted >= 60 && $minuted < (60 * 24))
        {
            return (int)($minuted / 60) . '小时前';
        }
        else
        {
            $diff = (int)($minuted / 60 / 24);
            $days = $p1['day'] - $p2['day'];
            if ($days == 1 && $diff == 1)
            {
                return '昨天';
            }
            else if ($days > 1 && $days <= 30 && $diff <= 30)
            {
                return $days . '天前';
            }
            else
            {
                if ($p1['year'] == $p2['year'])
                {
                    return $p2['month'] . '月' . $p2['day'] . '日';
                }
                else
                {
                    return $p2['year'] . '年' . $p2['month'] . '月' . $p2['day'] . '日';
                }
            }
        }
    }

    public static function parseTime($time)
    {
        $parsed = date_parse_from_format("Y-m-d H:i:s", $time);
        $ret = mktime(
            $parsed['hour'],
            $parsed['minute'],
            $parsed['second'],
            $parsed['month'],
            $parsed['day'],
            $parsed['year']
        );
        return $ret;
    }

    public function getPushResultAction($taskId)
    {

    }

    public function parseXmlAction()
    {
        $x = simplexml_load_string('<?xml version="1.0" encoding="gb2312"?><Root><Result>1</Result><SendNum>1</SendNum></Root>');
        echo $x->Result == 3;
    }

    public static function binarySearch($secretIdArray, $secretId)
    {
        $count = count($secretIdArray);
        $low = 0;
        $high = $count - 1;
        $loop = 0;
        while($low <= $high)
        {
            $loop++;
            if ($loop > 10)
            {
                // loops 10 times should be enough for search in 1000+ items,
                // but cache is always shorter than 1000, so must quit from while loop;
                return false;
            }

            $middle = floor(($low + $high) / 2);

            $i = $secretIdArray[$middle];

            if($i == $secretId)
                return $middle;
            else if($i < $secretId)
                $high = $middle - 1;
            else
                $low = $middle + 1;
        }
        return false;
    }

    public function envAction()
    {
        echo json_encode(array(
            'env' => Config::$env
        ));
    }

    public function testAction()
    {


    }


    public static function pushUserTest()
    {
        $data = self::groupcastData();
        self::sendPushPost($data);
    }

    public static function getAlias($userId)
    {

    }

    public static function isValidPhoneNumber($phoneNum)
    {
        if (strlen($phoneNum) != 11)
            return false;
        $s = str_split($phoneNum);
        if ($s[0] == '1')
        {
            return
                $s[1] == '3' ||
                $s[1] == '4' ||
                $s[1] == '5' ||
                $s[1] == '7' ||
                $s[1] == '8' ||
                $s[1] == '9';
        }
        return false;
    }

	public function exitScript()
	{
		die();
	}


}
