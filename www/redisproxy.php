<?php
/*
 *Deprecated
class RedisProxy
{
    private $redis = null;

    public function __call($name, $arguments)
    {
        $redis = $this->getRedis();
        return call_user_func_array(array($redis, $name), $arguments);
    }

    private function getRedis()
    {
        if ($this->redis == null)
        {
            $this->redis = new Redis();
            $this->redis->connect('127.0.0.1', 6379);
        }
        return $this->redis;
    }
}
*/