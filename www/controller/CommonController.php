<?php
/**
 * Created by PhpStorm.
 * User: zhuomuniao1
 * Date: 14-6-24
 * Time: 下午4:03
 */

class CommonController extends ApiController
{
    public function initialize()
    {
        NoSql::initialize($this->modelsManager);
    }

    public function downloadAction()
    {
        $agent = strtolower($_SERVER['HTTP_USER_AGENT']);
        $iphone = (strpos($agent, 'iphone')) ? true : false;
        $android = (strpos($agent, 'android')) ? true : false;
        if($iphone)
        {
            $this->dispatcher->forward(
                array(
                    'controller' => 'index',
                    'action' => 'ios'
                )
            );
        }
        elseif($android)
        {
            $this->dispatcher->forward(
                array(
                    'controller' => 'index',
                    'action' => 'android'
                )
            );
        }
    }

    public function questionsAction()
    {
        $this->view->setVar('questions', self::getCommonQuestions());
    }


    public function versionAction($platform, $version)
    {
        if ($platform == 'ios')
        {
            $policy = NoSql::get_iOSVersionPolicy($version);
        }
        else if ($platform == 'android')
        {
            $policy = NoSql::getAndroidVersionPolicy($version);
        }

        $parts = explode(':', $policy);
        
        $force = (int)$parts[0];
        $version = $parts[1];
        $where = $parts[2];

        return parent::result(array('version' => $version, 'where' => $where, 'force' => $force));
    }

    private static function getCommonQuestions()
    {
        return array(
            array(
                'q' => '如何知道秘密是哪个同学发的？',
                'a' => '为了保护隐私，不能看到秘密来自具体哪个同学。
可以看到的秘密来源如下：
（1）年级、院系——表示秘密来自你所在学校的人；
（2）某个学校——如果秘密不来自你所在学校，则会显示具体学校名称。'),
            array(
                'q' => '同学关系是从哪里来的？',
                'a' => '秘密中同学关系的显示基于你和你的同学登录时所填写的学校、年级、院系资料。为了保证同学关系的真实有效，每个用户仅能对已填资料作出1次更改。'),
            array(
                'q' => '为什么有些秘密我不能评论？',
                'a' => '为了提供良好的评论互动环境，只有秘密作者所在学校的人才能够评论。'),
            array(
                'q' => '看到了反感的秘密怎么办？',
                'a' => '如果你看到了人身攻击、学校歧视、地域偏见等任何令人反感或不适合在这里展示的秘密，请点击举报。我们将在第一时间处理。'),
            array(
                'q' => '发秘密隐私安全有保障吗？',
                'a' => '我们做了严格的隐私保护：
（1）所有的秘密或评论都不带作者名字；
（2）用户在每条秘密下评论时的头像会随机分配，不同秘密的头像不同；
（3）对特别隐私的数据，如通讯录和密码，进行了单向加密，确保即使在最坏的情况下数据被盗了也无法解密；
（4）最核心的密钥存放在客户端，确保即使是内部人员包括创始人也无法得知秘密是哪位用户发布的。'),
            array(
                'q' => '哪些内容是违规的？',
                'a' => '本应用旨在提供一个坦诚的交流平台，请大家珍惜匿名带来的好处，并正确使用它。我们不欢迎这些内容：暴力色情、广告欺诈、人身攻击、其他违反法律法规的信息。
违规内容将被删除，严重者将被封禁账号，详情见<a href="/share/pledge">《校园秘密社区公约》</a>'),
        );
    }
} 