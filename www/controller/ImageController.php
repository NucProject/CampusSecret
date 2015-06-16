<?php

require_once('qiniu/rs.php');

class ImageController extends ApiController
{
    const AccessKey = 'eptRxgNhZvlghg5UtYOUhCix_SIgwLG8Dg7UqDKE';

    const SecretKey = 'RwyJ2_jN1WPKgA03czC2SRr5kooXNU6z1Hm84Wxk';

    // For Production env
    const Bucket = 'xymm';

    // For Test env, and Dev env
    const Bucket2 = 'xiaoyuanmimi2';

    const BucketDomain = 'xymm.qiniudn.com';

    const BucketDomain2 = 'xiaoyuanmimi2.qiniudn.com';

    public function initialize()
    {
        Qiniu_SetKeys(self::AccessKey, self::SecretKey);
        $this->view->disable();
    }

    public static function getBucket()
    {
        $bucket = self::Bucket;
        if (Config::$env != 'PROD')
        {
            $bucket = self::Bucket2;
        }
        return $bucket;
    }

    public function uptokenAction()
    {
        parent::startSession();

        $putPolicy = new Qiniu_RS_PutPolicy(self::getBucket());
        $upToken = $putPolicy->Token(null);
        $key = uniqid() . rand(100, 999);
        if ($this->debug)
        {
            $key = '__' . $key;
        }
        return parent::result(array('uptoken' => $upToken, 'key' => $key));
    }

    // This interface is for Qiniu JS SDK, And no need session.
    public function uptoken2Action()
    {
        $putPolicy = new Qiniu_RS_PutPolicy(self::getBucket());
        $upToken = $putPolicy->Token(null);

        echo json_encode(array('uptoken' => $upToken));
    }

    public static function getDownloadUrl($key)
    {
        Qiniu_SetKeys(self::AccessKey, self::SecretKey);
        $bucketDomain = self::BucketDomain;
        if (Config::$env != 'PROD')
        {
            $bucketDomain = self::BucketDomain2;
        }
        $baseUrl = Qiniu_RS_MakeBaseUrl($bucketDomain, $key);
        $getPolicy = new Qiniu_RS_GetPolicy();
        $privateUrl = $getPolicy->MakeRequest($baseUrl, null);
        return $privateUrl;
    }

    public function downloadUrlAction($key)
    {
        $downloadUrl = self::getDownloadUrl($key);
        return parent::result(array('download-url' => $downloadUrl,'imageKey' => $key));
    }

    /* Maybe not need delete the image */
    public function deleteAction($key)
    {
        $deleteRes = self::deleteImage($key);
        return parent::result(array('delete_image' => $deleteRes));
    }

    public static function deleteImage($key)
    {
        Qiniu_SetKeys(self::AccessKey, self::SecretKey);
        $client = new Qiniu_MacHttpClient(null);

        $err = Qiniu_RS_Delete($client, self::getBucket(), $key);
        return $err;
    }



} 