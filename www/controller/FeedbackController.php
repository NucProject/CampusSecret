<?php

class FeedbackController extends ApiController
{
    public function initialize()
    {
        parent::startSession();
    }

    public function postAction()
    {
        if (!$this->request->isPost())
        {
            return parent::error(Error::BadHttpMethod, '');
        }

        $payload = $this->request->getPost();

        $fb = new Feedback();
        $fb->user_id = $this->curUserId;
        $fb->content = $payload['content'];
        $fb->contact = $payload['contact'];
        if ($fb->save())
        {
            return parent::result(array('feedback' => true));
        }
        else
        {
            return parent::error(Error::BadRecord, '');
        }

    }

}