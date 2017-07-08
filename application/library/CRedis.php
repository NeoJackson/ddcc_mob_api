<?php
class CRedis{
    private static $instances = array();
    private static $contentInstance = array();

    //显著特征：私有的构造方法，避免在类外部被实例化
    private function __construct(){

    }

    //类唯一实例的全局访问点
    public static function getInstance(){
        $config = Yaf_Registry::get("config");
        $key = md5($config->redis->host);
        if (!isset(self::$instances[$key])){
            $redis = new Redis();
            $redis->connect($config->redis->host,$config->redis->port,2);
            self::$instances[$key] = $redis;
        }
        return self::$instances[$key];
    }

    //缓存帖子、心境、服务、商品等后续新增内容类缓存
    public static function getContentInstance(){
        $config = Yaf_Registry::get("config");
        $key = md5($config->contentRedis->host);
        if (!isset(self::$contentInstance[$key])){
            $redis = new Redis();
            $redis->connect($config->contentRedis->host,$config->contentRedis->port,2);
            $redis->auth($config->contentRedis->password);
            self::$contentInstance[$key] = $redis;
        }
        return self::$contentInstance[$key];
    }

    //重载__clone方法，不允许对象实例被克隆
    public function __clone(){
        throw new Exception("Singleton Class Can Not Be Cloned");
    }

}