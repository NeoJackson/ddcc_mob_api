<?php

class WordModel {
    private $db;
    public function __construct() {
        $this->db = DB::getInstance();
    }

    public function hasWord($name){
        $stmt = $this->db->prepare("select count(*) as num from word where name=:name and status = 1");
        $array = array(
            ':name'=>$name,
        );
        $stmt->execute($array);
        $rs = $stmt->fetch(PDO::FETCH_ASSOC);
        return $rs['num'];
    }

}