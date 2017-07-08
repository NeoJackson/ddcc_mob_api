<?php
    class DB{
        private static $instances = array();

        //显著特征：私有的构造方法，避免在类外部被实例化
        private function __construct(){

        }
        //类唯一实例的全局访问点
        public static function getInstance(){
            $config = Yaf_Registry::get("config");
            $key = md5($config->db->host);
            if (!isset(self::$instances[$key])){
                try{
                    $db = new PDO($config->db->host, $config->db->username, $config->db->password);
                    $db->exec("SET NAMES utf8mb4");
                } catch (PDOException $e) {
                    echo "数据库迷路了^_^";
                    exit;
                }
                $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
                self::$instances[$key] = $db;
            }
            return self::$instances[$key];
        }

        //重载__clone方法，不允许对象实例被克隆
        public function __clone(){
            throw new Exception("Singleton Class Can Not Be Cloned");
        }

    }