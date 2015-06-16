<?php
/**
 * Created by PhpStorm.
 * User: zhuomuniao1
 * Date: 14-6-20
 * Time: 下午3:19
 */

class ShortMsg
{
    // For test only
    const Username = 'bjyyhd-1';

    const Password = 'c9bf87';

    private static function query($target, $message)
    {
        $username = self::Username;
        $password = self::Password;
        $content = iconv("utf-8", "gb2312//IGNORE", $message);
        return "?un=$username&pwd=$password&mobile=$target&msg=$content";
    }

    public static function send($target, $message)
    {
        $query = self::query($target, $message);
        $xml = file_get_contents('http://si.800617.com:4400/SendLenSms.aspx' . $query);
        $r = simplexml_load_string($xml);

        return $r->Result == '1';
    }

    public static function sendVerifyCode($target, $code)
    {
        if (Config::$env == 'PROD' || $_SERVER['CSCSSM'] == 'YES')
        {
            $message = "验证码：$code" . '。您正在登录@校园秘密APP，30分钟内有效。偷窥朋友圈同学的八卦、爆料、真心话，http://xiaoyuanmimi.com' . '。';
            if (self::send($target, $message))
            {
                return array('verify' => 'Sent');
            }
            else
            {
                // Try to send but failed!
                return false;
            }
        }
        else
        {
            return array('verify' => 'Sent', 'env' => Config::$env);
        }
    }

} 