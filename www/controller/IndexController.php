<?php

class IndexController extends \Phalcon\Mvc\Controller
{
    public function initialize()
    {
        //Cache::initialize($this->modelsManager);
        //NoSql::initialize($this->modelsManager);
    }

    public function indexAction()
    {
        $agent = strtolower($_SERVER['HTTP_USER_AGENT']);
        $iphone = (strpos($agent, 'iphone')) ? true : false;
        $android = (strpos($agent, 'android')) ? true : false;
        if($iphone)
        {
            $this->view->pick('index/ios');
        }
        elseif($android)
        {
            $this->view->pick('index/android');
        }
        else
        {
            $this->view->pick('index/index');
        }
        $this->setViewVars();
    }

    public function webAction()
    {
        $this->setViewVars();
    }

    public function androidAction()
    {
        $this->setViewVars();
    }

    public function iosAction()
    {
        $this->setViewVars();
    }

    private function setViewVars()
    {
        $this->view->setVar('title', '校园秘密APP');

        $logo='/media/image_web/logo120_web.png';
        $this->view->setVar('logo', $logo);

        $background = '/media/bg/7.jpg';
        $this->view->setVar('background', $background);

        $introduction1 = '/media/image_web/people.png';
        $this->view->setVar('introduction1', $introduction1);
        $introduction2 = 'media/image_web/cloud.png';
        $this->view->setVar('introduction2', $introduction2);
        $introduction3 = 'media/image_web/eye.png';
        $this->view->setVar('introduction3',$introduction3);
        $introduction4 = 'media/image_web/schoolbus.png';
        $this->view->setVar('introduction4', $introduction4);
        $comment1="八卦爆料朋友圈同学秘密";
        $this->view->setVar('comment1', $comment1);
        $comment2 = "DotA宅男向女神表白神器";
        $this->view->setVar('comment2', $comment2);
        $comment3 = " 偷窥校花、男神内心独白";
        $this->view->setVar('comment3', $comment3);
        $comment4 = " 目击校园里各种奇葩怪咖";
        $this->view->setVar('comment4', $comment4);
        $comment5 = "Copyright @2014 校园秘密 -京ICP备1000号 -联系我们";
        $this->view->setVar('comment5' ,$comment5);
    }

    public function weixindownloadAction()
    {
    }

    public function styleAction()
    {
    }

    public function sidebarAction()
    {
    }

    public function headerAction()
    {
    }
}