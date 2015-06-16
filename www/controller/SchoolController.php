<?php
/**
 * Created by PhpStorm.
 * User: yuzhongmin
 * Date: 14-6-4
 * Time: 下午2:46
 */

class SchoolController extends ApiController
{
    private static $hot = array(1001, 1002, 1003, 1004, 1005, 1006, 1007);

    public function initialize()
    {
        parent::initializeAction();
        //parent::startSession();
    }

    // Only Admin use this action
    public function listAction($provinceTag)
    {
        $schools = School::find(array("province_tag=$provinceTag"));
        $ret = array();
        foreach ($schools as $school)
        {
            array_push($ret, $school);
        }
        return parent::result($ret);
    }

    // Only Admin use this action
    public function academyAction($schoolId)
    {
        $academies = self::academiesBySchoolId($schoolId);

        return parent::result($academies);
    }

    public static function academiesBySchoolId($schoolId)
    {
        $academies = Academy::find(array("school_id=$schoolId"));
        $ret = array();
        foreach ($academies as $academy)
        {
            array_push($ret, $academy);
        }
        return $ret;
    }

    public function academyNameAction($academyId)
    {
        return parent::result(array('name' => Cache::getAcademyName($academyId)));
    }

    public function searchSchoolAction()
    {
        if (!$this->request->isPost())
        {
            return parent::error(Error::BadHttpMethod, '');
        }

        $payload = $this->request->getPost();
        $schoolName = $payload['name'];
        $school = School::findByName($schoolName);

        if($school !== false)
        {
            $schoolId = $school->school_id;
            $academies = self::academiesBySchoolId($schoolId);
            return parent::result(array('school_id' => $schoolId, 'academies' => $academies));
        }
        else
        {
            return parent::result(array('found' => false));
        }
    }

    public static function addSchool($schoolName, $provinceId)
    {
        $school = new School();
        $school->name = $schoolName;
        $school->name2 = null;
        $school->province_tag = $provinceId;

        if($school->save() != false)
        {
            $schoolId = $school->school_id;
            return $schoolId;
        }
        else
        {
           return false;
        }
    }

    public static function addAcademy($academyName, $academySchoolId)
    {
        $academy = new Academy();
        $academy->school_id = $academySchoolId;
        $academy->name = $academyName;

        if ($academy->save() !== false)
        {
            return $academy->academy_id;
        }
        else
        {
            return false;
        }
    }

    public function hotAction()
    {
        $ret = array();
        foreach (self::$hot as $schoolId)
        {
            $schoolName = Cache::getSchoolName($schoolId);
            array_push($ret, array('school_id' => $schoolId, 'school_name' => $schoolName));
        }
        return parent::result($ret);
    }

    public static function deleteAcademy($academyId)
    {
        $academy = Academy::findFirst($academyId);
        if ($academy !== false)
        {
            $academy->delete($academyId);
            return true;
        }
        return false;
    }

    public static function delSchool($schoolName)
    {
        $school = School::findByName($schoolName);
        if($school !== false)
        {
            $schoolId = $school->school_id;
            $academies = self::academiesBySchoolId($schoolId);
            foreach ($academies as $academy)
            {
                $academy->delete($academy->academy_id);
            }
            $school->delete($schoolId);
            return ture;
        }
        return false;
    }

}