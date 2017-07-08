<?php
class ActivityModel {
    private $db;
    public function __construct() {
        $this->db = DB::getInstance();
    }
    //根据url获取信息
    public function getInfoByUrl($url){
        $stmt = $this->db->prepare("select * from activity where url=:url and status = 1");
        $array = array(
            ':url'=>$url,
        );
        $stmt->execute($array);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}