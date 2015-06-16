<?php

class UserController extends ApiController
{
    const FirstTimeSetSchoolInfo = 0;

    const SecondTimeSetSchoolInfo = 1;

    public function initialize()
    {
        parent::initializeAction();
		$this->view->disable();
    }

    public function fetchVerifyCodeAction($phoneNum, $type)
    {
        if (!$this->request->isGet())
        {
            return parent::error(Error::BadHttpMethod, '');
        }

        if (parent::isValidPhoneNumber($phoneNum))
        {
            $username = $phoneNum;

            if (!isset($type) || $reset == 'register')
            {
                // If user exists, return Error
                $users = User::find(array("username='$username'"));

                if (count($users) > 0)
                {
                    return parent::error(Error::RegisterFailedForPhoneNumberExists, 'The username has been registered');
                }
            }
            else if ($type == 'reset')
            {
                // If user NOT exists, return Error
                $users = User::findFirst(array("username='$username'"));
                if ($users == false)
                {
                    return parent::error(Error::ResetPasswordFailedForPhoneNumberNotExists, 'The username has not been registered');
                }
            }

            ///////////////////////////////////////////////////////////////////
            // For test user, a test use MUST begin with '1390000'.
            if (strpos($phoneNum, '1390000') === 0)
            {
                $verifyCode = '1111';
                $verifyCodeKey = Key::UserVerifyCode . $phoneNum;
                Cache::getCacheObject()->setex($verifyCodeKey, 1800, $verifyCode);
                return parent::result(array('verify' => 'Sent'));
            }
            ///////////////////////////////////////////////////////////////////

            $verifyCode = Cache::saveVerifyCode($phoneNum);
            if ($verifyCode == false)
            {
                parent::error(Error::SendVerifyCodeFailedForFrequency, 'Verify code can NOT be resend within 120 seconds');
            }

            $result = ShortMsg::sendVerifyCode($phoneNum, $verifyCode);
            if ($result !== false)
            {
                return parent::result($result);
            }
            else
            {
                return parent::error(Error::RegisterFailedForNotSendVerifyCode, '');
            }
        }
    }

    public function fetchVerifyCode2Action($phoneNum)
    {
        if ($this->request->isGet())
        {
            $verifyCodeKey = Key::UserVerifyCode . $phoneNum;
            echo $this->redis->Get($verifyCodeKey);
        }
    }

    public function registerAction()
    {
    	if (!$this->request->isPost())
        {
            return parent::error(Error::BadHttpMethod, '');
        }

        $payload = $this->request->getPost();
        $username = $payload['username'];
        $passwordMD5 = $payload['password_hash'];
        $platform = self::getPlatform($payload['platform']);

        $verifyCode = $payload['verify-code'];
        $cacheCode = Cache::loadVerifyCode($username);

        if (!isset($verifyCode) || $verifyCode != $cacheCode)
        {
            return parent::error(Error::OperationFailedForInvalidVerifyCode, 'Invalid verification code');
        }

        // Remove the code, avoid register multiple users with one verification code.
        // $this->redis->del($verifyCodeKey);
        // $this->redis->del($verifyCtrlKey);

        if (isset($username) && isset($passwordMD5))
        {
            $users = User::find(array("username='$username'"));
            if (count($users) == 0)
            {
                $user = new User();
                $user->username = $username;
                $user->passwd_hash = self::hashPassword($passwordMD5);
                $user->school_id = 0;
                $user->academy_id = 0;
                $user->platform = $platform;
                $user->grade = 0;
                $user->time = date('Y-m-d H:i:s', time());
                $user->change = 0;
                $user->status = 0;

                $ret = $this->signInUser($user, $platform);

                $debug = $payload['_debug'];
                if (isset($debug) && $debug == '1')
                {
                    $_SESSION['debug'] = '1';
                }

                if ($ret['signin'] == true)
                {
                    return parent::result($ret);
                }
                else
                {
                    return parent::error(Error::BadRecord, '');
                }
            }
            else
            {
                return parent::error(Error::RegisterFailedForPhoneNumberExists, 'This username has been registered');
            }
        }
        else
        {
            return parent::error(Error::RegisterFailedForBadUsernameOrPassword, 'Both username and password are required');
        }
    }

    public function finishRegisterAction()
    {
        if (!$this->request->isPost())
        {
            return parent::error(Error::BadHttpMethod, '');
        }

        parent::startSession();
        $userId = $this->curUserId;
        $errorCode = 0;
        $ret = self::updateSchoolInfo($this->curUserId, $this->request->getPost(), self::FirstTimeSetSchoolInfo, &$errorCode);
        if ($ret !== false)
        {
            return parent::result($ret);
        }
        else
        {
            return parent::error($errorCode, '');
        }
    }

    public function changeInfoAction()
    {
        if (!$this->request->isPost())
        {
            return parent::error(Error::BadHttpMethod, '');
        }

        parent::startSession();
        $errorCode = 0;
        $ret = self::updateSchoolInfo($this->curUserId, $this->request->getPost(), self::SecondTimeSetSchoolInfo, &$errorCode);
        if ($ret !== false)
        {
            return parent::result($ret);
        }
        else
        {
            return parent::error($errorCode, '');
        }
    }

    private static function updateSchoolInfo($userId, $payload, $count, &$errorCode)
    {
        $schoolId = $payload['school_id'];
        $academyId = $payload['academy_id'];
        $grade = $payload['grade'];

        if (isset($schoolId) && isset($academyId) && isset($grade))
        {
            $user = User::findFirst($userId);
            if ($user !== false)
            {
                $change = (int)$user->change;
                // echo json_encode($user);
                if ($change != 0)
                {

                    $errorCode = Error::ChangeSchoolInfoOverTwice;
                    return false;
                }

                $user->school_id = $schoolId;
                $user->academy_id = $academyId;
                $user->grade = $grade;
                $user->change = $count;

                if ($user->save() != false)
                {
                    $_SESSION['school_id'] = $schoolId;
                    $_SESSION['academy_id'] = $academyId;
                    $_SESSION['grade'] = $grade;

                    $academyName = Cache::getAcademyName($academyId);
                    // TODO: Remove all add School name

                    return array(
                        'changed' => true,
                        'school_id' => $schoolId,
                        'academy_id' => $academyId,
                        'academy' => $academyName,
                        'grade' => $grade,
                        'change' => $count
                    );
                }
                else
                {
                    $errorCode = Error::BadRecord;
                    return false;
                }
            }
        }

        $errorCode = Error::BadPayload;
        return false;
    }


    // SignIn with your username and password
    public function signInAction()
    {
        if (!$this->request->isPost())
        {
            return parent::error(Error::BadHttpMethod, '');
        }

        $payload = $this->request->getPost();
        $username = $payload['username'];
        $passwordMD5 = $payload['password_hash'];
        $platform = self::getPlatform($payload['platform']);

        if (isset($username) && isset($passwordMD5))
        {
            $passwdHash = self::hashPassword($passwordMD5);
            $user = User::findFirst(array("username='$username' AND passwd_hash='$passwdHash'"));
            if ($user != false)
            {
                $ret = $this->signInUser($user, $platform);

                $debug = $payload['_debug'];
                if (isset($debug))
                {
                    if ($debug == '1')
                    {
                        $_SESSION['debug'] = '1';
                    }
                }

                if ($ret['signin'] == true)
                {
                    return parent::result($ret);
                }
                else
                {
                    return parent::error(Error::BadRecord, '');
                }
            }
            else
            {
                return parent::error(Error::AuthFailed, 'Wrong username or password');
            }
        }
        else
        {
            return parent::error(Error::BadPayload, 'Both username and password are required');
        }
    }

    private function signInUser($user, $platform)
    {
        // TODO: Delete previous session in redis and Make the user SignOut!
        $prevSessionId = $user->session;
        // ...

        session_start();
        session_regenerate_id(true);

        $sessionId = session_id();
        $user->session = $sessionId;
        $user->platform = $platform;
        if ($user->save())
        {
            $userId = $user->user_id;
            $_SESSION['uid'] = $userId;
            if ($user->school_id != 0)
            {
                $_SESSION['school_id'] = $user->school_id;
                $_SESSION['academy_id'] = $user->academy_id;
                $_SESSION['grade'] = $user->grade;
                $_SESSION['platform'] = $platform;

                $schoolName = Cache::getSchoolName($user->school_id);
                $academyName = Cache::getAcademyName($user->academy_id);

                Push::addPushUser($userId, $platform, $user->school_id, $user->academy_id, $user->grade);
                return array(
                    'user_id' => $userId,
                    'school_id' => $user->school_id,
                    'academy_id' => $user->academy_id,
                    'grade'=> $user->grade,
                    'school' => $schoolName,
                    'academy' => $academyName,
                    'modify' => (int)$user->change,
                    'signin' => true
                );
            }
            else
            {
                return array(
                    'user_id' => $userId,
                    'signin' => true,
                    'register' => 'NotCompleted');
            }
        }
        else
        {
            return array('user_id' => $userId, 'signin' => false);
        }
    }

    public function signOutAction()
    {
        if ($this->request->isGet())
        {
            session_start();
            Push::delPushUser($this->curUserId, $platform);
            session_destroy();
            return parent::result(array('signin' => false));
        }
    }

    // Add sina and renren token
    public function tokenAction()
    {
        if (!$this->request->isPost())
        {
            return parent::error(Error::BadHttpMethod, '');
        }

        parent::startSession();

        $payload = $this->request->getPost();
        $type = $payload['type'];
        $account = $payload['account'];
        $token = $payload['token'];

        if (isset($type) && isset($account) && isset($token))
        {
            $userId = $this->curUserId;
            $userToken = UserToken::findFirst(array("user_id=$userId and account='$account' and type=$type"));
            if ($userToken === false)
            {
                $userToken = new UserToken();
                $userToken->user_id = $this->curUserId;
                $userToken->type = $type;
                $userToken->account = $account;
                $userToken->token = $token;
            }
            else
            {
                // change Token
                $userToken->token = $token;
            }
            if ($userToken->save())
            {
                return parent::result(array('added' => 1));
            }
            else
            {
                return parent::error(Error::BadRecord, '');
            }
        }
        else
        {
            return parent::error(Error::BadPayload, '');
        }
    }

    public function changeAction($type)
    {
        if (!$this->request->isPost())
        {
            return parent::error(Error::BadHttpMethod, '');
        }

        if ($type == 'password')
        {
            $payload = $this->request->getPost();

            $username = $payload['username'];
            $verifyCode = $payload['verify-code'];
            $cacheCode = Cache::loadVerifyCode($username);

            if (!isset($verifyCode) || $verifyCode != $cacheCode)
            {
                return parent::error(Error::OperationFailedForInvalidVerifyCode, 'Invalid verify code');
            }

            if (!isset($username) || $username == '')
            {
                return parent::error(Error::BadPayload, 'Invalid username');
            }

            $user = User::findFirst(array("username='$username'"));
            if ($user !== false)
            {
                $newPasswdMD5 = $payload['password_hash'];
                $passwdHash = self::hashPassword($oldPasswdMD5);
                $user->passwd_hash = self::hashPassword($newPasswdMD5);
                if ($user->save() !== false)
                {
                    $this->curSchoolId = self::getSchoolId();
                    $this->curAcademyId = self::getAcademyId();
                    $this->curGrade = self::getGrade();

                    return parent::result(array('changed' => true));
                }
                else
                {
                    return parent::error(Error::BadRecord, '');
                }
            }
            else
            {
                return parent::error(Error::AuthFailed, '');
            }
        }
    }

    private static function hashPassword($password)
    {
        return sha1($password);
    }

    private static function getPlatform($platform)
    {
        if (isset($platform))
        {
            if (strpos($platform, 'android') === 0)
            {
                return 2;
            }
            else if (strpos($platform, 'ios') === 0)
            {
                return 1;
            }
        }
        return 0;
    }

    public static function getRandUser($schoolId)
    {
        $conditions = "school_id=$schoolId";
        $users = User::find(array($conditions));
        $count = count($users);
        if ($count > 0)
        {
            return $users[rand(0, $count - 1)];
        }

        return false;
    }

    /*
    public static function getRandUserId($schoolId, $academyId, $grade = null)
    {
        if (isset($grade))
        {
            $conditions = "school_id=$schoolId and academy_id=$academyId and grade=$grade";
        }
        else
        {
            $conditions = "school_id=$schoolId and academy_id=$academyId";
        }

        $users = User::find(array($conditions));

        $array = array();
        foreach ($users as $user)
        {
            array_push($array, $user);
        }
        $count = count($array);

        if ($count > 0)
        {
            $user = $array[rand(0, $count - 1)];
            return $user->user_id;
        }

        return false;
    }*/


}