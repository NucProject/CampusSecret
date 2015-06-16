<?php

class AdminController extends ApiController
{
    public function initialize()
    {
        Cache::initialize($this->modelsManager);
        NoSql::initialize($this->modelsManager);
    }

    // http://host/admin
    public function indexAction()
    {
        $this->view->pick('admin/admin');
    }

    // http://host/admin/lock
    public function lockAction()
    {
        $this->view->pick('admin/lock');
    }

    // Administrator
    public function signInAction()
    {
        if (!$this->request->isPost())
        {
            return parent::error(Error::BadHttpMethod, '');
        }

        $payload = $this->request->getPost();
        $username = $payload['username'];
        $passwordMD5 = $payload['password_md5'];
        if (isset($username) && isset($passwordMD5))
        {
            $passwordHash = sha1($passwordMD5);
            $admin = Admin::findFirst(array("name='$username' and password_hash='$passwordHash'"));
            if ($admin != false)
            {
                session_start();
                session_regenerate_id(true);
                $adminId = $admin->admin_id;
                $_SESSION['uid'] = $adminId;
                $_SESSION['admin'] = true;
                return parent::result(array('adminSignIn' => 'OK'));
            }
            else
            {
                return parent::error(Error::BadSession, '');
            }
        }
    }

    public function checkUserAction()
    {
        parent::startAdminSession();
        return parent::result(array());
    }

    public function signOutAction()
    {
        if ($this->request->isGet())
        {
            session_start() && session_destroy();
            return parent::result(array('adminSignOut' => 'OK'));
        }
    }

    public function addAdminAction()
    {
        if (!$this->request->isPost())
        {
            return parent::error(Error::BadHttpMethod, '');
        }

        parent::startAdminSession();
        $payload = $this->request->getPost();
        $username = $payload['username'];

        $passwordMD5 = '';
        if (isset($payload['password_md5']))
        {
            $passwordMD5 = $payload['password_md5'];
        }
        else
        {
            $passwordMD5 = md5("$username!ch");
        }

        if (isset($username))
        {
            $passwordHash = sha1($passwordMD5);
            $admin = new Admin();
            $admin->name = $username;
            $admin->password_hash = $passwordHash;
            if ($admin->save())
            {
                return parent::result(array('Admin added' => 'OK', 'name' => $username));
            }
            else
            {

            }
        }
        else
        {

        }
    }

    public function secretsAction()
    {
        if (!$this->request->isPost())
        {
            return parent::error(Error::BadHttpMethod, '');
        }
        parent::startAdminSession();

        $payload = $this->request->getPost();
        $start = $payload['start'];
        $end = $payload['end'];
        $schoolId = $payload['school_id'];
        $report = $payload['report'];

        $allSchools = $payload['all_schools'];
        $deleted = $payload['deleted'];
        $conditions = "time >= '$start' and time <= '$end' and secret_key is null ";

        if(!isset($allSchools) || $allSchools != 'yes')
        {
            $conditions .= " and school_id = $schoolId";
        }

        if (isset($report))
        {
            $conditions .= " and report != 0";
        }

        if (isset($deleted) && $deleted == 'yes')
        {
            $conditions .= " and (status = 2 or status = 3)";
        }
        else
        {
            $conditions .= ' and status = 0';
        }

        $secrets = Secret::find($conditions);

        $ret = array();
        foreach ($secrets as $secret)
        {
            self::assembleSecret(&$secret);

            array_push($ret, $secret);
        }
        return parent::result($ret);
    }

    public function secretCreatedAction()
    {
        if (!$this->request->isPost())
        {
            return parent::error(Error::BadHttpMethod, '');
        }
        parent::startAdminSession();

        $payload = $this->request->getPost();
        $start = $payload['start'];
        $end = $payload['end'];

        $conditions = "time >= '$start' and time <= '$end'";

        $secrets = SecretCreated::find($conditions);
        $ret = array();
        foreach ($secrets as $secret)
        {
            self::assembleSecret(&$secret);

            array_push($ret, $secret);
        }
        return parent::result($ret);
    }

    public static function assembleSecret(&$secret)
    {
        $imageKey = $secret->background_image;
        if (isset($imageKey))
        {
            $secret->background_image = ImageController::getDownloadUrl($imageKey);
            unset($secret->background);
        }
        else
        {
            unset($secret->background_image);
            $secret->background = (int)$secret->background;
        }

        $academyName = Cache::getAcademyName($secret->academy_id);
        $secret->academy = $academyName;

        $schoolName = Cache::getSchoolName($secret->school_id);
        $secret->school = $schoolName;

        return true;
    }


    public function secretKeySchoolsAction()
    {
        if (!$this->request->isPost())
        {
            return parent::error(Error::BadHttpMethod, '');
        }
        parent::startAdminSession();
        $payload = $this->request->getPost();
        $secretKey = $payload['secret_key'];

        $phql =
            'SELECT S.school_id, S.secret_key, SC.name '.
            'FROM Secret S '.
            'left join School SC on S.school_id=SC.school_id '.
            "having S.secret_key = $secretKey";

        $schools = $this->modelsManager->executeQuery($phql);
        $ret = array();
        foreach ($schools as $school)
        {
            array_push($ret, $school);
        }

        return parent::result(array('items' => $ret));
    }

    public function getAllSchoolsAction()
    {
        $schoolIdArray = Cache::getSchoolIdArray();
        echo json_encode($schoolIdArray);
    }

    private function saveSecretKey($secretKey, $content)
    {
        $created = SecretCreated::findFirst(array("secret_key=$secretKey"));
        if ($created === false)
        {
            $created = new SecretCreated();
            $created->time = date('Y-m-d H:i:s', time());
            $created->secret_key = $secretKey;
            $created->content = $content;

            $created->save();
        }
    }

    // Post multi-secrets to the schools in list
    public function postSecretsAction()
    {
        if (!$this->request->isPost())
        {
            return parent::error(Error::BadHttpMethod, '');
        }

        parent::startAdminSession();

        $payload = $this->request->getPost();
        $content = $payload['content'];
        if (trim($content) == '')
        {
            return parent::error(Error::SecretPostInvalidContent, 'The content is empty');
        }

        $schoolIdList = $payload['school_list'];
        $secretKey = $payload['secret_key'];
        if ($schoolIdList == 'check_all_schools')
        {
            $schoolIdArray = Cache::getSchoolIdArray();
        }
        else
        {
            $schoolIdList = trim($schoolIdList, ',');
            $schoolIdArray = explode(',', $schoolIdList);
        }

        $this->saveSecretKey($secretKey, $content);

        $results = array();
        foreach ($schoolIdArray as $schoolId)
        {
            $academyId = null;
            $grade = null;
            $user = UserController::getRandUser($schoolId);

            if ($user === false)
            {
                array_push($results, array($schoolId => 'no_user'));
                continue;
            }

            $backgroundIndex = self::getRandBackgroundIndex();

            $ret = SecretController::postSecret($user->user_id, $content, time(), $schoolId, $user->academy_id, $user->grade, $backgroundIndex, null, $secretKey);
            if ($ret === false)
            {
                array_push($results, array($schoolId => 'bad_post'));
            }
        }

        return parent::result($results);
    }

    // Delete a group a secrets by secret-key!
    public function deleteSecretsAction($secretKey)
    {

        if (!isset($secretKey))
        {
            return parent::error(Error::BadArguments, '');
        }

        parent::startAdminSession();

        $condition = "secret_key=$secretKey";
        $created = SecretCreated::findFirst(array($condition));
        if ($created !== false)
        {
            $secrets = Secret::find(array($condition));
            if (count($secrets) > 0)
            {
                foreach ($secrets as $secret)
                {
                    Cache::removeSecretFromSchoolSecretStream($secret->school_id, $secret->secret_id);
                }
            }
            $created->delete();
            // Delete all;
            $phql = 'DELETE FROM Secret WHERE ' . $condition;
            $this->modelsManager->executeQuery($phql);
            return parent::result(array('deleted' => true));
        }

        return parent::error(Error::BadRecord, '');
    }

    public function commentsAction($secretId)
    {
        if (!$this->request->isGet())
        {
            return parent::error(Error::BadHttpMethod, '');
        }
        parent::startAdminSession();

        $this->view->disable();
        $phql =
            "SELECT C.comment_id, C.user_id, C.content, C.removed, C.time, C.academy_id, C.grade ".
            "FROM Comment AS C ".
            "WHERE C.secret_id=$secretId AND C.removed != 1 ORDER BY C.comment_id";

        $comments = $this->modelsManager->executeQuery($phql);
        $ret = array();
        foreach ($comments as $comment)
        {
            array_push($ret, $comment);
        }
        return parent::result($ret);
    }

    public function deleteSecretAction($secretId)
    {
        if (!$this->request->isGet())
        {
            return parent::error(Error::BadHttpMethod, '');
        }
        parent::startAdminSession();

        $ret = SecretController::deleteSecret($secretId, -1, false);
        if ($ret === Error::None)
        {
            return parent::result(array('deleted' => true, 'secret_id' => $secretId));
        }
        else
        {
            return parent::error($ret, '');
        }
    }

    public function deleteCommentAction($secretId, $commentId)
    {
        $ret = CommentController::deleteComment($secretId, $commentId, -1, true);
        return parent::result($ret);
    }

    public function addQuestionAction()
    {
        if (!$this->request->isPost())
        {
            return parent::error(Error::BadHttpMethod, '');
        }
        parent::startAdminSession();
        $payload = $this->request->getPost();

        $imageKey = $payload['image_key'];
        $question = $payload['question'];
        if (isset($imageKey) && isset($question))
        {
            $q = new Question();
            $q->image_key = $imageKey;
            $q->question = $question;
            if ($q->save())
            {
                Cache::clearQuestions();
                return parent::result(array('add' => true, 'question_id' => $q->question_id));
            }
            return parent::error(Error::BadRecord, '');
        }

        return parent::error(Error::BadPayload, '');
    }

    public function getQuestionsAction()
    {
        $phql =
            "SELECT *".
            "FROM Question";

        $questions = $this->modelsManager->executeQuery($phql);
        $ret = array();
        foreach ($questions as $question)
        {
            array_push($ret, $question);
        }

        return parent::result($ret);
    }

    public function delQuestionAction($questionId)
    {
        $phql =
            "DELETE ".
            "FROM Question ".
            "WHERE question_id=$questionId";
        $result = $this->modelsManager->executeQuery($phql);
        return parent::result(array('deleted' => true));

    }

    private static function getRandBackgroundIndex()
    {
        return rand(1, 30);
    }

    public function getUserIdAction($username)
    {
        if (!$this->request->isGet())
        {
            return parent::error(Error::BadHttpMethod, '');
        }
        parent::startAdminSession();

        $user = User::findFirst(array("username=$username"));
        if ($user !== false)
        {
            return parent::result(array('user_id' => $user->user_id));
        }
        return parent::error(Error::BadArguments, 'Can NOT found the user with user_id = ' . $userId);
    }

    public function deleteUserAction()
    {
        if (!$this->request->isPost())
        {
            return parent::error(Error::BadHttpMethod, '');
        }
        parent::startAdminSession();

        $userId = $this->request->getPost('user_id');

        $user = User::findFirst($userId);
        if ($user !== false)
        {
            $user->delete();
            return parent::result(array('deleted' => true));
        }
        return parent::error(Error::BadArguments, 'Can NOT found the user with user_id = ' . $userId);
    }

    // Deprecated
    public function setVersionPolicyAction($platform)
    {
        if (!$this->request->isPost())
        {
            return parent::error(Error::BadHttpMethod, '');
        }
        parent::startAdminSession();

        $version = $this->request->getPost('version');
        $policy = $this->request->getPost('policy');

        if ($platform == 'ios')
        {
            NoSql::set_iOSVersionPolicy($version, $policy);
        }
        else if ($platform == 'android')
        {
            NoSql::setAndroidVersionPolicy($version, $policy);
        }
        return parent::result(array($version => $policy, 'set' => true));
    }

    public function updateAndroidVersionPolicyAction()
    {
        if (!$this->request->isPost())
        {
            return parent::error(Error::BadHttpMethod, '');
        }
        parent::startAdminSession();

        $policy = $this->request->getPost('policy');

        NoSql::clearAndroidVersionPolicy();
        foreach ($policy as $item)
        {
            $version = $item['version'];
            $tarPolicy = $item['policy'];
            NoSql::setAndroidVersionPolicy($version, $tarPolicy);
        }

        return parent::result(array('set' => 1));
    }

    public function versionsAction($platform)
    {
        if ($platform == 'ios')
        {
            $policies = NoSql::get_iOSVersions();
        }
        else if ($platform == 'android')
        {
            $policies = NoSql::getAndroidVersions();
        }

        $ret = array();

        foreach($policies as $version => $policy)
        {
            $policy = explode(':', $policy);
            $oldVersion = $version;
            $way = $policy[0];
            $newVersion = $policy[1];

            if ($policy[2] == 'latest')
            {
                $download = 'download/android/CampusSecret.apk';
            }
            else
            {
                $download = $policy[2];
            }

            $version = array('old_version' => $oldVersion, 'way' => $way, 'new_version' => $newVersion, 'download' => $download);
            array_push($ret, $version);
        }

        return parent::result($ret);
    }
    /*
    private static function getRandUserId($schoolId, &$academyId, &$grade)
    {
        if (is_null($grade))
        {
            $grades = array(2006, 2007, 2008, 2009, 2010, 2011, 2012, 2013, 2014, 2015, 2016);
            $grade = $grades[rand(0, count($grades))];
        }

        if (is_null($academyId))
        {
            $academies = SchoolController::academiesBySchoolId($schoolId);
            $count = count($academies);
            if ($count == 0)
                return false;

            $academy = $academies[rand(0, $count - 1)];
            $academyId = $academy->academy_id;
        }

        return UserController::getRandUserId($schoolId, $academyId);
    }
    */

    public function recommendSecretAction()
    {
        if (!$this->request->isPost())
        {
            return parent::error(Error::BadHttpMethod, '');
        }
        parent::startAdminSession();

        $payload = $this->request->getPost();

        $secretId = $payload['secret_id'];
        $schoolList = $payload['school_list'];    // 1,2,3;
        $schoolIdArr = explode(',', $schoolList);

        $secret = Secret::getSecret($secretId);
        $result = array();
        foreach($schoolIdArr as $schoolId)
        {
            if($schoolId != false)
            {
                $singleResult = self::recommendSecret($secretId, $schoolId, time());
                if ($singleResult->recommend !== false)
                {
                    Cache::addRecommendToSchoolSecretStream($schoolId, $secret);
                    array_push($result, $singleResult);
                }
            }
        }
        return parent::result($result);
    }

    //add a recommended secret to mysql
    public static function recommendSecret($secretId, $schoolId, $time)
    {
        $recommendedSecret = new RecommendedSecret();
        $condition = "secret_id=$secretId and school_id=$schoolId";
        $existItem = RecommendedSecret::findFirst(array($condition));
        if ($existItem !== false)
        {
            return array('recommend' => false, 'school_id' => $schoolId);
        }

        $recommendedSecret->secret_id = $secretId;
        $recommendedSecret->school_id = $schoolId;
        $recommendedSecret->recommend_time = date('Y-m-d H:i:s', $time);

        if($recommendedSecret->save() != false)
        {
            return array('recommend' => true, 'school_id' => $schoolId);
        }
        else
        {
            return array('recommend' => false, 'school_id' => $schoolId);
        }
    }

    public function addSchoolAction()
    {
        if (!$this->request->isPost())
        {
            return parent::error(Error::BadHttpMethod, '');
        }

        parent::startAdminSession();

        $payload = $this->request->getPost();
        $schoolName = $payload['name'];
        $provinceId = $payload['province'];

        $ret = SchoolController::addSchool($schoolName, $provinceId);

        if ($ret !== false)
        {
            return parent::result(array('added' => true, 'school_id' => $ret));
        }
        return parent::error(Error::BadRecord, '');
    }

    public function addAcademyAction()
    {
        if (!$this->request->isPost())
        {
            return parent::error(Error::BadHttpMethod, '');
        }

        parent::startAdminSession();

        $payload = $this->request->getPost();
        $academyName = $payload['name'];
        $academySchoolId = $payload['school_id'];
        $ret = SchoolController::addAcademy($academyName, $academySchoolId);
        if ($ret !== false)
        {
            return parent::result(array('added' => true, 'academy_id' => $ret));
        }
        return parent::error(Error::BadRecord, '');
    }

    public function delSchoolAction($schoolName)
    {
        if (!$this->request->isPost())
        {
            return parent::error(Error::BadHttpMethod, '');
        }
        parent::startAdminSession();
        $payload = $this->request->getPost();
        $schoolName = $payload['name'];
        $ret = SchoolController::delSchool($schoolName);
        if ($ret !== false)
        {
            return parent::result(array('del' => true));
        }
        return parent::error(Error::BadRecord, '');
    }

    public function deleteAcademyAction($academyId)
    {
        if (SchoolController::deleteAcademy($academyId))
        {
            return parent::result(array('deleted' => true));
        }
        return parent::error(Error::BadRecord, '');
    }
}