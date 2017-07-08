<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 15-1-13
 * Time: 下午4:22
 */
class AppInterfaceModel {
    private $db;
    public function __construct() {
        $this->db = DB::getInstance();
    }
    //验证接口
    public function verify($name,$time,$token,$version){
        $sql = 'insert into app_interface (name,time,token,app_version) values(:name,:time,:token,:app_version)';
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array(':name'=>$name,':time'=>$time,':token'=>$token,':app_version'=>$version));
    }
}