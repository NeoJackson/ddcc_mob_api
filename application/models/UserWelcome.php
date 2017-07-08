<?php
/**
 * @name AdModel
 * @desc Ad数据获取类, 可以访问数据库，文件，其它系统等
 * @author {&$AUTHOR&}
 */
class UserWelcomeModel {
    private $db;
    public function __construct() {
        $this->db = DB::getInstance();
    }

    public function getNum(){
        $stmt = $this->db->prepare("select count(*) as num from user_welcome where status = 1");
        $stmt->execute();
        $rs =  $stmt->fetch(PDO::FETCH_ASSOC);
        return $rs['num'];
    }

    public function getWord($limit){
        $stmt = $this->db->prepare("select content from user_welcome where status = 1 order by id limit $limit,1");
        $stmt->execute();
        $rs =  $stmt->fetch(PDO::FETCH_ASSOC);
        return $rs['content'];
    }
}